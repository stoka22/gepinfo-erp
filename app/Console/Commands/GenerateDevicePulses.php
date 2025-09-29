<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Device;
use Carbon\Carbon;
use Throwable;

class GenerateDevicePulses extends Command
{
    protected $signature = 'pulses:generate';
    protected $description = 'Percenkénti teszt-pulse generálása a cron_enabled eszközöknek (pulses táblába)';

    public function handle(): int
    {
        $now = Carbon::now()->startOfMinute();

        try {
            $deviceIds = Device::where('cron_enabled', true)->pluck('id')->all();
            if (empty($deviceIds)) {
                $this->line('Nincs cron_enabled eszköz.');
                return self::SUCCESS;
            }

            $rows = [];

            // Egyszerű és biztos: 1 lekérdezés / eszköz (20 eszköznél bőven oké)
            foreach ($deviceIds as $id) {
                $last = DB::table('pulses')
                    ->where('device_id', $id)
                    ->orderByDesc('sample_time')
                    ->limit(1)
                    ->first();

                $prevSampleId = (int)($last->sample_id ?? 0);
                $prevCount    = (int)($last->count     ?? 0);
                $delta        = random_int(0, 5);

                $rows[] = [
                    'device_id'   => $id,
                    'sample_id'   => $prevSampleId + 1,   // bigint
                    'sample_time' => $now,                // a „mérés” ideje
                    'count'       => $prevCount + $delta, // kumulatív számláló
                    'delta'       => $delta,              // perces növekmény
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }

            // Ütközés = ugyanarra (device_id, sample_time) már van sor -> frissítünk
            DB::table('pulses')->upsert(
                $rows,
                ['device_id', 'sample_time'],           // egyediség kulcs
                ['sample_id','count','delta','updated_at'] // mit frissítsen
            );

            $this->info('Pulses upserted: ' . count($rows) . ' @ ' . $now->toDateTimeString());
            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->error($e->getMessage());
            report($e);
            return self::FAILURE;
        }
    }
}
