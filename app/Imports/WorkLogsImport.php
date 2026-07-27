<?php

namespace App\Imports;

use App\Models\WorkLog;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use DateTime;

class WorkLogsImport
{
    protected array $honapok = [
        'jan.'   => 'Jan.',
        'febr.'  => 'Feb.',
        'márc.'  => 'Mar.',
        'ápr.'   => 'Apr.',
        'máj.'   => 'May.',
        'jún.'   => 'Jun.',
        'júl.'   => 'Jul.',
        'aug.'   => 'Aug.',
        'szept.' => 'Sep.',
        'okt.'   => 'Oct.',
        'nov.'   => 'Nov.',
        'dec.'   => 'Dec.',
    ];

    public function import(string $filePath): void
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($sheet->getRowIterator(2) as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $cells = [];
            foreach ($cellIterator as $cell) {
                $cells[] = $cell;
            }

            if (empty(trim((string)$cells[0]->getValue()))) {
                continue;
            }

            WorkLog::create([
                'nev'            => trim((string)$cells[0]->getValue()) ?? null,
                'munkakor'       => trim((string)$cells[1]->getValue()) ?? null,
                'helyiseg'       => trim((string)$cells[2]->getValue()) ?? null,
                'belepesi_pont'  => trim((string)$cells[3]->getValue()) ?? null,
                'kezdes'         => $this->parseDate($cells[4], 'kezdes', $row->getRowIndex()),
                'kilepesi_pont'  => trim((string)$cells[5]->getValue()) ?? null,
                'vege'           => $this->parseDate($cells[6], 'vege', $row->getRowIndex()),
                'ido'            => trim((string)$cells[7]->getValue()) ?? null,
            ]);
        }
    }

    protected function parseDate($cell, string $field, int $rowIndex): ?string
    {
        $value = trim((string)$cell->getValue());
        if ($value === '') {
            return null;
        }

        // Ha Excel dátum típusú
        if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($cell->getValue())->format('Y-m-d H:i:s');
        }

        // Magyar hónapnév csere
        $converted = str_replace(array_keys($this->honapok), array_values($this->honapok), $value);

        // Tisztítás: pontok és szóközök normalizálása
        $converted = preg_replace('/\s+/', ' ', $converted);
        $converted = preg_replace('/\.+/', '.', $converted);

        // Próbáljuk explicit formátummal
        $dateTime = \DateTime::createFromFormat('Y. M. j. H:i:s', $converted);

        if ($dateTime) {
            return $dateTime->format('Y-m-d H:i:s');
        }

        // Fallback: strtotime()
        $timestamp = strtotime($converted);
        if ($timestamp) {
            return date('Y-m-d H:i:s', $timestamp);
        }

        // Ha semmi nem sikerült, logoljuk
        Log::warning("Nem sikerült konvertálni a dátumot:'{$converted}'- '{$value}' (mező: {$field}, sor: {$rowIndex})");
        return null;
    }
}
