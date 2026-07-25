<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportPunches extends Command
{
    protected $signature = 'import:punches {file : Path to JSON or CSV} {--tz=Europe/Budapest} {--company=} {--dry : Parse only, no DB write}';
    protected $description = 'Pair raw in/out punches into time_entries rows';

    public function handle()
    {
        $file = $this->argument('file');
        if (!is_file($file)) {
            $this->error("File not found: $file");
            return 1;
        }

        $tz      = $this->option('tz') ?: 'Europe/Budapest';
        $company = $this->option('company'); // optional fixed company_id
        $dry     = (bool)$this->option('dry');
        $batchId = 'punches_'.date('Ymd_His').'_'.Str::random(6);

        $rows = $this->readFile($file);
        if (!$rows) {
            $this->error('No rows to import.');
            return 1;
        }

        // Csoportosítás RFID szerint, rendezett idő szerint
        $byRfid = [];
        foreach ($rows as $r) {
            $rfid = trim((string)($r['rfid'] ?? ''));
            $ts   = (string)($r['ts'] ?? '');
            $ev   = strtolower((string)($r['event'] ?? ''));

            if ($rfid === '' || !in_array($ev, ['in','out'], true) || $ts === '') continue;
            $byRfid[$rfid][] = [
                'ts' => CarbonImmutable::parse($ts)->setTimezone($tz),
                'event' => $ev,
                'raw' => $r,
            ];
        }
        foreach ($byRfid as &$list) {
            usort($list, fn($a,$b) => $a['ts'] <=> $b['ts']);
        }

        $insert = [];
        $skipped = 0;

        foreach ($byRfid as $rfid => $events) {
            $rfidHash = hash_hmac('sha256', $rfid, env('RFID_HMAC_KEY'));
            $emp = DB::table('employees')->where('rfid_hash', $rfidHash)->first();

            if (!$emp) { $skipped += count($events); continue; }
            $employeeId = $emp->id;

            $open = null; // nyitott "in"
            foreach ($events as $e) {
                if ($e['event'] === 'in') {
                    // ha volt nyitott, elengedjük (adatminőség)
                    $open = $e['ts'];
                } else {
                    // out
                    if (!$open) {
                        // nincs mihez zárni → átugorjuk
                        continue;
                    }
                    $start = $open;
                    $end   = $e['ts'];
                    if ($end <= $start) {
                        // hibás sorrend
                        $open = null;
                        continue;
                    }

                    // páros kész
                    $worked = $end->diffInMinutes($start);
                    $insert[] = [
                        'employee_id'    => $employeeId,
                        'company_id'     => $company ? (int)$company : null,
                        'start_date'     => $start->toDateString(),
                        'start_time'     => $start->toTimeString(),
                        'end_date'       => $end->toDateString(),
                        'end_time'       => $end->toTimeString(),
                        'worked_minutes' => $worked,
                        'hours'          => round($worked / 60, 2),
                        'type'           => 'presence',
                        'entry_method'   => 'rfid',
                        'status'         => 'confirmed',
                        'note'           => 'batch='.$batchId.';rfid_hash='.$rfidHash,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];
                    $open = null;
                }
            }
        }

        $this->info("Prepared pairs: ".count($insert).", skipped punches: ".$skipped." (unmatched or no employee).");
        if ($dry) { $this->warn('Dry-run: nothing written.'); return 0; }

        // Írás DB-be nagyobb csomagokban
        DB::transaction(function () use ($insert) {
            foreach (array_chunk($insert, 500) as $chunk) {
                DB::table('time_entries')->insert($chunk);
            }
        });

        $this->info("Import completed. Batch: $batchId");
        $this->line("Visszavonás (rollback) parancs minta:");
        $this->line("DELETE FROM time_entries WHERE note LIKE '%batch=$batchId%';");

        return 0;
    }

    private function readFile(string $path): array
    {
        $lower = strtolower($path);
        if (str_ends_with($lower, '.json')) {
            $data = json_decode(file_get_contents($path), true);
            return is_array($data) ? $data : [];
        }
        if (str_ends_with($lower, '.csv')) {
            $fh = fopen($path, 'r');
            if (!$fh) return [];
            $head = fgetcsv($fh);
            $rows = [];
            while (($r = fgetcsv($fh)) !== false) {
                $rows[] = array_combine($head, $r);
            }
            fclose($fh);
            return $rows;
        }
        return [];
    }
}
