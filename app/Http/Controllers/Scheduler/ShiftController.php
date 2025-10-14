<?php

namespace App\Http\Controllers\Scheduler;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\CarbonImmutable;

class ShiftController extends Controller
{
    /**
     * GET /api/scheduler/shift-window?date=YYYY-MM-DD&resource_id?=&tz?
     *
     * Válasz:
     * - start/end: "HH:MM:SS" (backward compatible összefoglaló)
     * - windows: [{ start_time, end_time, startAt, endAt }]
     * - tz: időzóna
     */
    public function window(Request $r)
    {
        $data = $r->validate([
            'date'        => ['required', 'date'],
            'resource_id' => ['nullable', 'integer'],
            'tz'          => ['nullable', 'string'],
        ]);

        $tz = $data['tz'] ?? config('app.timezone', 'UTC');
        $day = CarbonImmutable::parse($data['date'], $tz)->startOfDay();
        $weekday = $day->isoWeekday(); // 1..7 (Mon..Sun)
        $bit = 1 << ($weekday - 1);

        // --- EXCEPTION (opcionális tábla) ---
        $exception = null;
        if (Schema::hasTable('shift_exceptions')) {
            $exception = DB::table('shift_exceptions')
                ->when($data['resource_id'] ?? null, function ($q, $rid) {
                    // először resource-specifikus, aztán globális
                    $q->where(function ($qq) use ($rid) {
                        $qq->where('resource_id', $rid)->orWhereNull('resource_id');
                    });
                })
                ->whereDate('date', $day->toDateString())
                ->orderByRaw('resource_id IS NULL') // preferáljuk a resource-hoz kötöttet
                ->first();
        }

        if ($exception && !empty($exception->closed)) {
            // zárt nap
            return response()->json([
                'start'   => null,
                'end'     => null,
                'windows' => [],
                'closed'  => true,
                'tz'      => $tz,
            ]);
        }

        // --- PATTERNS ---
        // Elvárt séma: shift_patterns(id, resource_id?, days_mask, start_time, end_time, is_active?)
        $patternsQuery = DB::table('shift_patterns')
            ->whereRaw('(days_mask & ?) <> 0', [$bit])
            ->when($data['resource_id'] ?? null, function ($q, $rid) {
                $q->where(function ($qq) use ($rid) {
                    $qq->where('resource_id', $rid)->orWhereNull('resource_id');
                });
            })
            ->when(Schema::hasColumn('shift_patterns', 'is_active'), fn ($q) => $q->where('is_active', 1))
            ->orderByRaw('COALESCE(resource_id, 0) DESC') // preferáljuk a resource-os mintát
            ->orderBy('start_time');

        $patterns = collect();

        if ($exception && $exception->start_time && $exception->end_time) {
            // Napi kivétel felülírja a mintákat (egy ablak)
            $patterns = collect([(object)[
                'start_time' => $exception->start_time,
                'end_time'   => $exception->end_time,
            ]]);
        } else {
            $patterns = $patternsQuery->get();
        }

        if ($patterns->isEmpty()) {
            return response()->json(['error' => 'no shift'], 404);
        }

        // --- Ablakok számítása, éjjelbe lógás kezelése ---
        $windows = $patterns->map(function ($p) use ($day, $tz) {
            $start = CarbonImmutable::parse($day->format('Y-m-d') . ' ' . $p->start_time, $tz);
            $end   = CarbonImmutable::parse($day->format('Y-m-d') . ' ' . $p->end_time, $tz);
            if ($end->lessThanOrEqualTo($start)) {
                $end = $end->addDay(); // átlóg a következő napra
            }
            return [
                'start_time' => $start->format('H:i:s'),
                'end_time'   => $end->format('H:i:s'),
                'startAt'    => $start->toIso8601String(),
                'endAt'      => $end->toIso8601String(),
            ];
        })->sortBy('startAt')->values();

        // --- Átfedések összefésülése (ha több minta van) ---
        $merged = [];
        foreach ($windows as $w) {
            if (empty($merged)) {
                $merged[] = $w;
                continue;
            }
            $lastIdx = count($merged) - 1;
            $last = $merged[$lastIdx];

            if ($w['startAt'] <= $last['endAt']) {
                // összefésülés
                if ($w['endAt'] > $last['endAt']) {
                    $last['endAt']    = $w['endAt'];
                    $last['end_time'] = (new CarbonImmutable($w['endAt']))->format('H:i:s');
                }
                $merged[$lastIdx] = $last;
            } else {
                $merged[] = $w;
            }
        }

        // Backward compatible összegzés: min start, max end (az adott napon belül)
        $start = $merged[0]['start_time'] ?? '06:00:00';
        $end   = $merged[count($merged)-1]['end_time'] ?? '18:00:00';

        return response()->json([
            'start'   => $start,   // HH:MM:SS
            'end'     => $end,     // HH:MM:SS
            'windows' => $merged,  // részletes idősávok
            'tz'      => $tz,
        ]);
    }
}
