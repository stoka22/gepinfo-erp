<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\OvertimeBalance;
use App\Models\TimeEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Egyszeri javító parancs: eltávolítja a work-logs:sync-presence (2026-08-07) által
 * tévesen létrehozott duplikált jelenlét-bejegyzéseket, amik egy MÁR meglévő
 * daily-import sort duplikáltak (ugyanaz az employee_id+start_date+start_time). A
 * duplikáció-védelem hiánya miatt ezek a bejegyzések a valós ledolgozott időt
 * megduplázva lettek elszámolva a TimeEntryObserver-en keresztül, ezért a törlés
 * mellett a hozzájuk tartozó overtime_delta_minutes-t is visszavonjuk az érintett
 * dolgozó OvertimeBalance.balance_minutes egyenlegéből, különben a duplikátum
 * törlése után is tévesen magas maradna a bankolt túlóra.
 *
 * A `--dry` opcióval jelentés készül írás nélkül. Csak a pontosan
 * daily-import <-> worklog-import párokat érinti; más forráspárokat (pl.
 * office/gépi <-> worklog-import) szándékosan nem.
 */
class DedupDailyVsWorklogPresence extends Command
{
    protected $signature = 'attendance:dedup-daily-vs-worklog {--dry : csak jelentés, nincs írás}';

    protected $description = 'Törli a worklog-import jelenlét-duplikátumokat, amik egy már meglévő daily-import sort duplikálnak, és korrigálja az érintett dolgozók túlóra-egyenlegét.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');

        $pairs = DB::table('time_entries as w')
            ->join('time_entries as d', function ($j) {
                $j->on('w.employee_id', '=', 'd.employee_id')
                    ->on('w.start_date', '=', 'd.start_date')
                    ->on('w.start_time', '=', 'd.start_time')
                    ->where('d.entry_method', 'daily-import')
                    ->where('w.entry_method', 'worklog-import');
            })
            ->where('w.type', 'presence')
            ->where('d.type', 'presence')
            ->select(
                'w.id as worklog_id',
                'w.employee_id',
                'w.start_date',
                'w.overtime_delta_minutes as w_delta',
                'd.id as daily_id'
            )
            ->get();

        if ($pairs->isEmpty()) {
            $this->info('Nincs törlendő duplikátum.');
            return self::SUCCESS;
        }

        $this->info("Törlendő worklog-import duplikátum sorok: {$pairs->count()}");
        $this->line('---');

        $byEmployee = $pairs->groupBy('employee_id');
        $totalReduced = 0;

        foreach ($byEmployee as $employeeId => $rows) {
            $employee = Employee::find($employeeId);
            $balance = OvertimeBalance::where('employee_id', $employeeId)->first();
            $currentBalance = $balance->balance_minutes ?? 0;
            $reduceBy = (int) $rows->sum('w_delta');
            $newBalance = $currentBalance - $reduceBy;

            $this->line(sprintf(
                '%s: %d sor törlésre | egyenleg-korrekció: %d perc | jelenlegi: %d -> új: %d',
                $employee?->name ?? "id={$employeeId}",
                $rows->count(),
                -$reduceBy,
                $currentBalance,
                $newBalance
            ));
            $totalReduced += $reduceBy;

            if (! $dry) {
                DB::transaction(function () use ($rows, $balance, $reduceBy) {
                    if ($balance && $reduceBy !== 0) {
                        $balance->decrement('balance_minutes', $reduceBy);
                    }
                    TimeEntry::whereIn('id', $rows->pluck('worklog_id'))->delete();
                });
            }
        }

        $this->line('---');
        $this->info("Összesen visszavont perc: {$totalReduced} ({$byEmployee->count()} dolgozó)");

        if ($dry) {
            $this->warn('Ez csak próbafuttatás volt (--dry) — nem történt tényleges törlés/korrekció.');
        }

        return self::SUCCESS;
    }
}
