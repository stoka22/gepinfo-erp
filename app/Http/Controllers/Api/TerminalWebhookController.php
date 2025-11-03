<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TerminalWebhookController extends Controller
{
    public function store(Request $request)
    {
        // Egyszerű biztonsági kulcs
        $token = $request->header('X-Auth-Token');
        if ($token !== config('services.terminal.secret')) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }

        $data = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'direction'   => 'required|in:in,out', // belépés/kilépés
            'timestamp'   => 'required|date',
        ]);

        $ts = Carbon::parse($data['timestamp']);

        if ($data['direction'] === 'in') {
            DB::table('time_entries')->insert([
                'employee_id' => $data['employee_id'],
                'start_date'  => $ts->toDateString(),
                'start_time'  => $ts->format('H:i'),
                'status'      => 'open',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } else {
            $open = DB::table('time_entries')
                ->where('employee_id', $data['employee_id'])
                ->whereNull('end_time')
                ->orderByDesc('id')
                ->first();

            if ($open) {
                DB::table('time_entries')->where('id', $open->id)->update([
                    'end_date'   => $ts->toDateString(),
                    'end_time'   => $ts->format('H:i'),
                    'status'     => 'approved',
                    'updated_at' => now(),
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }
}
