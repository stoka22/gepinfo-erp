<?php

namespace App\Services;

use App\Enums\TimeEntryStatus;
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

        // Távollét jellegű bejegyzések (szabadság, túlóra terhére, táppénz, igazolatlan),
        // amik a vizsgált időszakot érintik – dátumonként feloldva, hogy minden nap megkapja a jellegét.
        $absenceEntries = TimeEntry::query()
            ->where('employee_id', $employee->id)
            ->where(function ($q) {
                // Szabadság/táppénz/igazolatlan mindig távollét; a "túlóra" típus csak akkor,
                // ha negatív órával a túlóra-keret terhére elszámolt hiányzást jelöl (nem
                // a ténylegesen ledolgozott, pozitív túlóra-jóváírást).
                $q->whereIn('type', [
                    TimeEntryType::Vacation->value,
                    TimeEntryType::SickLeave->value,
                    TimeEntryType::UnauthorizedAbsence->value,
                ])->orWhere(function ($q2) {
                    $q2->where('type', TimeEntryType::Overtime->value)->where('hours', '<', 0);
                });
            })
            ->where(function ($q) use ($periodStart, $periodEnd) {
                $q->where('start_date', '<=', $periodEnd->toDateString())
                    ->where(function ($q2) use ($periodStart) {
                        $q2->where('end_date', '>=', $periodStart->toDateString())
                            ->orWhereNull('end_date');
                    });
            })
            ->get();

        $absenceByDate = [];
        foreach ($absenceEntries as $absence) {
            $fromStr = $absence->start_date->toDateString();
            $toStr = ($absence->end_date ?? $absence->start_date)->toDateString();
            $cursor = CarbonImmutable::parse(max($fromStr, $periodStart->toDateString()));
            $last = CarbonImmutable::parse(min($toStr, $periodEnd->toDateString()));
            while ($cursor->lte($last)) {
                $absenceByDate[$cursor->toDateString()] = [
                    'label'      => $this->absenceLabel($absence),
                    'isModified' => (bool) $absence->is_modified,
                ];
                $cursor = $cursor->addDay();
            }
        }

        $days = [];
        $monthlyWorkedMinutes = 0;
        $d = $periodStart;
        while ($d->lte($periodEnd)) {
            $dateStr = $d->toDateString();
            $holidayName = $this->workdayResolver->holidayName($d);
            $entry = $presenceByDate->get($dateStr);
            $absence = $absenceByDate[$dateStr] ?? null;

            $note = $holidayName ?? $absence['label'] ?? null;
            if (! $note && $d->isWeekend() && ! $entry) {
                $note = 'Pihenőnap';
            }

            $isModified = (bool) $entry?->is_modified || (bool) ($absence['isModified'] ?? false);

            $regularLabel = null;
            $overtimeLabel = null;
            if ($entry && $entry->start_date && $entry->start_time && $entry->end_date && $entry->end_time) {
                $workedMinutes = $this->overtimeService->workedMinutes($entry);
                [$regularMinutes] = $this->overtimeService->splitMinutes($workedMinutes);
                $regularLabel = $this->formatMinutes($regularMinutes);
                // Előjeles eltérés a 8:30-tól: 8:30 alatt negatív – ez a túlóra-keret
                // terhére megy (ld. TimeEntryObserver / OvertimeBalanceService::applyDelta).
                $overtimeLabel = $this->formatMinutes($this->overtimeService->deltaMinutes($workedMinutes));
                $monthlyWorkedMinutes += $workedMinutes;
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
                'isModified'    => $isModified,
            ];

            $d = $d->addDay();
        }

        $year = (int) $periodStart->year;

        $vb = VacationBalance::where('employee_id', $employee->id)->where('year', $year)->first();

        // A jelenléti sorok "Túlóra" oszlopával összhangban: a jelenlét alapján automatikusan
        // elszámolt overtime_delta_minutes nettó összege – a 8:30 alatti (negatív) napok is
        // beleszámítanak, nem csak a ténylegesen túlórázott napok.
        $yearlyOvertimeMinutes = (int) TimeEntry::where('employee_id', $employee->id)
            ->where('type', TimeEntryType::Presence->value)
            ->whereYear('start_date', $year)
            ->whereNotNull('overtime_delta_minutes')
            ->sum('overtime_delta_minutes');

        $monthlyOvertimeMinutes = (int) TimeEntry::where('employee_id', $employee->id)
            ->where('type', TimeEntryType::Presence->value)
            ->whereBetween('start_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->whereNotNull('overtime_delta_minutes')
            ->sum('overtime_delta_minutes');

        $yearlyWorkedMinutes = (int) TimeEntry::where('employee_id', $employee->id)
            ->where('type', TimeEntryType::Presence->value)
            ->whereYear('start_date', $year)
            ->whereNotNull('end_time')
            ->selectRaw("COALESCE(SUM(TIMESTAMPDIFF(MINUTE, CONCAT(start_date,' ',start_time), CONCAT(COALESCE(end_date,start_date),' ',end_time))),0) as m")
            ->value('m');

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
                'yearly'  => $this->formatMinutes($yearlyOvertimeMinutes),
                'monthly' => $this->formatMinutes($monthlyOvertimeMinutes),
            ],
            'workedHours' => [
                'yearly'  => $this->formatMinutes($yearlyWorkedMinutes),
                'monthly' => $this->formatMinutes($monthlyWorkedMinutes),
            ],
        ];
    }

    /** Egy távollét jellegű bejegyzés magyar megnevezése a jelenléti íven, a jóváhagyás állapotával. */
    private function absenceLabel(TimeEntry $absence): string
    {
        $type = $absence->type instanceof TimeEntryType ? $absence->type->value : (string) $absence->type;

        $label = match ($type) {
            TimeEntryType::Vacation->value => 'Szabadság',
            TimeEntryType::SickLeave->value => 'Táppénz',
            TimeEntryType::UnauthorizedAbsence->value => 'Igazolatlan távollét',
            TimeEntryType::Overtime->value => 'Túlóra terhére',
            default => 'Távollét',
        };

        $status = $absence->status instanceof TimeEntryStatus ? $absence->status->value : (string) $absence->status;
        if ($status === TimeEntryStatus::Pending->value) {
            $label .= ' (jóváhagyásra vár)';
        }

        return $label;
    }

    /** Percek előjeles H:MM formátumban, pl. 510 -> "8:30", -45 -> "-0:45". */
    private function formatMinutes(int $minutes): string
    {
        $sign = $minutes < 0 ? '-' : '';
        $abs = abs($minutes);
        $h = intdiv($abs, 60);
        $m = $abs % 60;
        return sprintf('%s%d:%02d', $sign, $h, $m);
    }
}
