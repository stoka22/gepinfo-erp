<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\EmployeeCard;
use Illuminate\Support\Str;

class CardsImportCommand extends Command
{
    protected $signature = 'cards:import {path : XLS/XLSX/CSV fájl útvonala} {--company=}';
    protected $description = 'Dolgozói kártyák importálása (staging) és automatikus párosítás';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (! file_exists($path)) {
            $this->error('Fájl nem található: '.$path);
            return self::FAILURE;
        }

        // ── 1) Beolvasás PhpSpreadsheet-del ─────────────────────────────────────
        if (! class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            $this->error('Hiányzik a phpoffice/phpspreadsheet csomag. Telepítsd: composer require phpoffice/phpspreadsheet');
            return self::FAILURE;
        }

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) === 0) {
            $this->error('Üres fájl.');
            return self::FAILURE;
        }

        // Fejléc meghatározása
        $header = array_map(fn($v) => Str::slug(trim((string)$v), '_'), array_shift($rows));
        // kötelező mezők kitalálása
        $nameKey = $this->firstKey($header, ['nev','name','dolgozo','dolgozó']);
        $uidKey  = $this->firstKey($header, ['rfid','kartyaszam','kártyaszám','card','uid','azonosito','azonosító']);
        $companyKey = $this->firstKey($header, ['ceg','cég','vallalat','vállalat','company']);

        if ($uidKey === null) {
            $this->error('Nem találok kártya/UID oszlopot a fájlban.');
            return self::FAILURE;
        }

        // ── 2) Import rekord ────────────────────────────────────────────────────
        $importId = \DB::table('card_imports')->insertGetId([
            'source' => basename($path),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info('Import ID: '.$importId);

        // ── 3) Sorok bejárása + párosítás ───────────────────────────────────────
        $insertRows = [];
        $auto = $amb = $new = 0;

        foreach ($rows as $r) {
            $rawName = $nameKey ? trim((string)($r[$this->colKey($header, $nameKey)] ?? '')) : null;
            $rawUid  = trim((string)($r[$this->colKey($header, $uidKey)] ?? ''));
            $rawCompany = $companyKey ? trim((string)($r[$this->colKey($header, $companyKey)] ?? '')) : null;

            if ($rawUid === '') continue;

            // normalizált név
            $norm = $this->normalizeName($rawName);

            // 1) pontos egyezés névre
            $match = null;
            $score = null;

            if ($norm) {
                $cand = Employee::query()
                    ->when($this->option('company'), fn($q,$cid) => $q->where('company_id', $cid))
                    ->get(['id','name']);
                foreach ($cand as $e) {
                    $s = $this->similarity($norm, $this->normalizeName($e->name));
                    if ($s >= 98) { $match = $e->id; $score = $s; break; }
                    if ($score === null || $s > $score) { $score = $s; $match = $e->id; }
                }
            }

            $status = 'new';
            $matchedEmployeeId = null;

            if ($score !== null) {
                if ($score >= 98) { $status = 'auto'; $matchedEmployeeId = $match; $auto++; }
                elseif ($score >= 80) { $status = 'ambiguous'; $amb++; }
                else { $status = 'new'; $new++; }
            } else {
                $new++;
            }

            // duplikált UID?
            if (EmployeeCard::where('card_uid', $rawUid)->exists()) {
                $status = 'duplicate';
            }

            $insertRows[] = [
                'card_import_id' => $importId,
                'raw_name'       => $rawName,
                'raw_uid'        => $rawUid,
                'raw_company'    => $rawCompany,
                'matched_employee_id' => $matchedEmployeeId,
                'match_score'    => $score,
                'status'         => $status,
                'meta'           => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }

        \DB::table('card_import_rows')->insert($insertRows);

        $this->table(
            ['Import ID','Auto','Ambiguous','New/Other','Rows'],
            [[ $importId, $auto, $amb, $new, count($insertRows) ]]
        );

        $this->info('Kész. A kétes sorokat a card_import_rows táblában (status=ambiguous/new) kézzel beállíthatod a matched_employee_id mezővel, majd futtasd: php artisan cards:apply '.$importId);

        return self::SUCCESS;
    }

    protected function firstKey(array $header, array $candidates): ?string
    {
        foreach ($candidates as $c) {
            foreach ($header as $idx => $h) {
                if ($h === $c) return $c;
            }
        }
        return null;
    }

    protected function colKey(array $header, string $key): ?string
    {
        foreach ($header as $idx => $h) {
            if ($h === $key) return $idx;
        }
        return null;
    }

    protected function normalizeName(?string $name): ?string
    {
        if (! $name) return null;
        $n = mb_strtolower(trim($name));
        $n = str_replace(['/', '\\', '  '], ' ', $n);
        $n = strtr($n, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ö'=>'o','ő'=>'o','ú'=>'u','ü'=>'u','ű'=>'u',
            'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ö'=>'o','Ő'=>'o','Ú'=>'u','Ü'=>'u','Ű'=>'u',
        ]);
        return preg_replace('/\s+/', ' ', $n);
    }

    protected function similarity(string $a, string $b): float
    {
        similar_text($a, $b, $p);
        return $p; // 0..100
    }
}
