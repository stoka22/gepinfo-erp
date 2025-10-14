<?php

namespace App\Services\Calendar;

use Illuminate\Support\Carbon;

class HuWorkCalendar
{
    /** Fix ünnepnapok (jan.1, márc.15, máj.1, aug.20, okt.23, nov.1, dec.25-26) */
    public function fixedHolidays(int $year): array
    {
        $f = fn(int $m,int $d,string $t)=>[sprintf('%d-%02d-%02d',$year,$m,$d)=>$t];

        return array_merge(
            $f(1, 1,  'Újév'),
            $f(3, 15, 'Nemzeti ünnep'),
            $f(5, 1,  'A munka ünnepe'),
            $f(8, 20, 'Államalapítás'),
            $f(10,23, '1956-os forradalom'),
            $f(11, 1, 'Mindenszentek'),
            $f(12,25, 'Karácsony'),
            $f(12,26, 'Karácsony')
        );
    }

    /** Mozgó ünnepek: Nagypéntek, Húsvét hétfő, Pünkösd hétfő */
    public function movableHolidays(int $year): array
    {
        $easter = $this->easterDate($year);                   // Húsvét vasárnap
        $goodFriday   = $easter->copy()->subDays(2);
        $easterMonday = $easter->copy()->addDay();
        $pentecostMon = $easter->copy()->addDays(50);

        $fmt = fn(Carbon $d,string $t)=>[$d->toDateString()=>$t];

        return array_merge(
            $fmt($goodFriday,   'Nagypéntek'),
            $fmt($easterMonday, 'Húsvét hétfő'),
            $fmt($pentecostMon, 'Pünkösd hétfő'),
        );
    }

    /** A látható tartományhoz visszaadjuk a „jelölőket” */
    public function markersForRange(Carbon $start, Carbon $end): array
    {
        $markers = [];

        // Ünnepnapok: minden érintett évben
        for ($y = $start->year; $y <= $end->year; $y++) {
            foreach ([$this->fixedHolidays($y), $this->movableHolidays($y)] as $set) {
                foreach ($set as $date => $title) {
                    if ($date >= $start->toDateString() && $date <= $end->toDateString()) {
                        $markers[$date][] = ['kind' => 'holiday', 'title' => $title];
                    }
                }
            }

            // Cserenapok a configból
            $ovr = config("hu_workcalendar.overrides.$y", []);
            foreach (($ovr['workdays'] ?? []) as $date => $title) {
                if ($date >= $start->toDateString() && $date <= $end->toDateString()) {
                    $markers[$date][] = ['kind' => 'workday', 'title' => $title ?: 'Áthelyezett munkanap'];
                }
            }
            foreach (($ovr['restdays'] ?? []) as $date => $title) {
                if ($date >= $start->toDateString() && $date <= $end->toDateString()) {
                    $markers[$date][] = ['kind' => 'restday', 'title' => $title ?: 'Áthelyezett pihenőnap'];
                }
            }
        }

        // Lapított tömb (FullCalendarhez kényelmesebb)
        $out = [];
        foreach ($markers as $date => $arr) {
            foreach ($arr as $m) {
                $out[] = ['date' => $date] + $m; // date, kind, title
            }
        }
        return $out;
    }

    /** Meeus/Jones/Butcher */
    private function easterDate(int $year): Carbon
    {
        $a=$year%19; $b=intdiv($year,100); $c=$year%100;
        $d=intdiv($b,4); $e=$b%4; $f=intdiv($b+8,25);
        $g=intdiv($b-$f+1,3); $h=(19*$a+$b-$d-$g+15)%30;
        $i=intdiv($c,4); $k=$c%4; $l=(32+2*$e+2*$i-$h-$k)%7;
        $m=intdiv($a+11*$h+22*$l,451);
        $month=intdiv($h+$l-7*$m+114,31);
        $day=(($h+$l-7*$m+114)%31)+1;
        return Carbon::create($year,$month,$day); // vasárnap
    }
}
