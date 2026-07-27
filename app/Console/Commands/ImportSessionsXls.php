<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlsDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ImportSessionsXls extends Command
{
    protected $signature = 'import:sessions-xls 
        {file : Path to .xls/.xlsx/.csv}
        {--tz=Europe/Budapest}
        {--company=}
        {--name=Név : Név oszlop címe}
        {--start=Kezdés : Kezdés oszlop címe}
        {--end=Vége   : Vége oszlop címe}
        {--locale=hu}
        {--errors-csv= : Hibák exportálása ide}
        {--dry}';

    protected $description = 'Kezdés–Vége időszakok importja XLS/CSV-ből, cards.notes és/vagy employees.name alapú párosítással. Részletes hibaloggal.';

    public function handle()
    {
        $file    = $this->argument('file');
        $tz      = $this->option('tz') ?: 'Europe/Budapest';
        $company = $this->option('company');
        $dry     = (bool)$this->option('dry');
        $locale  = strtolower($this->option('locale') ?: 'hu');
        $batchId = 'sessions_'.date('Ymd_His').'_'.Str::random(6);

        if (!is_file($file)) {
            $this->error("Nincs fájl: $file");
            return 1;
        }

        /** @var Worksheet $sheet */
        $sheet = IOFactory::load($file)->getActiveSheet();
        $highestRow = (int) $sheet->getHighestRow();
        $highestColIndex = Coordinate::columnIndexFromString($sheet->getHighestColumn());
        
        $open = []; // employee_id => ['start' => CarbonImmutable, 'row' => int, 'dbg' => array]
        $preparedCount = 0;

        $insertChunk = [];
        $chunkSize = 500;

        if ($highestRow < 2) {
            $this->warn('Üres fájl.');
            return 0;
        }

        // --- Fejlécek ---
        $headers = [];
        for ($c = 1; $c <= $highestColIndex; $c++) {
            $colLetter   = Coordinate::stringFromColumnIndex($c);
            $cellAddress = $colLetter . '1';
            $headers[$c] = trim((string) $sheet->getCell($cellAddress)->getValue());
        }
        $nameHeader  = (string) $this->option('name');
        $startHeader = (string) $this->option('start');
        $endHeader   = (string) $this->option('end');

        $findCol = function (string $wanted) use ($headers) {
            foreach ($headers as $col => $text) {
                if ($text === $wanted) return (int) $col; // pontos egyezés
            }
            return null;
        };

        $nameCol  = $findCol($nameHeader);
        $startCol = $findCol($startHeader);
        $endCol   = $findCol($endHeader);

        if (!$nameCol || !$startCol || !$endCol) {
            $this->error('Nem találom a Név/Kezdés/Vége oszlopot.');
            $this->line('Fejlécek: '.implode(' | ', array_values($headers)));
            return 1;
        }

        // --- Törzsadatok (employees + cards) ---
        $employees = DB::table('employees')
            ->when($company, fn($q) => $q->where('company_id', (int)$company))
            ->select('id','name')
            ->get();

        $empByNormName = [];
        foreach ($employees as $e) {
            $k = $this->normalizeName($e->name);
            if (!isset($empByNormName[$k])) $empByNormName[$k] = [];
            $empByNormName[$k][] = $e;
        }

        // cards.notes -> employee_id (prioritás!)
        $cards = DB::table('cards')
            ->select('id','employee_id','notes','status','deleted_at')
            ->whereNull('deleted_at')
            ->whereNotNull('notes')
            ->get();

        $empByCardNote = [];     // normált notes -> employee_id / 'AMBIGUOUS'
        $cardNoteToCardIds = []; // debughoz
        foreach ($cards as $c) {
            $k = $this->normalizeName((string) $c->notes);
            $cardNoteToCardIds[$k][] = $c->id;
            if ($c->employee_id) {
                $eid = (int) $c->employee_id;
                if (!isset($empByCardNote[$k])) $empByCardNote[$k] = $eid;
                elseif ($empByCardNote[$k] !== $eid) $empByCardNote[$k] = 'AMBIGUOUS';
            }
        }

        $insert = [];
        $errors = [];
        $err = function(
            int $rowNo, string $reason, $name='',
            $startCellInfo=null, $endCellInfo=null
        ) use (&$errors) {
            $errors[] = [
                'row'    => $rowNo,
                'reason' => $reason,
                'name'   => (string)$name,
                'start'  => is_array($startCellInfo) ? json_encode($startCellInfo, JSON_UNESCAPED_UNICODE) : (string)$startCellInfo,
                'end'    => is_array($endCellInfo)   ? json_encode($endCellInfo,   JSON_UNESCAPED_UNICODE) : (string)$endCellInfo,
            ];
        };

        for ($r = 2; $r <= $highestRow; $r++) {
            $nameCell  = $sheet->getCell(Coordinate::stringFromColumnIndex($nameCol)  . $r);
            $startCell = $sheet->getCell(Coordinate::stringFromColumnIndex($startCol) . $r);
            $endCell   = $sheet->getCell(Coordinate::stringFromColumnIndex($endCol)   . $r);

            $nameRaw = trim((string) $nameCell->getValue());
            [$s, $sDbg] = $this->readDateCellWithDebug($startCell, $tz, $locale);
            [$e, $eDbg] = $this->readDateCellWithDebug($endCell,   $tz, $locale);

            if ($nameRaw === '') {
                $err($r, 'hiányzó Név', '', $sDbg ?: $startCell->getFormattedValue(), $eDbg ?: $endCell->getFormattedValue());
                continue;
            }
            if ($e <= $s) {
                $err($r, 'Vége <= Kezdés', $nameRaw, $sDbg, $eDbg);
                continue;
            }

            $norm = $this->normalizeName($nameRaw);

            // 1) cards.notes alapján
            $employeeId = null;
            if (isset($empByCardNote[$norm])) {
                if ($empByCardNote[$norm] === 'AMBIGUOUS') {
                    $err($r, 'azonos név több különböző kártyán (cards)', $nameRaw, $sDbg, $eDbg);
                    continue;
                }
                $employeeId = (int) $empByCardNote[$norm];
            }

            // 2) fallback: employees.name
            if (!$employeeId) {
                $hits = $empByNormName[$norm] ?? [];
                if (count($hits) === 1) {
                    $employeeId = $hits[0]->id;
                } elseif (count($hits) > 1) {
                    $err($r, 'azonos nevű több dolgozó (employees)', $nameRaw, $sDbg, $eDbg);
                    continue;
                } else {
                    $err($r, 'nem található dolgozó', $nameRaw, $sDbg, $eDbg);
                    continue;
                }
            }

            $mins = $e->diffInMinutes($s);
            $insert[] = [
                'employee_id'    => $employeeId,
                'company_id'     => $company ? (int)$company : null,
                'start_date'     => $s->toDateString(),
                'start_time'     => $s->toTimeString(),
                'end_date'       => $e->toDateString(),
                'end_time'       => $e->toTimeString(),
                'worked_minutes' => $mins,
                'hours'          => round($mins / 60, 2),
                'type'           => 'presence',
                'entry_method'   => 'name/cards',
                'status'         => 'confirmed',
                'note'           => 'batch='.$batchId.';name='.$nameRaw,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];

            $preparedCount++;
            if (!$dry && count($insertChunk) >= $chunkSize) {
                DB::table('time_entries')->insert($insertChunk);
                $insertChunk = [];
            }
            continue;

            // B) csak Kezdés van → nyitunk
            if ($s && !$e) {
                // ha már volt nyitva a dolgozónak, az előző lezáratlan
                if (isset($open[$employeeId])) {
                    $prev = $open[$employeeId];
                    $err($prev['row'], 'lezáratlan Kezdés (hiányzó Vége a következő punch előtt)', $nameRaw, $prev['dbg'], null);
                }
                $open[$employeeId] = ['start' => $s, 'row' => $r, 'dbg' => $sDbg, 'name' => $nameRaw];
                continue;
            }

            // C) csak Vége van → próbáljuk zárni a korábbi nyitott Kezdést
            if (!$s && $e) {
                if (!isset($open[$employeeId])) {
                    $err($r, 'Vége punch Kezdés nélkül', $nameRaw, null, $eDbg);
                    continue;
                }
                $st = $open[$employeeId]['start'];
                $stDbg = $open[$employeeId]['dbg'];
                $stRow = $open[$employeeId]['row'];
                unset($open[$employeeId]);

                if ($e <= $st) {
                    $err($r, 'Vége <= Nyitott Kezdés', $nameRaw, $stDbg, $eDbg);
                    continue;
                }

                $mins = $e->diffInMinutes($st);
                $insertChunk[] = [
                    'employee_id'    => $employeeId,
                    'company_id'     => $company ? (int)$company : null,
                    'start_date'     => $st->toDateString(),
                    'start_time'     => $st->toTimeString(),
                    'end_date'       => $e->toDateString(),
                    'end_time'       => $e->toTimeString(),
                    'worked_minutes' => $mins,
                    'hours'          => round($mins/60, 2),
                    'type'           => 'presence',
                    'entry_method'   => 'name/cards+pairs',
                    'status'         => 'confirmed',
                    'note'           => 'batch='.$batchId.';name='.$nameRaw,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
                $preparedCount++;

                if (!$dry && count($insertChunk) >= $chunkSize) {
                    DB::table('time_entries')->insert($insertChunk);
                    $insertChunk = [];
                }
                continue;
            }

            // D) se Kezdés, se Vége → adat nélküli sor
            $err($r, 'hiányzó Kezdés és Vége', $nameRaw, $sDbg ?: $startCell->getFormattedValue(), $eDbg ?: $endCell->getFormattedValue());
        }

        foreach ($open as $empId => $o) {
            $err($o['row'], 'lezáratlan Kezdés (Vége nem érkezett meg a fájlban)', $o['name'] ?? '', $o['dbg'], null);
        }

        if (!$dry && !empty($insertChunk)) {
            DB::table('time_entries')->insert($insertChunk);
        }
        $this->info("Előkészítve: ".$preparedCount.", hibák: ".(isset($first10) ? 'lásd fent' : '…'));

        //$this->info("Előkészítve: ".count($insert).", hibák: ".count($errors));

        if ($errors) {
            $this->warn('Első 10 hiba:');
            foreach (array_slice($errors, 0, 10) as $e) {
                $s = json_decode($e['start'], true);
                $d = json_decode($e['end'],   true);
                $this->line(sprintf(
                    "- sor #%d | %s | Név='%s'\n  Kezdés: raw='%s' fmt='%s' norm='%s' step='%s'\n  Vége:   raw='%s' fmt='%s' norm='%s' step='%s'",
                    $e['row'], $e['reason'], $e['name'],
                    $s['raw'] ?? $e['start'], $s['fmt'] ?? '', $s['norm'] ?? '', $s['step'] ?? '',
                    $d['raw'] ?? $e['end'],   $d['fmt'] ?? '', $d['norm'] ?? '', $d['step'] ?? ''
                ));
            }
        }

        if ($path = $this->option('errors-csv')) {
            if ($fh = fopen($path, 'w')) {
                fputcsv($fh, ['row','reason','name','start(raw,fmt,norm,step)','end(raw,fmt,norm,step)']);
                foreach ($errors as $e) fputcsv($fh, [$e['row'], $e['reason'], $e['name'], $e['start'], $e['end']]);
                fclose($fh);
                $this->warn("Hibalista elmentve: $path");
            } else {
                $this->error("Nem tudom megnyitni írásra: $path");
            }
        }

        if ($dry) {
            $this->warn('Dry-run, nincs írás.');
            return 0;
        }

        if ($insert) {
            DB::transaction(function () use ($insert) {
                foreach (array_chunk($insert, 500) as $chunk) {
                    DB::table('time_entries')->insert($chunk);
                }
            });
        }

        $this->info("Kész. Batch: $batchId");
        $this->line("Visszavonás: DELETE FROM time_entries WHERE note LIKE '%batch=$batchId%';");
        return 0;
    }

    private function normalizeName(string $name): string
    {
        $n = mb_strtolower(trim(preg_replace('/\s+/', ' ', $name)));
        $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ö'=>'o','ő'=>'o','ú'=>'u','ü'=>'u','ű'=>'u'];
        return strtr($n, $map);
    }

    private function readDateCell(Cell $cell, string $tz, string $locale='hu'): ?CarbonImmutable
    {
        [$dt, $dbg] = $this->readDateCellWithDebug($cell, $tz, $locale);
        return $dt;
    }

    /** Dátum olvasás részletes diagnosztikával */
    private function readDateCellWithDebug(Cell $cell, string $tz, string $locale='hu'): array
    {
        $debug = [
            'raw'   => $cell->getValue(),
            'fmt'   => $cell->getFormattedValue(),
            'is_dt' => XlsDate::isDateTime($cell) ? 1 : 0,
            'norm'  => null,
            'step'  => null,
        ];

        // 1) Excel numerikus dátum
        if (XlsDate::isDateTime($cell)) {
            $v = $cell->getValue();
            if (is_numeric($v)) {
                try {
                    $dt = XlsDate::excelToDateTimeObject((float)$v);
                    $debug['step'] = 'excel-numeric';
                    return [ CarbonImmutable::instance($dt)->setTimezone($tz), $debug ];
                } catch (\Throwable $e) {
                    $debug['step'] = 'excel-numeric-failed: '.$e->getMessage();
                }
            }
        }

        // 2) Közvetlen DateTimeInterface
        $v = $cell->getValue();
        if ($v instanceof \DateTimeInterface) {
            $debug['step'] = 'datetime-interface';
            return [ CarbonImmutable::instance($v)->setTimezone($tz), $debug ];
        }

        // 3) Szöveg + normalizálás
        $t = $cell->getFormattedValue();
        if ($t === null || $t === '') $t = (string)$cell->getValue();
        $t = preg_replace('/\x{00A0}|\x{202F}/u', ' ', (string)$t);
        $t = trim($t);
        if ($t === '') {
            $debug['step'] = 'empty-text';
            return [ null, $debug ];
        }

        // hónapnevek → szám
       // hónapnevek → szám (rövid + hosszú, ponttal és pont nélkül is)
$map = [
    'jan'=>'01','jan.'=>'01','januar'=>'01','január'=>'01',
    'feb'=>'02','feb.'=>'02','febr'=>'02','febr.'=>'02','februar'=>'02','február'=>'02',
    'mar'=>'03','mar.'=>'03','márc'=>'03','márc.'=>'03','marc'=>'03','marc.'=>'03','marcius'=>'03','március'=>'03',
    'apr'=>'04','apr.'=>'04','ápr'=>'04','ápr.'=>'04','aprilis'=>'04','április'=>'04',
    'maj'=>'05','maj.'=>'05','máj'=>'05','máj.'=>'05','majus'=>'05','május'=>'05',
    'jun'=>'06','jun.'=>'06','jún'=>'06','jún.'=>'06','junius'=>'06','június'=>'06',
    'jul'=>'07','jul.'=>'07','júl'=>'07','júl.'=>'07','julius'=>'07','július'=>'07',
    'aug'=>'08','aug.'=>'08','augusztus'=>'08',
    'szept'=>'09','szept.'=>'09','szep'=>'09','szep.'=>'09','szeptember'=>'09',
    'okt'=>'10','okt.'=>'10','oktober'=>'10','október'=>'10',
    'nov'=>'11','nov.'=>'11','november'=>'11',
    'dec'=>'12','dec.'=>'12','december'=>'12',
];

// CSAK ezt a regexet tartsd meg a hónapok cseréjéhez:
$t2 = preg_replace_callback('/[A-Za-zÁÉÍÓÖŐÚÜŰáéíóöőúüű]+\.?/u', function($m) use ($map){
    $k = mb_strtolower(rtrim($m[0], '.'));
    return $map[$k] ?? $m[0];
}, $t);

// normalizálás (maradhat változatlan)
$t2 = preg_replace('/\s+/', ' ', $t2);
$t2 = preg_replace('/\s*\.\s*/', '.', $t2);
$debug['norm'] = $t2;

// Regex: "YYYY.MM[.| ]DD. HH:MM(:SS)?"
if (preg_match('/^\s*(\d{4})\.(\d{1,2})(?:\.|\s)(\d{1,2})\.?\s*(\d{1,2}:\d{2}(?::\d{2})?)\s*$/', $t2, $m)) {
    $iso = sprintf('%04d-%02d-%02d %s', $m[1], $m[2], $m[3], $m[4]);
    try {
        $debug['step'] = 'regex-iso';
        return [ CarbonImmutable::parse($iso, $tz)->setTimezone($tz), $debug ];
    } catch (\Throwable $e) {
        $debug['step'] = 'regex-iso-failed: '.$e->getMessage();
    }
}


        // Fallback parse
        try {
            $debug['step'] = 'fallback-parse';
            return [ CarbonImmutable::parse($t2, $tz)->setTimezone($tz), $debug ];
        } catch (\Throwable $e) {
            $debug['step'] = 'fallback-failed: '.$e->getMessage();
            return [ null, $debug ];
        }
    }
}
