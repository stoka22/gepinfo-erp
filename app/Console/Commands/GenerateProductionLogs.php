<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Machine;
use App\Models\ProductionLog;
use Carbon\Carbon;
use Throwable;

class GenerateProductionLogs extends Command
{
    protected $signature = 'production:generate';
    protected $description = 'Percenkénti darabszám generálása a cron_enabled gépekre';

    public function handle(): int
    {
        $now = Carbon::now()->startOfMinute();

        try {
            // ha nem akarod a duplázást, tegyél unique indexet (lentebb)
            $machines = Machine::query()
                ->where('cron_enabled', true)
                ->get();

            if ($machines->isEmpty()) {
                $this->line('Nincs cron_enabled gép.');
                return self::SUCCESS;
            }

            DB::beginTransaction();

            foreach ($machines as $m) {
                $qty = random_int(0, 5);

                // ha el akarod kerülni a duplát ugyanarra a percre:
                // ProductionLog::firstOrCreate(
                //     ['machine_id' => $m->id, 'created_at' => $now],
                //     ['qty' => $qty]
                // );

                ProductionLog::create([
                    'machine_id' => $m->id,
                    'qty'        => $qty,
                    'created_at' => $now,
                ]);

                $this->info("[{$now}] {$m->code} → +{$qty} db");
            }

            DB::commit();
            return self::SUCCESS;

        } catch (Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());
            report($e);
            return self::FAILURE;
        }
    }
}
