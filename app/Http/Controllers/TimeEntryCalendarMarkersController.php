<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
//use Illuminate\Support\Carbon;
use App\Support\Calendar\HuCalendar;
use Carbon\Carbon;

class TimeEntryCalendarMarkersController extends Controller
{
    public function __invoke(Request $request)
    {
        [$start, $end] = $this->range($request);
        $years = range((int)substr($start,0,4), (int)substr($end,0,4));

        $bucket = [];

        foreach ($years as $y) {
            foreach (HuCalendar::holidays($y) as [$date, $title]) {
                if ($date >= $start && $date <= $end) {
                    $bucket[$date][] = ['kind'=>'holiday','title'=>"Ünnepnap: $title"];
                }
            }
        }

        $overrides = config('calendar_overrides.overrides', []);
        foreach ($years as $y) {
            $o = $overrides[$y] ?? ['workdays'=>[], 'restdays'=>[]];
            foreach (($o['workdays'] ?? []) as $d => $title) {
                if ($d >= $start && $d <= $end) $bucket[$d][] = ['kind'=>'workday','title'=>$title ?: 'Áthelyezett munkanap'];
            }
            foreach (($o['restdays'] ?? []) as $d => $title) {
                if ($d >= $start && $d <= $end) $bucket[$d][] = ['kind'=>'restday','title'=>$title ?: 'Áthelyezett pihenőnap'];
            }
        }

        ksort($bucket);
        $markers = [];
        foreach ($bucket as $date => $items) {
            $seen = [];
            foreach ($items as $it) {
                $key = ($it['kind'] ?? '-') . '|' . ($it['title'] ?? '');
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $markers[] = ['date'=>$date, 'kind'=>$it['kind'] ?? 'note', 'title'=>$it['title'] ?? ''];
            }
        }

        return response()->json($markers, 200, ['Cache-Control'=>'no-store']);
    }

    /**
     * Visszaad: [$startYmd, $endYmd]
     * FullCalendar end exkluzív → a kapott end-ből 1 napot visszalépünk.
     */
    private function range(Request $r): array
    {
        try { $s = Carbon::parse($r->query('start'))->toDateString(); } catch (\Throwable) { $s = now()->startOfMonth()->toDateString(); }
        try { $e = Carbon::parse($r->query('end'))->toDateString();   } catch (\Throwable) { $e = now()->endOfMonth()->toDateString(); }
        // FullCalendar end exkluzív → -1 nap
        $e = Carbon::parse($e)->subDay()->toDateString();
        if ($e < $s) [$s,$e]=[$e,$s];
        return [$s,$e];
    }

   
    private function easterDate(int $Y): Carbon
    {
        // Meeus/Jones/Butcher
        $a = $Y % 19;
        $b = intdiv($Y, 100); $c = $Y % 100;
        $d = intdiv($b, 4);   $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);   $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day   = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($Y, $month, $day); // Húsvét vasárnap
    }
}
