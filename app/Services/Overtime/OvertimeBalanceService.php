<?php

namespace App\Services\Overtime;

use App\Models\OvertimeBalance;
use App\Models\TimeEntry;
use Carbon\Carbon;

class OvertimeBalanceService
{
    /** Standard munkanap hossza percben: 8 óra 30 perc. */
    public const STANDARD_WORKDAY_MINUTES = 510;

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

    /** A napi eltérés a standard munkaidőtől (pozitív = túlóra, negatív = hiány). */
    public function deltaMinutes(int $workedMinutes): int
    {
        return $workedMinutes - self::STANDARD_WORKDAY_MINUTES;
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
