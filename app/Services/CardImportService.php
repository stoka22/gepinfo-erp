<?php

namespace App\Services;

use App\Models\Card;
use App\Models\CardImport;
use App\Models\CardImportRow;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CardImportService
{
    public function import(string $absolutePath): CardImport
    {
        if (! file_exists($absolutePath)) {
            throw new \InvalidArgumentException('Fájl nem található: ' . $absolutePath);
        }

        $sheet = IOFactory::load($absolutePath)->getActiveSheet();
        $rows  = $sheet->toArray(null, true, true, true);
        if (! $rows || count($rows) === 0) {
            throw new \RuntimeException('Üres fájl.');
        }

        $header   = array_map(fn($v) => Str::slug(trim((string) $v), '_'), array_shift($rows));
        $uidKey   = $this->firstKey($header, ['rfid','kartyaszam','kártyaszám','card','uid','azonosito','azonosító']);
        $nameKey  = $this->firstKey($header, ['nev','name','dolgozo','dolgozó']);
        $labelKey = $this->firstKey($header, ['label','megjegyzes','megjegyzés','note']);

        if ($uidKey === null) {
            throw new \RuntimeException('Nem található kártya/UID oszlop.');
        }

        $import = CardImport::create(['source' => basename($absolutePath)]);

        $insertRows = [];

        foreach ($rows as $r) {
            $rawUid = trim((string)($r[$this->colKey($header, $uidKey)] ?? ''));
            if ($rawUid === '') continue;

            $normUid  = $this->normalizeUid($rawUid);
            $rawName  = $nameKey  ? trim((string)($r[$this->colKey($header, $nameKey)]  ?? '')) : null;
            $rawLabel = $labelKey ? trim((string)($r[$this->colKey($header, $labelKey)] ?? '')) : null;

            // --- cards upsert: notes = raw_name ---
            $card = Card::where('uid', $normUid)->first();

            if ($card) {
                $updates = [];

                if ($rawLabel && ! $card->label) {
                    $updates['label'] = $rawLabel;
                }
                if ($rawName) {
                    // fűzzük hozzá a nevet a notes végére (ha még nincs tartalom)
                    $updates['notes'] = trim(($card->notes ? ($card->notes . PHP_EOL) : '') . $rawName);
                }

                if (!empty($updates)) {
                    $card->update($updates);
                }

                // ENUM-kompatibilis státusz a stagingben
                $rowStatus = 'new';
            } else {
                Card::create([
                    'uid'    => $normUid,
                    'label'  => $rawLabel,
                    'notes'  => $rawName,     // <-- ide kerül a név
                    'status' => 'available',
                ]);

                $rowStatus = 'new';         // ENUM-kompatibilis
            }

            $insertRows[] = [
                'card_import_id'      => $import->id,
                'raw_uid'             => $rawUid,
                'raw_name'            => $rawName,
                'raw_company'         => null,
                'matched_employee_id' => null,
                'match_score'         => null,
                'status'              => $rowStatus,   // 'new' biztosan elfogadott az enum-ban
                'meta'                => null,
                'created_at'          => now(),
                'updated_at'          => now(),
            ];
        }

        if (!empty($insertRows)) {
            CardImportRow::insert($insertRows);
        }

        return $import->fresh('rows');
    }

    // --- helpers ---
    protected function firstKey(array $header, array $c): ?string
    {
        foreach ($c as $x) foreach ($header as $h) if ($h === $x) return $x;
        return null;
    }

    protected function colKey(array $header, string $key): ?string
    {
        foreach ($header as $i => $h) if ($h === $key) return $i;
        return null;
    }

    protected function normalizeUid(string $uid): string
    {
        $u = strtoupper($uid);
        return str_replace([' ', '-', ':', '.'], '', $u);
    }
}
