<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Services\Calendar\HuWorkCalendar;

class CalendarMarkersController extends Controller
{
     public function __invoke(Request $request)
    {
        [$start, $end] = $this->range($request);
        $years = range((int)substr($start,0,4), (int)substr($end,0,4));

        $markers = [];

        // 1) Magyar munkaszüneti napok (fix + mozgó)
        foreach ($years as $y) {
            foreach ($this->huHolidays($y) as [$date, $title]) {
                if ($date >= $start && $date <= $end) {
                    $markers[] = ['date' => $date, 'kind' => 'holiday', 'title' => "Ünnepnap: $title"];
                }
            }
        }

        // 2) Áthelyezett napok (konfigból)
        $overrides = config('calendar_overrides.overrides', []);
        foreach ($years as $y) {
            $o = $overrides[$y] ?? ['workdays'=>[], 'restdays'=>[]];

            foreach ($o['workdays'] ?? [] as $d => $title) {
                if ($d >= $start && $d <= $end) {
                    $markers[] = ['date' => $d, 'kind' => 'workday', 'title' => $title ?: 'Áthelyezett munkanap'];
                }
            }
            foreach ($o['restdays'] ?? [] as $d => $title) {
                if ($d >= $start && $d <= $end) {
                    $markers[] = ['date' => $d, 'kind' => 'restday', 'title' => $title ?: 'Áthelyezett pihenőnap'];
                }
            }
        }

        return response()->json($markers, 200, ['Cache-Control' => 'no-store']);
    }

    private function range(Request $r): array
    {
        try { $s = Carbon::parse($r->query('start'))->toDateString(); } catch (\Throwable) { $s = now()->startOfMonth()->toDateString(); }
        try { $e = Carbon::parse($r->query('end'))->toDateString(); }   catch (\Throwable) { $e = now()->endOfMonth()->toDateString(); }
        if ($e < $s) [$s, $e] = [$e, $s];
        return [$s, $e];
    }

    /** Visszaadja: [[Y-m-d, Cím], ...] */
    private function huHolidays(int $y): array
    {
        // fix
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

        // mozgó: nagypéntek, húsvét hétfő, pünkösd hétfő
        $easter = $this->easterDate($y);
        $goodFriday = $easter->copy()->subDays(2)->toDateString();
        $easterMon  = $easter->copy()->addDay()->toDateString();
        $whitMon    = $easter->copy()->addDays(50)->toDateString();

        $movable = [
            [$goodFriday, 'Nagypéntek'],
            [$easterMon,  'Húsvét hétfő'],
            [$whitMon,    'Pünkösd hétfő'],
        ];

        return array_merge($fixed, $movable);
    }

    private function easterDate(int $Y): Carbon
    {
        // Meeus/Jones/Butcher
        $a = $Y % 19;
        $b = intdiv($Y, 100); $c = $Y % 100;
        $d = intdiv($b, 4);   $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19*$a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);   $k = $c % 4;
        $l = (32 + 2*$e + 2*$i - $h - $k) % 7;
        $m = intdiv($a + 11*$h + 22*$l, 451);
        $month = intdiv($h + $l - 7*$m + 114, 31);
        $day   = (($h + $l - 7*$m + 114) % 31) + 1;

        return Carbon::create($Y, $month, $day); // Húsvét vasárnap
    }
}
