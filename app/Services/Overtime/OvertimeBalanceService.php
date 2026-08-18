<?php

namespace App\Services\Overtime;

use App\Models\Employee;
use App\Models\OvertimeBalance;
use App\Models\TimeEntry;
use App\Support\TimeRounding;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OvertimeBalanceService
{
    /** Standard munkanap hossza percben: 8 óra 30 perc — alapérték, ha a dolgozóhoz nincs kvóta. */
    public const STANDARD_WORKDAY_MINUTES = 510;

    /** Alapértelmezett napi kötelező munkaidő percben (8 óra), ha a dolgozóhoz nincs beállítva. */
    public const DEFAULT_DAILY_QUOTA_MINUTES = 480;

    /** A dolgozó napi kvótája fölötti "engedélyezett" ráadás percben, ami még nem túlóra (a küszöb része). */
    public const STANDARD_BUFFER_MINUTES = 30;

    /** Kijelentkezési hibahatár percben: ennyi korábbi távozás még nem számít hiánynak. */
    public const EARLY_DEPARTURE_TOLERANCE_MINUTES = 2;

    /** Túlóra hibahatár percben: a küszöb utáni idő csak eddig a percig türelmi idő, e fölött a teljes eltérés túlórának számít. */
    public const OVERTIME_TOLERANCE_MINUTES = 10;

    /**
     * A dolgozó napi túlóra-küszöbe percben: napi kötelező munkaidő + 30 perc puffer.
     * Pl. 6 órára kötelezett dolgozónál a küszöb 6:30 — ebből a deltaMinutes() 10 perc
     * türelmi idővel (azaz 6:40-től) számol tényleges túlórát, visszamenőlegesen 6:30-tól.
     */
    public function standardMinutesFor(?Employee $employee): int
    {
        $quotaMinutes = $employee?->daily_quota_hours !== null
            ? (int) round(((float) $employee->daily_quota_hours) * 60)
            : self::DEFAULT_DAILY_QUOTA_MINUTES;

        return $quotaMinutes + self::STANDARD_BUFFER_MINUTES;
    }

    /** A jelenléti bejegyzés ledolgozott ideje percben, éjszakába nyúlást kezelve. */
    public function workedMinutes(TimeEntry $entry): int
    {
        $start = Carbon::parse($entry->start_date->toDateString() . ' ' . $entry->start_time->format('H:i:s'));
        $end = Carbon::parse($entry->end_date->toDateString() . ' ' . $entry->end_time->format('H:i:s'));

        if ($end->lessThan($start)) {
            $end = $end->copy()->addDay();
        }

        return max(0, $start->diffInMinutes($end));
    }

    /**
     * A napi eltérés a standard munkaidőtől (pozitív = túlóra, negatív = hiány).
     * Hibahatárok (küszöbök, nem levonások): a hibahatáron belüli eltérés 0-nak számít,
     * afölött viszont a teljes (küszöbtől számított) eltérés túlóra/hiány, visszamenőlegesen.
     * - Korai távozás: EARLY_DEPARTURE_TOLERANCE_MINUTES percig nem számít hiánynak.
     * - Túlóra: OVERTIME_TOLERANCE_MINUTES percig nem számít túlórának.
     *
     * @param  int|null  $standardMinutes  a dolgozó saját küszöbe (ld. standardMinutesFor());
     *                                     ha nincs megadva, az alapértelmezett 8:30-as küszöböt használja.
     */
    public function deltaMinutes(int $workedMinutes, ?int $standardMinutes = null): int
    {
        $standard = $standardMinutes ?? self::STANDARD_WORKDAY_MINUTES;
        $delta = $workedMinutes - $standard;

        if ($delta < 0 && $delta >= -self::EARLY_DEPARTURE_TOLERANCE_MINUTES) {
            return 0;
        }

        if ($delta > 0 && $delta <= self::OVERTIME_TOLERANCE_MINUTES) {
            return 0;
        }

        return $delta;
    }

    /**
     * Egy adott nap Presence-szakaszai időrendi sorrendben (a raw/tényleges kezdést nézve).
     * A sorrend határozza meg, melyik szakasz számít a nap "elsőnek" (ld. effectiveStartLabel).
     *
     * @param  Collection<int, TimeEntry>  $entriesForDay  a nap ÖSSZES (nyitott is) Presence szakasza
     * @return Collection<int, TimeEntry>
     */
    public function sortEntriesForDay(Collection $entriesForDay): Collection
    {
        return $entriesForDay
            ->filter(fn (TimeEntry $e) => $e->start_date && $e->start_time)
            ->sortBy(fn (TimeEntry $e) => ($e->raw_start_time ?? $e->start_time)->format('H:i:s'))
            ->values();
    }

    /**
     * A jelenléti íven MEGJELENÍTENDŐ kezdési idő "H:i" alakban — mindig a nyers,
     * tényleges bejelentkezés, kerekítés nélkül. FONTOS: ez a "kijelzett" idő, NEM a
     * túlóra-elszámoláshoz használt "műszakkezdés" — azt ld. segmentMinutesForDay(),
     * ami a nap első szakaszánál külön, csak a számításhoz kerekít.
     */
    public function effectiveStartLabel(TimeEntry $entry, bool $isFirstOfDay): string
    {
        $rawStart = $entry->raw_start_time ?? $entry->start_time;

        return $rawStart->format('H:i');
    }

    /**
     * Egy nap összes lezárt (be- és kilépéssel is rendelkező) jelenlét-szakaszának ledolgozott
     * ideje percben, SZAKASZONKÉNT — a NAP ELSŐ szakaszánál a "műszakkezdés" fél órára
     * felfelé kerekítve (pl. 05:37 -> 06:00; a kijelentkezés innentől számítva kvóta+30
     * perc szünet+10 perc türelmi idő után minősül túlórának), minden további aznapi
     * szakasznál (ebéd utáni visszatérés stb.) és minden kilépésnél percre pontosan,
     * kerekítés nélkül. Forrástól függetlenül érvényes (kioszk, import, kézi rögzítés
     * egyaránt) — ez KIZÁRÓLAG a számításra vonatkozik, a kijelzett érkezési időt (ld.
     * effectiveStartLabel()) nem érinti.
     *
     * @param  Collection<int, TimeEntry>  $entriesForDay  a nap ÖSSZES (nyitott is) Presence szakasza –
     *                                                       a nyitottak is kellenek a helyes sorrendhez,
     *                                                       még ha nincs is ledolgozott percük.
     * @return array<int, int>  spl_object_id($entry) => ledolgozott percek
     */
    public function segmentMinutesForDay(Collection $entriesForDay): array
    {
        $sorted = $this->sortEntriesForDay($entriesForDay);

        $result = [];
        foreach ($sorted as $i => $entry) {
            if (! $entry->end_date || ! $entry->end_time) {
                continue; // nyitott szakasz: számít a sorrendbe, de nincs ledolgozott ideje
            }

            $rawStartHm = ($entry->raw_start_time ?? $entry->start_time)->format('H:i');
            $rawStart = Carbon::parse($entry->start_date->toDateString() . ' ' . $rawStartHm . ':00');
            $end = Carbon::parse($entry->end_date->toDateString() . ' ' . $entry->end_time->format('H:i:s'));

            // Az éjszakába nyúlást a NYERS (kerekítetlen) kezdéssel dőntjük el, MIELŐTT bármit
            // kerekítenénk – különben egy rövid, aznapi szakasznál a lentebb felkerekített
            // "műszakkezdés" tévesen a kilépés UTÁNRA eshet, és ez az ág hamisan majdnem egy
            // teljes napot (~24 órát) adna hozzá a ledolgozott időhöz.
            if ($end->lessThan($rawStart)) {
                $end = $end->copy()->addDay();
            }

            $startHm = $i === 0 ? TimeRounding::roundStartUpToHalfHour($rawStartHm) : $rawStartHm;
            $start = Carbon::parse($entry->start_date->toDateString() . ' ' . $startHm . ':00');
            if ($start->lessThan($rawStart)) {
                // A fél órás kerekítés éjfélen túlra csúszott (pl. 23:45 -> 00:00) – a kerekített
                // kezdés emiatt valójában a KÖVETKEZŐ napra esik, nem a nyers kezdéssel azonos napra.
                $start = $start->addDay();
            }

            // A kerekítés a fenti, már helyesen eldöntött napon belül a kilépés fölé csúsztathatja
            // a kezdést (pl. nagyon rövid szakasznál) – ilyenkor 0 a ledolgozott idő, nem negatív.
            // A Carbon diffInMinutes() alapból abszolút értéket ad vissza, ezért itt explicit
            // előjeles különbség kell, hogy a max(0, ...) ténylegesen hatni tudjon.
            $result[spl_object_id($entry)] = (int) max(0, $start->diffInMinutes($end, absolute: false));
        }

        return $result;
    }

    /**
     * Egy nap összes lezárt (be- és kilépéssel is rendelkező) jelenlét-szakaszának együttes
     * ledolgozott ideje percben. A napi küszöböt és a hibahatárokat mindig erre az összegre
     * kell alkalmazni, NEM az egyes szakaszokra külön-külön – különben napi több be-/kilépés
     * (pl. ebédszünet) esetén minden szakasz önmagában "hiányos munkanapnak" tűnne, és
     * többszörösen (tévesen) terhelné/jóváírná a túlóra-keretet.
     *
     * @param  Collection<int, TimeEntry>  $entriesForDay  a nap ÖSSZES (nyitott is) Presence szakasza
     */
    public function totalWorkedMinutesForDay(Collection $entriesForDay): int
    {
        return array_sum($this->segmentMinutesForDay($entriesForDay));
    }

    /**
     * [rendes_percek, túlóra_percek] egy napi ledolgozott idő alapján.
     *
     * @param  int|null  $standardMinutes  ld. deltaMinutes()
     */
    public function splitMinutes(int $workedMinutes, ?int $standardMinutes = null): array
    {
        $standard = $standardMinutes ?? self::STANDARD_WORKDAY_MINUTES;
        $regular = min($workedMinutes, $standard);
        $overtime = max(0, $workedMinutes - $standard);
        return [$regular, $overtime];
    }

    /** Göngyölt egyenleg módosítása; a keret negatív is lehet. */
    public function applyDelta(int $employeeId, ?int $companyId, int $deltaMinutes): OvertimeBalance
    {
        $balance = OvertimeBalance::firstOrCreate(
            ['employee_id' => $employeeId],
            ['company_id' => $companyId, 'balance_minutes' => 0]
        );

        $balance->increment('balance_minutes', $deltaMinutes);

        return $balance->fresh();
    }
}
