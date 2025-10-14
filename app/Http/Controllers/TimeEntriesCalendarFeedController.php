<?php

namespace App\Http\Controllers;

use App\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Support\Calendar\HuCalendar;

class TimeEntriesCalendarFeedController 
{
// ... namespace, use ...

public function __invoke(Request $request)
{
    // FullCalendar: start inkluzív, end exkluzív
    $start = Carbon::parse($request->query('start', now()->startOfMonth()->toDateString()))->startOfDay();
    $end   = Carbon::parse($request->query('end',   now()->endOfMonth()->toDateString()))->endOfDay();

    // Kért típusok a frontendről (checkboxok). Ha üres, mindent adunk.
    $types = $request->query('types', []);
    if (!is_array($types)) $types = [];

    $q = TimeEntry::query()
        ->when(Auth::user()?->company_id, fn ($qq) => $qq->where('company_id', Auth::user()->company_id))
        ->when(!empty($types), fn ($qq) => $qq->whereIn('type', $types))
        ->where(function ($qq) use ($start, $end) {
            // átfedés a kért ablakkal
            $qq->whereBetween('start_date', [$start, $end])
               ->orWhereBetween('end_date',   [$start, $end])
               ->orWhere(function ($q2) use ($start, $end) {
                   $q2->where('start_date', '<=', $start)->where('end_date', '>=', $end);
               });
        })
        ->with('employee:id,name')
        ->orderBy('start_date');

    $entries = $q->get();

    // Tiltott napok: hétvége + ünnep + áthelyezett pihenőnap; kivétel: áthelyezett munkanap
    $blocked = $this->makeBlockedSet($start->copy(), $end->copy());

    $events = [];

    foreach ($entries as $t) {
        $type   = $t->type   instanceof \BackedEnum ? $t->type->value   : (string) $t->type;
        $status = $t->status instanceof \BackedEnum ? $t->status->value : (string) $t->status;

        $title = ($t->employee?->name ?? '—') . ' — ' . match ($type) {
            'vacation'   => 'Szabadság',
            'overtime'   => 'Túlóra',
            'sick_leave' => 'Táppénz',
            'presence'   => 'Jelenlét',
            default      => ucfirst($type),
        };

        $bg = match ($type) {
            'vacation'   => '#f59e0b',
            'overtime'   => '#38bdf8',
            'sick_leave' => '#ef4444',
            'presence'   => '#10b981',
            default      => '#94a3b8',
        };

        $startYmd = Carbon::parse($t->start_date)->toDateString();
        $endYmd   = Carbon::parse($t->end_date ?? $t->start_date)->toDateString();

        if (in_array($type, ['vacation','sick_leave'], true)) {
            // csak munkanapokra rajzoljuk
            foreach ($this->businessDaySegments($startYmd, $endYmd, $blocked) as [$segStart, $segEnd]) {
                $events[] = [
                    'id'        => "te-{$t->id}-{$segStart}",
                    'title'     => $title,
                    'start'     => $segStart,
                    'end'       => Carbon::parse($segEnd)->addDay()->toDateString(), // allDay end exkluzív
                    'allDay'    => true,
                    'backgroundColor' => $bg,
                    'borderColor'     => $bg,
                    'textColor'       => '#111827',
                    'extendedProps'   => [
                        'type'   => $type,
                        'status' => $status,
                        'note'   => (string) ($t->note ?? ''),
                    ],
                ];
            }
            continue;
        }

        // Egyéb típusok változatlanul
        $events[] = [
            'id'        => (string) $t->id,
            'title'     => $title,
            'start'     => $startYmd,
            'end'       => Carbon::parse($endYmd)->addDay()->toDateString(),
            'allDay'    => true,
            'backgroundColor' => $bg,
            'borderColor'     => $bg,
            'textColor'       => '#111827',
            'extendedProps'   => [
                'type'   => $type,
                'status' => $status,
                'note'   => (string) ($t->note ?? ''),
            ],
        ];
    }

    return response()->json(array_values($events), 200, ['Cache-Control' => 'no-store']);
}

/** Hétvége + ünnep + áthelyezett pihenőnap; kivétel: áthelyezett munkanap */
private function makeBlockedSet(Carbon $start, Carbon $end): array
{
    $blocked = [];
    $work = []; $rest = [];

    for ($y = (int)$start->format('Y'); $y <= (int)$end->format('Y'); $y++) {
        $ov = config("calendar_overrides.overrides.$y", []);
        foreach (($ov['workdays'] ?? []) as $d => $_) { $work[$d] = true; }
        foreach (($ov['restdays'] ?? []) as $d => $_) { $rest[$d] = true; }
        foreach ($this->huHolidays($y) as [$d])       { $rest[$d] = true; }
    }

    $cur = $start->copy()->startOfDay();
    $to  = $end->copy()->startOfDay();
    while ($cur->lte($to)) {
        $k = $cur->toDateString();
        $isWeekend = $cur->isWeekend();
        $isRest    = isset($rest[$k]);
        $isWork    = isset($work[$k]);
        $blocked[$k] = (!$isWork) && ($isWeekend || $isRest);
        $cur->addDay();
    }

    return $blocked;
}

/** Szomszédos munkanapokat összevonva adja vissza a szegmenseket */
private function businessDaySegments(string $fromYmd, string $toYmd, array $blocked): array
{
    $cur = Carbon::parse($fromYmd);
    $to  = Carbon::parse($toYmd);

    $segments = [];
    $segStart = null;
    $prev = null;

    while ($cur->lte($to)) {
        $k = $cur->toDateString();
        $isBlocked = !empty($blocked[$k]);

        if (!$isBlocked) {
            if ($segStart === null) $segStart = $k;
            $prev = $k;
        } else {
            if ($segStart !== null) {
                $segments[] = [$segStart, $prev];
                $segStart = null;
            }
        }
        $cur->addDay();
    }
    if ($segStart !== null) $segments[] = [$segStart, $prev];

    return $segments;
}

// huHolidays() + easterDate() maradhat, ahogy eddig használtad


