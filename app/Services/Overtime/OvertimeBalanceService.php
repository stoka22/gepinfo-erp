<?php

namespace App\Services\Overtime;

use App\Models\OvertimeBalance;
use App\Models\TimeEntry;
use Carbon\Carbon;

class OvertimeBalanceService
{
    /** Standard munkanap hossza percben: 8 óra 30 perc. */
    public const STANDARD_WORKDAY_MINUTES = 510;

    /** Kijelentkezési hibahatár percben: ennyi korábbi távozás még nem számít hiánynak. */
    public const EARLY_DEPARTURE_TOLERANCE_MINUTES = 2;

    /** Túlóra hibahatár percben: a 8:30 utáni idő csak eddig a percig türelmi idő, e fölött a teljes eltérés túlórának számít. */
    public const OVERTIME_TOLERANCE_MINUTES = 10;

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
     * afölött viszont a teljes (8:30-tól számított) eltérés túlóra/hiány, visszamenőlegesen.
     * - Korai távozás: EARLY_DEPARTURE_TOLERANCE_MINUTES percig nem számít hiánynak.
     * - Túlóra: OVERTIME_TOLERANCE_MINUTES percig nem számít túlórának.
     */
    public function deltaMinutes(int $workedMinutes): int
    {
        $delta = $workedMinutes - self::STANDARD_WORKDAY_MINUTES;

        if ($delta < 0 && $delta >= -self::EARLY_DEPARTURE_TOLERANCE_MINUTES) {
            return 0;
        }

        if ($delta > 0 && $delta <= self::OVERTIME_TOLERANCE_MINUTES) {
            return 0;
        }

        return $delta;
    }

    /** [rendes_percek, túlóra_percek] egy napi ledolgozott idő alapján (8:30 felett túlóra). */
    public function splitMinutes(int $workedMinutes): array
    {
        $regular = min($workedMinutes, self::STANDARD_WORKDAY_MINUTES);
        $overtime = max(0, $workedMinutes - self::STANDARD_WORKDAY_MINUTES);
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
