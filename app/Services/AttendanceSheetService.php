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
        // groupBy (nem keyBy!): egy napon belül több be-/kilépés is előfordulhat (pl. ebédszünet) –
        // ha csak egyet tartanánk meg naponta, a többi szakasz csendben eltűnne az ívről és a
        // ledolgozott idő/túlóra számításából.
        $presenceByDate = TimeEntry::query()
            ->where('employee_id', $employee->id)
            ->where('type', TimeEntryType::Presence->value)
            ->whereBetween('start_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->orderBy('start_time')
            ->get()
            ->groupBy(fn (TimeEntry $e) => $e->start_date->toDateString());

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
        $monthlyOvertimeMinutes = 0;
        $d = $periodStart;
        while ($d->lte($periodEnd)) {
            $dateStr = $d->toDateString();
            $holidayName = $this->workdayResolver->holidayName($d);
            /** @var \Illuminate\Support\Collection<int, TimeEntry> $entriesToday */
            $entriesToday = $presenceByDate->get($dateStr, collect());
            $absence = $absenceByDate[$dateStr] ?? null;

            $note = $holidayName ?? $absence['label'] ?? null;
            if (! $note && $d->isWeekend() && $entriesToday->isEmpty()) {
                $note = 'Pihenőnap';
            }

            $isModified = $entriesToday->contains(fn (TimeEntry $e) => (bool) $e->is_modified)
                || (bool) ($absence['isModified'] ?? false);

            // Napi szintű összesítés: a 8:30-as szabályt a NAP ÖSSZES szakaszának együttes
            // ledolgozott idejére alkalmazzuk (ld. OvertimeBalanceService::totalWorkedMinutesForDay),
            // nem szakaszonként külön-külön – ugyanaz a logika, mint a TimeEntryObserver-ben.
            $regularLabel = null;
            $overtimeLabel = null;
            $completeEntries = $entriesToday->filter(
                fn (TimeEntry $e) => $e->start_date && $e->start_time && $e->end_date && $e->end_time
            );
            if ($completeEntries->isNotEmpty()) {
                $workedMinutes = $this->overtimeService->totalWorkedMinutesForDay($completeEntries);
                [$regularMinutes] = $this->overtimeService->splitMinutes($workedMinutes);
                $regularLabel = $this->formatMinutes($regularMinutes);
                // Előjeles eltérés a 8:30-tól: 8:30 alatt negatív – ez a túlóra-keret
                // terhére megy (ld. TimeEntryObserver / OvertimeBalanceService::applyDelta).
                $dayDelta = $this->overtimeService->deltaMinutes($workedMinutes);
                $overtimeLabel = $this->formatMinutes($dayDelta);
                $monthlyWorkedMinutes += $workedMinutes;
                $monthlyOvertimeMinutes += $dayDelta;
            }

            // Napi több be-/kilépés esetén a legkorábbi érkezés és a legkésőbbi távozás jelenik meg
            // (megegyezik azzal, ahogy a "ma" nézet is összegzi a kiosk-widgeten).
            $firstEntry = $completeEntries->isNotEmpty() ? $completeEntries->first() : $entriesToday->first();
            $lastEntry = $completeEntries->isNotEmpty() ? $completeEntries->last() : $entriesToday->last();

            // Minden egyes szakasz külön is (a "másodlagos", részletes jelenléti ívhez) — a
            // fenti napi összevonás (első be-/utolsó kilépés) mellett, hogy egy nap többszöri
            // be-/kilépése (pl. ebédszünet) is látszódjon soronként, ne csak összesítve.
            $segments = $entriesToday->map(function (TimeEntry $e) {
                $start = $e->raw_start_time ?? $e->start_time;
                $end = $e->end_time;
                $minutes = null;
                if ($start && $end && $e->start_date && $e->end_date) {
                    $startDt = CarbonImmutable::parse($e->start_date->toDateString().' '.$start->format('H:i:s'));
                    $endDt = CarbonImmutable::parse($e->end_date->toDateString().' '.$end->format('H:i:s'));
                    $minutes = $startDt->diffInMinutes($endDt);
                }
                return [
                    'start'      => $start?->format('H:i'),
                    'end'        => $end?->format('H:i'),
                    'hoursLabel' => $minutes !== null ? $this->formatMinutes($minutes) : null,
                    'isModified' => (bool) $e->is_modified,
                    'location'   => $e->location,
                ];
            })->values()->all();

            $days[] = [
                'date'          => $d->format('Y-m-d'),
                'dayNumber'     => $d->day,
                'dayName'       => $d->locale('hu')->isoFormat('ddd'),
                'isHoliday'     => $holidayName !== null,
                'isWeekend'     => $d->isWeekend(),
                'note'          => $note,
                'start'         => ($firstEntry?->raw_start_time ?? $firstEntry?->start_time)?->format('H:i'),
                'end'           => $lastEntry?->end_time?->format('H:i'),
                'hoursLabel'    => $regularLabel,
                'overtimeLabel' => $overtimeLabel,
                'isModified'    => $isModified,
                'segments'      => $segments,
            ];

            $d = $d->addDay();
        }

        $year = (int) $periodStart->year;

        $vb = VacationBalance::where('employee_id', $employee->id)->where('year', $year)->first();

        // Élőben újraszámolva (nem a tárolt overtime_delta_minutes összege!), mert az csak akkor
        // kerül kitöltésre, ha a bejegyzés ténylegesen átment a TimeEntryObserver elszámolásán
        // (needs_review=false, checked_out) – a régebbi, importált adatoknál ez sokszor sosem
        // történt meg, így a tárolt oszlop összege hamisan mindig 0/változatlan lenne.
        // Így a fejléc mindig pontosan megegyezik a napi sorok "Túlóra" oszlopának összegével.
        // Napi szintű csoportosítás itt is szükséges (ld. fenti napi ciklus): a 8:30-as
        // szabályt naponta egyszer, a nap összes szakaszának együttes idejére alkalmazzuk.
        $yearlyOvertimeMinutes = (int) TimeEntry::where('employee_id', $employee->id)
            ->where('type', TimeEntryType::Presence->value)
            ->whereYear('start_date', $year)
            ->whereNotNull('start_time')
            ->whereNotNull('end_date')
            ->whereNotNull('end_time')
            ->get()
            ->groupBy(fn (TimeEntry $e) => $e->start_date->toDateString())
            ->sum(fn ($entriesForDay) => $this->overtimeService->deltaMinutes(
                $this->overtimeService->totalWorkedMinutesForDay($entriesForDay)
            ));

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
