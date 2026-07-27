<?php

namespace App\Services\Calendar;

use App\Models\Employee;
use Carbon\CarbonImmutable;

/**
 * Egységes munkanap/ünnepnap eldöntés: import-osztályozáshoz és jelenléti ív
 * nyomtatáshoz egyaránt ugyanazt a naptárt (HuWorkCalendar + config overrides
 * + dolgozói ShiftPattern) használja, hogy a kettő ne térjen el egymástól.
 */
class WorkdayResolver
{
    public function __construct(private HuWorkCalendar $calendar)
    {
    }

    /** Az ünnepnap neve, vagy null, ha a dátum nem munkaszüneti nap. */
    public function holidayName(CarbonImmutable $date): ?string
    {
        $year = (int) $date->year;
        $dateStr = $date->toDateString();

        $overrides = config("hu_workcalendar.overrides.{$year}", []);
        if (array_key_exists($dateStr, $overrides['workdays'] ?? [])) {
            return null; // áthelyezett munkanap -> nem ünnep
        }

        $holidays = $this->calendar->fixedHolidays($year) + $this->calendar->movableHolidays($year);
        return $holidays[$dateStr] ?? null;
    }

    public function isRestDayOverride(CarbonImmutable $date): bool
    {
        $year = (int) $date->year;
        $overrides = config("hu_workcalendar.overrides.{$year}", []);
        return array_key_exists($date->toDateString(), $overrides['restdays'] ?? []);
    }

    public function isWorkingDayForEmployee(Employee $employee, CarbonImmutable $date): bool
    {
        if ($this->isRestDayOverride($date)) {
            return false;
        }
        if ($this->holidayName($date) !== null) {
            return false;
        }

        $year = (int) $date->year;
        $overrides = config("hu_workcalendar.overrides.{$year}", []);
        if (array_key_exists($date->toDateString(), $overrides['workdays'] ?? [])) {
            return true; // áthelyezett munkanap
        }

        $pattern = $employee->shiftPattern;
        if ($pattern) {
            return $pattern->appliesToDow($date->dayOfWeekIso - 1);
        }

        return ! $date->isWeekend();
    }
}
