<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\OvertimeBalance;
use App\Models\TimeEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Egyszeri javító parancs: eltávolítja azokat a `worklog-import` jelenlét-bejegyzéseket,
 * amik egy MÁSIK, ugyanolyan forrású (`worklog-import`) sort duplikálnak — ugyanaz a
 * dolgozó, ugyanaz a nap, a kilépés 60 másodpercen belül egyezik, a kezdés viszont pár
 * perccel eltér (két külön munkanapló-import/fájlverzió rögzítette ugyanazt a valós
 * műszakot kicsit eltérő, kézzel bevitt kezdési idővel). A `WorkLogsImport::
 * hasPresenceEntry()` duplikáció-védelme percre pontos kilépés-egyezést vár, de a
 * kezdésnél a (kerekített) `start_time` egyezését is megköveteli — ha a két import
 * eltérő kezdést rögzített, a kerekített `start_time` is eltérhet, így a védelem nem
 * ismerte fel ezeket egymás duplikátumának.
 *
 * A KORÁBBI (alacsonyabb id-jű) sor marad meg, a KÉSŐBBI törlődik — a hozzá könyvelt
 * `overtime_delta_minutes` visszavonva az érintett dolgozó `OvertimeBalance.balance_minutes`
 * egyenlegéből.
 *
 * A `--dry` opcióval jelentés készül írás nélkül.
 */
class DedupWorklogSelfDuplicatePresence extends Command
{
    protected $signature = 'attendance:dedup-worklog-self-duplicate {--dry : csak jelentés, nincs írás}';

    protected $description = 'Törli a worklog-import jelenlét-duplikátumokat, amik egy másik, korábbi worklog-import sort duplikálnak (közel azonos kilépéssel), és korrigálja az érintett dolgozók túlóra-egyenlegét.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');

        $pairs = DB::table('time_entries as a')
            ->join('time_entries as b', function ($j) {
                $j->on('a.employee_id', '=', 'b.employee_id')
                    ->on('a.start_date', '=', 'b.start_date')
                    ->whereColumn('a.id', '<', 'b.id')
                    ->where('a.entry_method', 'worklog-import')
                    ->where('b.entry_method', 'worklog-import')
                    ->whereRaw('ABS(TIMESTAMPDIFF(SECOND, a.end_time, b.end_time)) <= 60');
            })
            ->where('a.type', 'presence')
            ->where('b.type', 'presence')
            ->select(
                'b.id as delete_id',
                'a.id as keep_id',
                'a.employee_id',
                'a.start_date',
                'b.overtime_delta_minutes as delete_delta'
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
            $reduceBy = (int) $rows->sum('delete_delta');
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
                    TimeEntry::whereIn('id', $rows->pluck('delete_id'))->delete();
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
