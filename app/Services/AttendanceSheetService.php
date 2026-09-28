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

    /** Szabadság napon a "rendes" oszlopba mindig ennyi (8 óra) kerül a havi/éves összesítőbe. */
    private const VACATION_CREDIT_MINUTES = 480;

    /** Egy dolgozó jelenléti ívéhez szükséges adatok egy adott időszakra. */
    public function buildForEmployee(Employee $employee, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        $year = (int) $periodStart->year;
        $yearStart = CarbonImmutable::create($year, 1, 1)->startOfYear();
        $yearEnd = CarbonImmutable::create($year, 12, 31)->endOfYear();

        // A TELJES évre lekérve (nem csak a kért időszakra) — az éves fejléc-összesítőnek
        // (workedHours.yearly / overtime.yearly) ugyanazt a napi (szabadság-tudatos) logikát
        // kell alkalmaznia, mint a havi nézetnek, különben a kettő elszakadna egymástól.
        // groupBy (nem keyBy!): egy napon belül több be-/kilépés is előfordulhat (pl. ebédszünet) –
        // ha csak egyet tartanánk meg naponta, a többi szakasz csendben eltűnne az ívről és a
        // ledolgozott idő/túlóra számításából.
        $presenceByDate = TimeEntry::query()
            ->where('employee_id', $employee->id)
            ->where('type', TimeEntryType::Presence->value)
            ->whereBetween('start_date', [$yearStart->toDateString(), $yearEnd->toDateString()])
            ->orderBy('start_time')
            ->get()
            ->groupBy(fn (TimeEntry $e) => $e->start_date->toDateString());

        // Távollét jellegű bejegyzések (szabadság, túlóra terhére, táppénz, igazolatlan),
        // amik az évet érintik – dátumonként feloldva, hogy minden nap megkapja a jellegét.
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
            ->where(function ($q) use ($yearStart, $yearEnd) {
                $q->where('start_date', '<=', $yearEnd->toDateString())
                    ->where(function ($q2) use ($yearStart) {
                        $q2->where('end_date', '>=', $yearStart->toDateString())
                            ->orWhereNull('end_date');
                    });
            })
            ->get();

        $absenceByDate = [];
        foreach ($absenceEntries as $absence) {
            $fromStr = $absence->start_date->toDateString();
            $toStr = ($absence->end_date ?? $absence->start_date)->toDateString();
            $cursor = CarbonImmutable::parse(max($fromStr, $yearStart->toDateString()));
            $last = CarbonImmutable::parse(min($toStr, $yearEnd->toDateString()));
            $type = $absence->type instanceof TimeEntryType ? $absence->type->value : (string) $absence->type;
            while ($cursor->lte($last)) {
                $absenceByDate[$cursor->toDateString()] = [
                    'label'      => $this->absenceLabel($absence),
                    'type'       => $type,
                    'isModified' => (bool) $absence->is_modified,
                ];
                $cursor = $cursor->addDay();
            }
        }

        // A dolgozó saját napi túlóra-küszöbe (napi kötelező munkaidő + 30 perc puffer) —
        // ld. OvertimeBalanceService::standardMinutesFor(). Egy híváson belül nem változik.
        $standardMinutes = $this->overtimeService->standardMinutesFor($employee);

        $days = [];
        $monthlyWorkedMinutes = 0;
        $monthlyOvertimeMinutes = 0;
        $yearlyWorkedMinutes = 0;
        $yearlyOvertimeMinutes = 0;

        // FONTOS: a napi ciklus a periodEnd-ig fut, NEM a naptári év végéig (yearEnd) --
        // korábban itt $yearEnd állt, ami azt jelentette, hogy a "yearly" összesítők
        // (lásd lent) MINDIG a teljes naptári évet összegezték, függetlenül attól, melyik
        // hónap ívét generáltuk. Emiatt pl. az augusztusi és a szeptemberi ív ugyanazt az
        // "éves" túlórát/ledolgozott időt mutatta (a riport készítésének pillanatában
        // ténylegesen létező adatok alapján, a jövőbeli hónapokat is beleértve, amik persze
        // még 0-t adtak) -- a felhasználó ezt joggal "mindig állandó" értéknek látta. A
        // helyes viselkedés: az éves összesítő a naptári év ELEJÉTŐL a RIPORT HÓNAPJÁNAK
        // VÉGÉIG (periodEnd) göngyölve, hónapról hónapra ténylegesen változva.
        $d = $yearStart;
        while ($d->lte($periodEnd)) {
            $dateStr = $d->toDateString();
            $inRequestedPeriod = $d->gte($periodStart) && $d->lte($periodEnd);

            /** @var \Illuminate\Support\Collection<int, TimeEntry> $entriesToday */
            $entriesToday = $presenceByDate->get($dateStr, collect());
            $absence = $absenceByDate[$dateStr] ?? null;
            $isVacationDay = ($absence['type'] ?? null) === TimeEntryType::Vacation->value;

            // Napi szintű összesítés: a küszöböt a NAP ÖSSZES szakaszának együttes ledolgozott
            // idejére alkalmazzuk (ld. OvertimeBalanceService::totalWorkedMinutesForDay), nem
            // szakaszonként külön-külön – ugyanaz a logika, mint a TimeEntryObserver-ben.
            $completeEntries = $entriesToday->filter(
                fn (TimeEntry $e) => $e->start_date && $e->start_time && $e->end_date && $e->end_time
            );

            $regularMinutes = null;
            $overtimeMinutes = null;
            // A "ledolgozott" havi/éves összesítőbe kerülő percek — normál napon a nyers
            // ledolgozott idő (NEM a regularMinutes+overtimeMinutes, mert a deltaMinutes()
            // tolerancia-sávja miatt ez néhány percet tévesen elnyelne), szabadság napon a
            // fix 8 óra + az esetleges tényleges jelenlét.
            $dayWorkedMinutes = null;

            if ($isVacationDay) {
                // Szabadság napon a szabadság az "erősebb": nem keletkezhet negatív túlóra
                // (hiányos munkaidő) amiatt, hogy a dolgozó a napi kötelező munkaidőnél
                // kevesebbet volt jelen — hiszen aznap eleve nem volt köteles dolgozni. Az
                // esetlegesen mégis rögzített jelenlét a TELJES egészében túlórának számít
                // (nincs küszöb/tolerancia-levonás), a "rendes" oszlopba pedig a szabadság
                // fix 8 órája kerül.
                $presenceMinutes = $completeEntries->isNotEmpty()
                    ? $this->overtimeService->totalWorkedMinutesForDay($entriesToday)
                    : 0;
                $regularMinutes = self::VACATION_CREDIT_MINUTES;
                $overtimeMinutes = $presenceMinutes;
                $dayWorkedMinutes = self::VACATION_CREDIT_MINUTES + $presenceMinutes;
            } elseif ($completeEntries->isNotEmpty()) {
                $workedMinutes = $this->overtimeService->totalWorkedMinutesForDay($entriesToday);
                [$regularMinutes] = $this->overtimeService->splitMinutes($workedMinutes, $standardMinutes);
                // Előjeles eltérés a küszöbtől: alatta negatív – ez a túlóra-keret
                // terhére megy (ld. TimeEntryObserver / OvertimeBalanceService::applyDelta).
                $overtimeMinutes = $this->overtimeService->deltaMinutes($workedMinutes, $standardMinutes);

                // A "ledolgozott" havi/éves összesítőbe a napi 30 perces szünet NEM számít bele
                // (az a küszöbnek csak a TÚLÓRA-HATÁR eltolására szolgáló, nem ledolgozott
                // munkaidő) — de csak akkor vonjuk le, ha a dolgozó legalább a napi kötelező
                // munkaidőt (kvóta, a puffer NÉLKÜL) ledolgozta aznap; egy rövidebb, félnapos
                // jelenlétnél nincs feltételezett ebédszünet. Csak a "Rendes"/"Túlóra" oszlopok
                // (regularMinutes/overtimeMinutes) számítását és a túlóra-küszöböt NEM érinti —
                // azok változatlanul a kvóta+puffer (standardMinutes) ellen mérnek.
                $quotaMinutes = $standardMinutes - \App\Services\Overtime\OvertimeBalanceService::STANDARD_BUFFER_MINUTES;
                $dayWorkedMinutes = $workedMinutes >= $quotaMinutes
                    ? $workedMinutes - \App\Services\Overtime\OvertimeBalanceService::STANDARD_BUFFER_MINUTES
                    : $workedMinutes;
            }

            if ($dayWorkedMinutes !== null) {
                $yearlyWorkedMinutes += $dayWorkedMinutes;
                $yearlyOvertimeMinutes += $overtimeMinutes;

                if ($inRequestedPeriod) {
                    $monthlyWorkedMinutes += $dayWorkedMinutes;
                    $monthlyOvertimeMinutes += $overtimeMinutes;
                }
            }

            if ($inRequestedPeriod) {
                $holidayName = $this->workdayResolver->holidayName($d);

                $note = $holidayName ?? $absence['label'] ?? null;
                if (! $note && $d->isWeekend() && $entriesToday->isEmpty()) {
                    $note = 'Pihenőnap';
                }
                // Az adott napon hiányosan ledolgozott (a küszöb alatti) munkaidő soronkénti
                // jelölése -- a havi összesítő "Csúszó" sortól (ld. lent, monthlyMinutes < 0)
                // FÜGGETLENÜL, hiszen egy hónapon belül a napi hiányok és többletek
                // kiegyenlíthetik egymást a havi nettó egyenlegben, miközben az EGYES napok
                // hiánya a jelenléti íven soronként is látszania kell.
                if (! $note && $overtimeMinutes !== null && $overtimeMinutes < 0) {
                    $note = 'Csúszó';
                }

                $isModified = $entriesToday->contains(fn (TimeEntry $e) => (bool) $e->is_modified)
                    || (bool) ($absence['isModified'] ?? false);

                // Napi több be-/kilépés esetén a legkorábbi érkezés és a legkésőbbi távozás jelenik meg
                // (megegyezik azzal, ahogy a "ma" nézet is összegzi a kiosk-widgeten) — a KIJELZETT
                // érkezés mindig a nyers, kerekítés nélküli idő (ld. lent, effectiveStartLabel); a
                // ledolgozott óra/túlóra SZÁMÍTÁSA viszont a nap első szakaszánál fél órára kerekített
                // "műszakkezdéstől" indul (ld. OvertimeBalanceService::segmentMinutesForDay) — a kettő
                // szándékosan eltérhet.
                //
                // FONTOS: a "legkésőbbi távozás" kiválasztása a TÉNYLEGES kilépési időpont szerint kell,
                // NEM a $entriesToday SQL-lekérdezés `orderBy('start_time')` sorrendje szerinti utolsó
                // elem — az csak akkor esik egybe a legkésőbbi kilépéssel, ha a nap szakaszai a
                // kezdésük szerint is növekvő sorrendben végződnek. Egy több-olvasós telephely (pl.
                // csarnok/raktár közti kapu-áthaladások) granulátumban exportált napján ez NEM igaz:
                // élesben azonosítva (Égi Ferenc, 2026-08-27), a régi kód egy 14:00-ra kerekített
                // kezdésű, de 14:05-kor záruló RÖVID töredék-szakaszt választott "utolsónak", miközben
                // a valódi (8:30-ás, 14:30-kor záruló) teljes műszak korábban kezdődött — a kijelzett
                // "Vége" (14:05) emiatt ellentmondott a mellette számolt "Ledolgozott" (8:30) oszlopnak.
                $lastEntry = $completeEntries->isNotEmpty()
                    ? $completeEntries->sortBy(fn (TimeEntry $e) => $e->end_date->toDateString().' '.$e->end_time->format('H:i:s'))->last()
                    : $entriesToday->last();

                // Minden egyes szakasz külön is (a "másodlagos", részletes jelenléti ívhez) — a
                // fenti napi összevonás (első be-/utolsó kilépés) mellett, hogy egy nap többszöri
                // be-/kilépése (pl. ebédszünet) is látszódjon soronként, ne csak összesítve. A
                // kijelzett kezdés nyers (ld. effectiveStartLabel), a hoursLabel viszont a fél órára
                // kerekített műszakkezdésből számolt ledolgozott időt tükrözi (ld.
                // OvertimeBalanceService::segmentMinutesForDay).
                $sortedToday = $this->overtimeService->sortEntriesForDay($entriesToday);
                $segmentMinuteMap = $this->overtimeService->segmentMinutesForDay($entriesToday);
                $segments = $sortedToday->map(function (TimeEntry $e, int $i) use ($segmentMinuteMap) {
                    $minutes = $segmentMinuteMap[spl_object_id($e)] ?? null;
                    return [
                        'start'      => $this->overtimeService->effectiveStartLabel($e, $i === 0),
                        'end'        => $e->end_time?->format('H:i'),
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
                    'start'         => $sortedToday->isNotEmpty() ? $this->overtimeService->effectiveStartLabel($sortedToday->first(), true) : null,
                    'end'           => $lastEntry?->end_time?->format('H:i'),
                    'hoursLabel'    => $regularMinutes !== null ? $this->formatMinutes($regularMinutes) : null,
                    'overtimeLabel' => $overtimeMinutes !== null ? $this->formatMinutes($overtimeMinutes) : null,
                    'isModified'    => $isModified,
                    'segments'      => $segments,
                ];
            }

            $d = $d->addDay();
        }

        $vb = VacationBalance::where('employee_id', $employee->id)->where('year', $year)->first();
        $entitledDays = $vb?->entitled_days ?? 0.0;
        // A "Felhasznált" a RIPORT HÓNAPJÁNAK VÉGÉIG (periodEnd) göngyölve -- NEM a
        // VacationBalance::used_days teljes éves accessor, ami a naptári év egészét
        // (a jövőbeli hónapokat is beleértve) összesíti, és emiatt minden hónap ívén
        // ugyanazt az (év végi) állandó értéket mutatná. Lásd az analóg éves túlóra
        // javítást feljebb ($periodEnd cap a napi ciklusban).
        $usedDaysAsOfPeriod = $this->usedVacationDaysInRange($employee->id, $yearStart, $periodEnd);
        // Külön, a RIPORT HÓNAPJÁRA szűkített felhasználás -- a fenti (év eleje óta göngyölt)
        // "used" mellett, mert önmagában a göngyölt szám nem egyértelmű (nem világos belőle,
        // mennyi esik pont erre a hónapra) -- ld. a felhasználói visszajelzést.
        $usedDaysThisPeriod = $this->usedVacationDaysInRange($employee->id, $periodStart, $periodEnd);

        return [
            'employeeName' => $employee->name,
            'companyName'  => $employee->company?->name,
            'periodLabel'  => mb_convert_case($periodStart->locale('hu')->isoFormat('YYYY. MMMM'), MB_CASE_TITLE, 'UTF-8'),
            'days'         => $days,
            'vacation'     => [
                'entitled'    => $entitledDays,
                'used'        => $usedDaysAsOfPeriod,
                'monthlyUsed' => $usedDaysThisPeriod,
                'remaining'   => max(0.0, $entitledDays - $usedDaysAsOfPeriod),
            ],
            'overtime' => [
                'yearly'  => $this->formatMinutes($yearlyOvertimeMinutes),
                'monthly' => $this->formatMinutes($monthlyOvertimeMinutes),
                // Nyers (nem formázott) havi túlóra-eltérés -- a Blade sablon ez alapján
                // dönti el, kell-e "Csúszó" (hiány) sor az ívre. Csak akkor negatív, ha a
                // dolgozó a hónap egészében NETTÓ kevesebbet dolgozott a kötelezőnél.
                'monthlyMinutes' => $monthlyOvertimeMinutes,
            ],
            'workedHours' => [
                'yearly'  => $this->formatMinutes($yearlyWorkedMinutes),
                'monthly' => $this->formatMinutes($monthlyWorkedMinutes),
            ],
        ];
    }

    /**
     * Felhasznált szabadságnapok a $rangeStart..$rangeEnd tartományra levágva -- a
     * TimeEntry::countBusinessDaysForEntry()-vel azonos (hétvége-kizáró) munkanap-számolás,
     * de egy a tartományon túlnyúló szabadság-bejegyzésnél MINDKÉT végén levágva, hogy se a
     * tartomány előtti, se az utáni napok ne számítsanak bele. Ugyanez a metódus szolgálja
     * az "év eleje óta göngyölt" (rangeStart=évkezdet) és a "csak ebben a hónapban" (rangeStart
     * =periodStart) felhasználást is -- ld. a hívási helyeken.
     */
    private function usedVacationDaysInRange(int $employeeId, CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd): float
    {
        return (float) TimeEntry::query()
            ->where('employee_id', $employeeId)
            ->where('type', TimeEntryType::Vacation->value)
            ->where('status', TimeEntryStatus::Approved->value)
            ->where('start_date', '<=', $rangeEnd->toDateString())
            ->where(function ($q) use ($rangeStart) {
                $q->where('end_date', '>=', $rangeStart->toDateString())->orWhereNull('end_date');
            })
            ->get()
            ->sum(function (TimeEntry $entry) use ($rangeStart, $rangeEnd) {
                if (! empty($entry->hours)) {
                    return 0.0;
                }

                $start = CarbonImmutable::parse($entry->start_date);
                $end = CarbonImmutable::parse($entry->end_date ?? $entry->start_date);
                if ($start->lt($rangeStart)) {
                    $start = $rangeStart;
                }
                if ($end->gt($rangeEnd)) {
                    $end = $rangeEnd;
                }
                if ($end->lt($start)) {
                    return 0.0;
                }

                $days = 0;
                $d = $start;
                while ($d->lte($end)) {
                    if (! $d->isWeekend()) {
                        $days++;
                    }
                    $d = $d->addDay();
                }

                return $days;
            });
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
