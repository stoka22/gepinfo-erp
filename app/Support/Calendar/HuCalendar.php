<?php
namespace App\Support\Calendar;

use Carbon\Carbon;

class HuCalendar
{
    public static function holidays(int $y): array
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
        $easter = self::easterDate($y);
        $movable = [
            [$easter->copy()->subDays(2)->toDateString(), 'Nagypéntek'],
            [$easter->copy()->addDay()->toDateString(),   'Húsvét hétfő'],
            [$easter->copy()->addDays(50)->toDateString(),'Pünkösd hétfő'],
        ];
        return array_merge($fixed, $movable);
    }

    public static function blockedSet(Carbon $start, Carbon $end): array
    {
        // Tiltott napok: hétvége + ünnep + áthelyezett pihenőnap; kivétel: áthelyezett munkanap
        $blocked = [];
        $work = []; $rest = [];
        for ($y = (int)$start->format('Y'); $y <= (int)$end->format('Y'); $y++) {
            $ov = config("calendar_overrides.overrides.$y", []);
            foreach (($ov['workdays'] ?? []) as $d => $_) { $work[$d] = true; }
            foreach (($ov['restdays'] ?? []) as $d => $_) { $rest[$d] = true; }
            foreach (self::holidays($y) as [$d])          { $rest[$d] = true; }
        }
        $cur = $start->copy()->startOfDay();
        $to  = $end->copy()->startOfDay();
        while ($cur->lte($to)) {
            $k = $cur->toDateString();
            $isWeekend = $cur->isWeekend();
            $isRest = isset($rest[$k]);
            $isWork = isset($work[$k]);
            $blocked[$k] = (!$isWork) && ($isWeekend || $isRest);
            $cur->addDay();
        }
        return $blocked;
    }

    public static function easterDate(int $Y): Carbon
    {
        $a=$Y%19;$b=intdiv($Y,100);$c=$Y%100;$d=intdiv($b,4);$e=$b%4;$f=intdiv($b+8,25);
        $g=intdiv($b-$f+1,3);$h=(19*$a+$b-$d-$g+15)%30;$i=intdiv($c,4);$k=$c%4;
        $l=(32+2*$e+2*$i-$h-$k)%7;$m=intdiv($a+11*$h+22*$l,451);
        $month=intdiv($h+$l-7*$m+114,31);$day=(($h+$l-7*$m+114)%31)+1;
        return Carbon::create($Y,$month,$day);
    }
}
