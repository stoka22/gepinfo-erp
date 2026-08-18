<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\OvertimeBalance;
use App\Models\TimeEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Egyszeri javító parancs: eltávolítja a worklog-import által tévesen létrehozott
 * duplikált jelenlét-bejegyzéseket, amik egy MÁR meglévő `gépi` (terminál) eredetű sort
 * duplikálnak. A `WorkLogsImport::hasPresenceEntry()` duplikáció-védelme a kezdést a
 * kerekített alakban hasonlította össze, a `gépi` sorok viszont a NYERS, kerekítetlen
 * időt tárolják `start_time`-ban (nincs külön `raw_start_time`-juk) — emiatt a védelem
 * sosem ismerte fel ezeket azonos műszaknak, és minden újabb worklog-szinkron újra
 * felvitte ugyanazt a napot. A gyökérokot ld. `WorkLogsImport::hasPresenceEntry()`
 * javítása (2026-08-18) — ez a parancs csak a már korábban keletkezett szemetet
 * takarítja el, a jövőbeli importoknál a javított védelem véd.
 *
 * A duplikátum azonosítása: azonos employee_id + start_date + end_time, és a
 * worklog-import sor `raw_start_time`-ja pontosan egyezik a gépi sor `start_time`-jával
 * (mindkettő a nyers, kerekítetlen kezdést tárolja, csak más oszlopban). A gépi sor
 * marad meg (hiteles, elsődleges forrás), a worklog-import törlődik — a hozzá könyvelt
 * `overtime_delta_minutes` visszavonva az érintett dolgozó `OvertimeBalance.balance_minutes`
 * egyenlegéből.
 *
 * A `--dry` opcióval jelentés készül írás nélkül.
 */
class DedupGepiVsWorklogPresence extends Command
{
    protected $signature = 'attendance:dedup-gepi-vs-worklog {--dry : csak jelentés, nincs írás}';

    protected $description = 'Törli a worklog-import jelenlét-duplikátumokat, amik egy már meglévő gépi (terminál) sort duplikálnak, és korrigálja az érintett dolgozók túlóra-egyenlegét.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');

        $pairs = DB::table('time_entries as w')
            ->join('time_entries as g', function ($j) {
                $j->on('w.employee_id', '=', 'g.employee_id')
                    ->on('w.start_date', '=', 'g.start_date')
                    ->on('w.end_time', '=', 'g.end_time')
                    ->whereColumn('w.raw_start_time', '=', 'g.start_time')
                    ->where('g.entry_method', 'gépi')
                    ->where('w.entry_method', 'worklog-import');
            })
            ->where('w.type', 'presence')
            ->where('g.type', 'presence')
            ->select(
                'w.id as worklog_id',
                'w.employee_id',
                'w.start_date',
                'w.overtime_delta_minutes as w_delta',
                'g.id as gepi_id'
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
