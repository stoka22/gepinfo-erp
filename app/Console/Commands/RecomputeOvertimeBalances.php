<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\OvertimeBalance;
use App\Services\Overtime\OvertimeBalanceService;
use Illuminate\Console\Command;

class RecomputeOvertimeBalances extends Command
{
    protected $signature = 'overtime:recompute-balances {--dry-run : csak kiírja a várható változást, nem ír az adatbázisba}';

    protected $description = 'Minden dolgozó OvertimeBalance.balance_minutes értékét NULLÁRÓL, a tényleges '
        .'jelenlét- és túlóra-bejegyzésekből számolja újra (ld. OvertimeBalanceService::recomputeBalance()). '
        .'2026-09-én élesben azonosított hiba javítására: a korábbi, relatív (increment-alapú) '
        .'applyDelta()-mechanizmus kötegelt műveleteknél (tömeges import, tömeges admin-jóváhagyás) '
        .'bizonyítottan elcsúszott -- egy dolgozó egyenlege 173 órával tért el a saját bejegyzéseinek '
        .'tényleges összegétől. A manual_adjustment_minutes kézi korrekciót nem érinti.';

    public function handle(OvertimeBalanceService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $employeeIds = OvertimeBalance::query()->pluck('employee_id')
            ->merge(\App\Models\TimeEntry::query()->distinct()->pluck('employee_id'))
            ->unique()
            ->values();

        $totalOld = 0;
        $totalNew = 0;
        $changedEmployees = 0;

        foreach ($employeeIds as $employeeId) {
            $employee = Employee::find($employeeId);
            if (! $employee) {
                continue;
            }

            $existing = OvertimeBalance::where('employee_id', $employeeId)->first();
            $oldBalance = $existing?->balance_minutes ?? 0;

            if ($dryRun) {
                // Dry-run alatt NEM írunk -- a recomputeBalance() célzottan menti a
                // számított értéket, ezért itt a szolgáltatás belső logikáját tükröző,
                // de írásmentes számítást végzünk ugyanazokból a forrásokból.
                $presenceMinutes = (int) \App\Models\TimeEntry::query()
                    ->where('employee_id', $employeeId)
                    ->where('type', \App\Enums\TimeEntryType::Presence->value)
                    ->sum('overtime_delta_minutes');
                $consumptionMinutes = (int) \App\Models\TimeEntry::query()
                    ->where('employee_id', $employeeId)
                    ->where('type', \App\Enums\TimeEntryType::Overtime->value)
                    ->where('status', \App\Enums\TimeEntryStatus::Approved->value)
                    ->get()
                    ->sum(fn ($e) => (int) round(((float) $e->hours) * 60));
                $newBalance = $presenceMinutes + $consumptionMinutes;
            } else {
                $newBalance = $service->recomputeBalance($employeeId, $employee->company_id)->balance_minutes;
            }

            $delta = $newBalance - $oldBalance;
            $totalOld += $oldBalance;
            $totalNew += $newBalance;

            if ($delta === 0) {
                continue;
            }

            $changedEmployees++;
            $this->line(sprintf(
                '%s: régi=%d perc, új=%d perc, változás=%+d perc (%+.1f óra)',
                $employee->name,
                $oldBalance,
                $newBalance,
                $delta,
                $delta / 60
            ));
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
}
