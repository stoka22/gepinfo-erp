<?php

namespace App\Console\Commands;

use App\Enums\TimeEntryStatus;
use App\Enums\TimeEntryType;
use App\Models\Employee;
use App\Models\TimeEntry;
use App\Services\Calendar\WorkdayResolver;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Napi/időszaki ellenőrzés: minden aktív dolgozó minden munkanapjára (a WorkdayResolver
 * szerint -- hétvége/ünnep/áthelyezett munkanap/egyéni műszakminta figyelembevételével),
 * ha a napra EGYETLEN TimeEntry sincs rögzítve (se jelenlét, se szabadság, se táppénz, se
 * igazolatlan távollét), egy "felülvizsgálandó" (status=pending, needs_review=true)
 * Vacation-típusú jelölőt hoz létre -- hogy a hiány a meglévő admin felülvizsgálati sorban
 * (needs_review szűrő) látszódjon, ahelyett hogy csendben, üres cellaként maradna a
 * jelenléti íven.
 *
 * Csak MÚLTBELI (a mai napnál korábbi) napokat jelöl -- a mai nap még nyitott lehet,
 * a dolgozó este még bejelentkezhet -- és csak a dolgozó felvételi dátuma (hired_at)
 * utáni napokat.
 */
class FlagMissingPresenceDays extends Command
{
    protected $signature = 'attendance:flag-missing-days
        {--from= : kezdő dátum (Y-m-d), alapértelmezett: a záró dátum előtti 30. nap}
        {--to= : záró dátum (Y-m-d), alapértelmezett: tegnap}
        {--dry : csak jelentés, nincs írás}';

    protected $description = 'Teljesen üres (semmilyen bejegyzés nélküli) munkanapokra felülvizsgálandó szabadság-jelölőt hoz létre.';

    public function handle(WorkdayResolver $workdayResolver): int
    {
        $dry = (bool) $this->option('dry');

        $today = CarbonImmutable::today();
        $to = $this->option('to') ? CarbonImmutable::parse($this->option('to')) : $today->subDay();
        if ($to->gte($today)) {
            $to = $today->subDay();
        }
        $from = $this->option('from') ? CarbonImmutable::parse($this->option('from')) : $to->subDays(29);

        if ($from->gt($to)) {
            $this->info('Nincs vizsgálandó időszak (from > to).');

            return self::SUCCESS;
        }

        $created = 0;

        foreach (Employee::query()->get() as $employee) {
            $hireDate = $employee->hired_at ? CarbonImmutable::parse($employee->hired_at) : null;
            $rangeStart = ($hireDate && $hireDate->gt($from)) ? $hireDate : $from;

            if ($rangeStart->gt($to)) {
                continue;
            }

            $existingDates = TimeEntry::query()
                ->where('employee_id', $employee->id)
                ->whereBetween('start_date', [$rangeStart->toDateString(), $to->toDateString()])
                ->pluck('start_date')
                ->map(fn ($d) => Carbon::parse($d)->toDateString())
                ->flip();

            $d = $rangeStart;
            while ($d->lte($to)) {
                $dateStr = $d->toDateString();

                if (! $existingDates->has($dateStr) && $workdayResolver->isWorkingDayForEmployee($employee, $d)) {
                    $this->line(sprintf('%s: %s -- hiányzó nap, felülvizsgálandó szabadság-jelölő%s', $employee->name, $dateStr, $dry ? ' (dry-run)' : ''));
                    $created++;

                    if (! $dry) {
                        TimeEntry::create([
                            'employee_id'  => $employee->id,
                            'company_id'   => $employee->company_id,
                            'type'         => TimeEntryType::Vacation->value,
                            'status'       => TimeEntryStatus::Pending->value,
                            'start_date'   => $dateStr,
                            'end_date'     => $dateStr,
                            'needs_review' => true,
                            'entry_method' => 'missing-day',
                            'note'         => 'Automatikusan jelölve: nincs rögzített jelenlét/távollét erre a napra.',
                        ]);
                    }
                }

                $d = $d->addDay();
            }
        }

        $this->line('---');
        $this->info(sprintf('Összesen %d hiányzó nap jelölve.%s', $created, $dry ? ' (DRY-RUN, nem íródott az adatbázisba)' : ''));

        return self::SUCCESS;
    }
}
