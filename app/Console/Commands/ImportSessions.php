<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportSessions extends Command
{
    protected $signature = 'import:sessions {file} {--tz=Europe/Budapest} {--company=} {--dry}';
    protected $description = 'Import paired sessions (start_ts/end_ts) into time_entries';

    public function handle()
    {
        $file = $this->argument('file');
        $tz   = $this->option('tz') ?: 'Europe/Budapest';
        $company = $this->option('company');
        $dry  = (bool)$this->option('dry');
        $batchId = 'sessions_'.date('Ymd_His').'_'.Str::random(6);

        $rows = $this->readFile($file);
        if (!$rows) { $this->error('Invalid/empty file'); return 1; }

        $insert = [];
        $skipped = 0;

        foreach ($rows as $r) {
            $rfid = trim((string)($r['rfid'] ?? ''));
            $startTs = $r['start_ts'] ?? null;
            $endTs   = $r['end_ts']   ?? null;
            if ($rfid === '' || !$startTs || !$endTs) { $skipped++; continue; }

            $start = CarbonImmutable::parse($startTs)->setTimezone($tz);
            $end   = CarbonImmutable::parse($endTs)->setTimezone($tz);
            if ($end <= $start) { $skipped++; continue; }

            $rfidHash = hash_hmac('sha256', $rfid, env('RFID_HMAC_KEY'));
            $emp = DB::table('employees')->where('rfid_hash', $rfidHash)->first();
            if (!$emp) { $skipped++; continue; }

            $worked = $end->diffInMinutes($start);
            $insert[] = [
                'employee_id'    => $emp->id,
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
        }

        $this->info("Prepared: ".count($insert).", skipped: $skipped");
        if ($dry) { $this->warn('Dry-run'); return 0; }

        DB::transaction(function () use ($insert) {
            foreach (array_chunk($insert, 500) as $chunk) {
                DB::table('time_entries')->insert($chunk);
            }
        });

        $this->info("Import completed. Batch: $batchId");
        $this->line("Rollback minta: DELETE FROM time_entries WHERE note LIKE '%batch=$batchId%';");
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

