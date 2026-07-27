<?php

namespace App\Services;

use App\Enums\TimeEntryType;
use App\Models\Employee;
use App\Models\TimeEntry;
use App\Models\VacationBalance;
use App\Services\Calendar\WorkdayResolver;
use App\Services\Overtime\OvertimeBalanceService;
use Carbon\CarbonImmutable;

class AttendanceSheetService
{
    public function __construct(
        private WorkdayResolver $workdayResolver,
        private OvertimeBalanceService $overtimeService,
    ) {
    }

    /** Egy dolgozó jelenléti ívéhez szükséges adatok egy adott időszakra. */
    public function buildForEmployee(Employee $employee, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        $presenceByDate = TimeEntry::query()
            ->where('employee_id', $employee->id)
            ->where('type', TimeEntryType::Presence->value)
            ->whereBetween('start_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get()
            ->keyBy(fn (TimeEntry $e) => $e->start_date->toDateString());

        $days = [];
        $d = $periodStart;
        while ($d->lte($periodEnd)) {
            $dateStr = $d->toDateString();
            $holidayName = $this->workdayResolver->holidayName($d);
            $entry = $presenceByDate->get($dateStr);

            $note = $holidayName;
            if (! $note && $d->isWeekend() && ! $entry) {
                $note = 'Pihenőnap';
            }

            $regularLabel = null;
            $overtimeLabel = null;
            if ($entry && $entry->start_time && $entry->end_time) {
                $workedMinutes = $this->overtimeService->workedMinutes($entry);
                [$regularMinutes, $overtimeMinutes] = $this->overtimeService->splitMinutes($workedMinutes);
                $regularLabel = $this->formatMinutes($regularMinutes);
                $overtimeLabel = $overtimeMinutes > 0 ? $this->formatMinutes($overtimeMinutes) : null;
            }

            $days[] = [
                'date'          => $d->format('Y-m-d'),
                'dayNumber'     => $d->day,
                'dayName'       => $d->locale('hu')->isoFormat('ddd'),
                'isHoliday'     => $holidayName !== null,
                'isWeekend'     => $d->isWeekend(),
                'note'          => $note,
                'start'         => ($entry?->raw_start_time ?? $entry?->start_time)?->format('H:i'),
                'end'           => $entry?->end_time?->format('H:i'),
                'hoursLabel'    => $regularLabel,
                'overtimeLabel' => $overtimeLabel,
            ];

            $d = $d->addDay();
        }

        $year = (int) $periodStart->year;

        $vb = VacationBalance::where('employee_id', $employee->id)->where('year', $year)->first();

        // hours > 0: csak a ténylegesen ledolgozott túlóra (a negatív = keret terhére elszámolt hiányzás).
        $yearlyOvertime = (float) TimeEntry::where('employee_id', $employee->id)
            ->whereYear('start_date', $year)
            ->where('type', TimeEntryType::Overtime->value)
            ->where('hours', '>', 0)
            ->sum('hours');

        $monthlyOvertime = (float) TimeEntry::where('employee_id', $employee->id)
            ->whereBetween('start_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->where('type', TimeEntryType::Overtime->value)
            ->where('hours', '>', 0)
            ->sum('hours');

        return [
            'employeeName' => $employee->name,
            'companyName'  => $employee->company?->name,
            'periodLabel'  => mb_convert_case($periodStart->locale('hu')->isoFormat('YYYY. MMMM'), MB_CASE_TITLE, 'UTF-8'),
            'days'         => $days,
            'vacation'     => [
                'entitled'  => $vb?->entitled_days ?? 0.0,
                'used'      => $vb?->used_days ?? 0.0,
                'remaining' => $vb?->remaining_days ?? 0.0,
            ],
            'overtime' => [
                'yearly'  => $yearlyOvertime,
                'monthly' => $monthlyOvertime,
            ],
        ];
    }

    /** Percek H:MM formátumban, pl. 510 -> "8:30". */
    private function formatMinutes(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return sprintf('%d:%02d', $h, $m);
    }
}