    /** Magyar ünnepnapok: [[Y-m-d, Cím], ...] */
    private function huHolidays(int $y): array
    {
        $fixed = [
            ["$y-01-01", 'Újév'],
            ["$y-03-15", 'Nemzeti ünnep'],
            ["$y-05-01", 'A munka ünnepe'],
            ["$y-08-20", 'Államalapítás'],
            ["$y-10-23", '1956-os forradalom'],
            ["$y-11-01", 'Mindenszentek'],
            ["$y-12-25", 'Karácsony'],
            ["$y-12-26", 'Karácsony'],
        ];

        $easter = $this->easterDate($y);
        $movable = [
            [$easter->copy()->subDays(2)->toDateString(), 'Nagypéntek'],
            [$easter->copy()->addDay()->toDateString(),   'Húsvét hétfő'],
            [$easter->copy()->addDays(50)->toDateString(),'Pünkösd hétfő'],
        ];

        return array_merge($fixed, $movable);
    }

    /** Húsvét vasárnap (Meeus/Jones/Butcher) */
    private function easterDate(int $Y): Carbon
    {
        $a=$Y%19; $b=intdiv($Y,100); $c=$Y%100; $d=intdiv($b,4); $e=$b%4; $f=intdiv($b+8,25);
        $g=intdiv($b-$f+1,3); $h=(19*$a+$b-$d-$g+15)%30; $i=intdiv($c,4); $k=$c%4;
        $l=(32+2*$e+2*$i-$h-$k)%7; $m=intdiv($a+11*$h+22*$l,451);
        $month=intdiv($h+$l-7*$m+114,31); $day=(($h+$l-7*$m+114)%31)+1;
        return Carbon::create($Y,$month,$day);
    }
}
