<?php

namespace App\Console\Commands;

use App\Enums\TimeEntryStatus;
use App\Enums\TimeEntryType;
use App\Models\Employee;
use App\Models\TimeEntry;
use App\Services\Overtime\OvertimeBalanceService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RecomputeOvertimeBalances extends Command
{
    protected $signature = 'overtime:recompute-balances {--dry-run : csak kiírja a várható változást, nem ír az adatbázisba}';

    protected $description = 'Nullától újraszámolja a jelenléti bejegyzésekből a napi túlóra-deltákat és az '
        .'OvertimeBalance.balance_minutes értékeket a OvertimeBalanceService::segmentMinutesForDay() 2026-08-18-i '
        .'javítása után (a fél órás "műszakkezdés" kerekítés korábban tévesen éjszakába nyúló műszaknak minősített '
        .'rövid, ugyanaznapi szakaszokat, ~24 órás hamis túlórát írva jóvá). A manual_adjustment_minutes kézi '
        .'korrekciót nem érinti.';

    public function handle(OvertimeBalanceService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $employeeIds = TimeEntry::query()
            ->where('type', TimeEntryType::Presence->value)
            ->distinct()
            ->pluck('employee_id');

        $totalOld = 0;
        $totalNew = 0;
        $changedEmployees = 0;

        foreach ($employeeIds as $employeeId) {
            $employee = Employee::find($employeeId);
            if (! $employee) {
                continue;
            }

            $entries = TimeEntry::where('employee_id', $employeeId)
                ->where('type', TimeEntryType::Presence->value)
                ->get();

            $oldSum = (int) $entries->sum('overtime_delta_minutes');
            [$newSum, $assignments] = $this->recomputeForEmployee($service, $employee, $entries);

            $delta = $newSum - $oldSum;
            $totalOld += $oldSum;
            $totalNew += $newSum;

            if ($delta === 0) {
                continue;
            }

            $changedEmployees++;
            $this->line(sprintf(
                '%s: régi=%d perc, új=%d perc, változás=%+d perc',
                $employee->name,
                $oldSum,
                $newSum,
                $delta
            ));

            if (! $dryRun) {
                DB::transaction(function () use ($assignments, $employeeId, $employee, $delta, $service) {
                    foreach ($assignments as $entryId => $newDelta) {
                        TimeEntry::where('id', $entryId)->update(['overtime_delta_minutes' => $newDelta]);
                    }

                    $service->applyDelta($employeeId, $employee->company_id, $delta);
                });
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s Összesen: régi=%d perc, új=%d perc, változás=%+d perc, érintett dolgozók=%d',
            $dryRun ? '[DRY-RUN, nem írt az adatbázisba]' : '[ÉLES, elmentve]',
            $totalOld,
            $totalNew,
            $totalNew - $totalOld,
            $changedEmployees
        ));

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, TimeEntry>  $entries  a dolgozó ÖSSZES presence bejegyzése
     * @return array{0: int, 1: array<int, int>}  [új teljes delta-összeg, entry_id => új overtime_delta_minutes]
     */
    private function recomputeForEmployee(OvertimeBalanceService $service, Employee $employee, Collection $entries): array
    {
        $byDay = $entries->groupBy(fn (TimeEntry $e) => $e->start_date->toDateString());

        $newSum = 0;
        $assignments = [];

        foreach ($byDay as $dayEntries) {
            foreach ($dayEntries as $e) {
                $assignments[$e->id] = 0; // alapértelmezés: nincs elszámolva (felülíródik lent, ha kell)
            }

            // Amíg a nap bármelyik szakasza felülvizsgálatra vár, a teljes napi ledolgozott idő
            // bizonytalan – az observerrel megegyezően itt sem számolunk el (ld. TimeEntryObserver::settlePresence).
            if ($dayEntries->contains(fn (TimeEntry $e) => $e->needs_review)) {
                continue;
            }

            $closed = $dayEntries->filter(
                fn (TimeEntry $e) => $e->end_date && $e->end_time && $e->status === TimeEntryStatus::CheckedOut
            );
            if ($closed->isEmpty()) {
                continue;
            }

            $segmentMinutes = $service->segmentMinutesForDay($dayEntries);
            $totalWorked = array_sum($segmentMinutes);
            $standard = $service->standardMinutesFor($employee);
            $dayDelta = $service->deltaMinutes($totalWorked, $standard);

            // A teljes napi delta a legkésőbb záruló lezárt szakaszra kerül. Ez tisztán bookkeeping:
            // a UI és a riportok (ld. EmployeeOvertimeCard, ShiftPresenceTable) mindig a napi/havi
            // ÖSSZEGET nézik (SUM), sosem az egyes szakasz overtime_delta_minutes értékét önmagában.
            $last = $closed->sortByDesc(fn (TimeEntry $e) => $e->end_date->toDateString().' '.$e->end_time->format('H:i:s'))->first();
            $assignments[$last->id] = $dayDelta;

            $newSum += $dayDelta;
        }

        return [$newSum, $assignments];
    }
}
