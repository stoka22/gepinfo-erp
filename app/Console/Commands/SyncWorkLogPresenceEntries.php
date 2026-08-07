<?php

namespace App\Console\Commands;

use App\Imports\WorkLogsImport;
use App\Models\WorkLog;
use Illuminate\Console\Command;

class SyncWorkLogPresenceEntries extends Command
{
    protected $signature = 'work-logs:sync-presence {--dry : Csak összesítést mutat, nem ír az adatbázisba}';

    protected $description = 'Minden dolgozóhoz már párosított munkanapló-sorhoz létrehozza a hiányzó time_entries (jelenlét) bejegyzést. Biztonságosan, ismételten futtatható — a már szinkronizált sorokat kihagyja.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');

        $total = WorkLog::whereNotNull('employee_id')->count();
        if ($total === 0) {
            $this->info('Nincs dolgozóhoz párosított munkanapló-sor.');
            return self::SUCCESS;
        }

        $this->info("{$total} dolgozóhoz párosított sor átvizsgálása...");

        $import = new WorkLogsImport;
        $alreadySynced = 0;
        $created = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        WorkLog::whereNotNull('employee_id')
            ->orderBy('id')
            ->chunkById(500, function ($logs) use ($import, &$alreadySynced, &$created, $dry, $bar) {
                foreach ($logs as $log) {
                    $row = [
                        'kezdes'      => $log->kezdes?->format('Y-m-d H:i:s'),
                        'vege'        => $log->vege?->format('Y-m-d H:i:s'),
                        'helyiseg'    => $log->helyiseg,
                        'employee_id' => $log->employee_id,
                    ];
                    $lookup = WorkLogsImport::presenceEntryLookupKey($row);

                    if (WorkLogsImport::hasPresenceEntry($log->employee_id, $lookup)) {
                        $alreadySynced++;
                    } else {
                        if (! $dry) {
                            $import->createPresenceEntry($row);
                        }
                        $created++;
                    }

                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['', 'Darab'],
            [
                ['Dolgozóhoz párosított sor összesen', $total],
                ['Már szinkronizált volt (kihagyva)', $alreadySynced],
                [$dry ? 'Létrehozásra várna' : 'Most létrehozva', $created],
            ]
        );

        if ($dry && $created > 0) {
            $this->warn('Ez csak próbafuttatás volt (--dry) — nem történt tényleges mentés. Futtasd újra a --dry kapcsoló nélkül.');
        }

        return self::SUCCESS;
    }
}
