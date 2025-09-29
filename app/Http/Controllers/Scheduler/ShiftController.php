<?php

namespace App\Http\Controllers\Scheduler;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftController extends Controller
{
    public function window(Request $r) {
      $date = $r->query('date'); // 'YYYY-MM-DD'
      if (!$date) return response()->json(['error'=>'missing date'], 400);

      $weekday = (int) date('N', strtotime($date)); // 1..7 (Mon..Sun)
      // mask: igazítsd a tárolás logikájához
      $bit = 1 << ($weekday - 1);

      $pattern = DB::table('shift_patterns')
        ->whereRaw('(days_mask & ?) != 0', [$bit])
        ->orderBy('id')
        ->first();

      if (!$pattern) return response()->json(['error'=>'no shift'], 404);

      return response()->json([
        'start' => $pattern->start_time, // "HH:MM:SS"
        'end'   => $pattern->end_time,
      ]);
    }
}