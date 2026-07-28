<?php

namespace App\Imports;

use App\Models\WorkLog;
use App\Support\SpreadsheetEncoding;
use Illuminate\Support\Facades\Log;
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
        $spreadsheet = SpreadsheetEncoding::loadNormalized($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        // A HTML-alapú "fake xls" exportoknál soronként eltérhet a cellák száma (pl. egy
        // üres/rövidebb sor), ezért a sor saját legmagasabb oszlopa helyett a TELJES lap
        // legmagasabb oszlopáig kényszerítjük az iterálást, hogy minden sor egyenlő
        // hosszúságú (üresekkel kitöltött) cellalistát adjon.
        $highestColumn = $sheet->getHighestColumn();

        foreach ($sheet->getRowIterator(2) as $row) {
            $cellIterator = $row->getCellIterator('A', $highestColumn);
            $cellIterator->setIterateOnlyExistingCells(false);

            $cells = [];
            foreach ($cellIterator as $cell) {
                $cells[] = $cell;
            }

            $value = fn (int $i): ?string => isset($cells[$i]) ? trim((string) $cells[$i]->getValue()) : null;

            if (empty($value(0))) {
                continue;
            }

            $kezdes = isset($cells[4]) ? $this->parseDate($cells[4], 'kezdes', $row->getRowIndex()) : null;
            $vege = isset($cells[6]) ? $this->parseDate($cells[6], 'vege', $row->getRowIndex()) : null;

            // Nincs sem belépés, sem kilépés időpont rögzítve (hétvége/ünnep/távollét sor) –
            // ez nem tényleges munkanapló-bejegyzés, nem importáljuk.
            if ($kezdes === null && $vege === null) {
                continue;
            }

            WorkLog::create([
                'nev'            => $value(0),
                'munkakor'       => $value(1),
                'helyiseg'       => $value(2),
                'belepesi_pont'  => $value(3),
                'kezdes'         => $kezdes,
                'kilepesi_pont'  => $value(5),
                'vege'           => $vege,
                'ido'            => $value(7),
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
