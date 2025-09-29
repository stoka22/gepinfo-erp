<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;

class SchedulerController extends Controller
{
    private function qtyToMinutes(int|float $qty, int|float $ratePph): float {
        if ($ratePph <= 0) return 0;
        return ($qty / $ratePph) * 60.0;
    }

    // Mock: gépek/erőforrások
    public function resources(Request $request): JsonResponse
    {
        $resources = collect(range(1, 30))->map(function ($i) {
            return [
                'id' => $i,
                'name' => "Gép #{$i}",
                'group' => $i <= 10 ? 'Lézervágó' : ($i <= 20 ? 'Hajlító' : 'Hegesztő'),
                'calendarId' => 1,
            ];
        });

        return response()->json($resources);
    }

    // Mock: feladatok (időszűréssel)
    public function tasks(Request $request): JsonResponse
    {
        $from = $request->query('from') ? Carbon::parse($request->query('from')) : now()->startOfDay()->subDays(1);
        $to   = $request->query('to')   ? Carbon::parse($request->query('to'))   : now()->endOfDay()->addDays(3);

        // Ha felcserélték a paramétereket, tegyük helyre.
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        // Időtartam órában (nem negatív).
        $spanHours = max(0, $from->diffInHours($to));

        // Ha túl kicsi az ablak, adjunk hozzá 4 órát (hogy legyen hely).
        if ($spanHours === 0) {
            $to = $from->copy()->addHours(4);
            $spanHours = 4;
        }

        $durPool = [60, 90, 120, 180, 240]; // percek

       $batch = 1000;
$rate  = 300; // db/óra

$tasks = collect(range(1, 120))->map(function ($i) use ($from, $to, $batch, $rate) {
    $start = $from->copy()->addHours(random_int(0, max(1, $from->diffInHours($to) - 3)))->seconds(0)->milliseconds(0);

    // tegyünk rá 3-6 ezer db-ot (3-6 batch)
    $batches = random_int(3, 6);
    $qtyFrom = 0;
    $qtyTo   = $batches * $batch;
    $mins    = ($qtyTo - $qtyFrom) > 0 ? ($qtyTo - $qtyFrom) / $rate * 60 : 60;
    $end     = $start->copy()->addMinutes((int)round($mins))->seconds(0)->milliseconds(0);

    return [
        'id'         => $i,
        'title'      => "Művelet #{$i}",
        'resourceId' => random_int(1, 30),

        'start'      => $start->toIso8601String(),
        'end'        => $end->toIso8601String(),

        'qtyTotal'   => $batches * $batch,
        'qtyFrom'    => $qtyFrom,
        'qtyTo'      => $qtyTo,
        'ratePph'    => $rate,
        'batchSize'  => $batch,

        'color'      => null,
        'locked'     => false,
    ];
});


        return response()->json($tasks->values());
    }


    // Drag/resize utáni mentés – egyelőre csak validáció-mock + echo back
    public function schedule(int $taskId, Request $request): JsonResponse
    {
        $data = $request->validate([
            'start'      => ['required', 'date'],
            'end'        => ['required', 'date', 'after:start'],
            'resourceId' => ['required', 'integer'],
        ]);

        // Itt később: ütközésvizsgálat, calendar szabályok, stb.
        return response()->json([
            'ok' => true,
            'taskId' => $taskId,
            'applied' => $data,
        ]);
    }

    // “What-if” validátor – egyelőre mindig ok vagy random figyelmeztetés
    public function validateBatch(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'changes' => ['required', 'array'],
        ]);

        // később: tényleges szabálymotor
        return response()->json([
            'issues' => [], // pl. [{ code: 'OVERLAP', taskId: 12, message: '...' }]
        ]);
    }

public function split(int $task, \Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
{
    $data = $request->validate([
        'splitAt'    => ['required', 'date'],
        'start'      => ['required', 'date'],
        'end'        => ['required', 'date', 'after:start'],
        'resourceId' => ['required', 'integer'],
        'minMinutes' => ['nullable', 'integer', 'min:1'],
    ]);

    $min = max(15, (int)($data['minMinutes'] ?? 15)); // állítható minimum

    $start = \Illuminate\Support\Carbon::parse($data['start'])->seconds(0)->milliseconds(0);
    $end   = \Illuminate\Support\Carbon::parse($data['end'])->seconds(0)->milliseconds(0);
    $split = \Illuminate\Support\Carbon::parse($data['splitAt'])->seconds(0)->milliseconds(0);

    if ($split->lte($start) || $split->gte($end)) {
        return response()->json(['error' => 'Invalid split point (outside segment)'], 422);
    }

    // MÁSODPERC alapú ellenőrzés (nincs floor miatti 1 perces csúszás)
    $leftSec  = $start->diffInSeconds($split);
    $rightSec = $split->diffInSeconds($end);
    if ($leftSec < $min * 60 || $rightSec < $min * 60) {
        return response()->json([
            'error' => "Segments would be too short (min {$min} min). Left=" . floor($leftSec/60) . " min, Right=" . floor($rightSec/60) . " min"
        ], 422);
    }

    // mock: két új task
    $leftId  = (int)($task * 10 + 1);
    $rightId = (int)($task * 10 + 2);

    return response()->json([
        'ok' => true,
        'removedId' => $task,
        'newTasks' => [
            [
                'id'         => $leftId,
                'title'      => "Művelet #{$leftId}",
                'start'      => $start->toIso8601String(),
                'end'        => $split->toIso8601String(),
                'resourceId' => (int)$data['resourceId'],
                'progress'   => 0.0,
                'color'      => null,
                'locked'     => false,
            ],
            [
                'id'         => $rightId,
                'title'      => "Művelet #{$rightId}",
                'start'      => $split->toIso8601String(),
                'end'        => $end->toIso8601String(),
                'resourceId' => (int)$data['resourceId'],
                'progress'   => 0.0,
                'color'      => null,
                'locked'     => false,
            ],
        ],
    ]);
}

public function splitQty(int $task, \Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
{
    $data = $request->validate([
        'qtySplit'   => ['required', 'numeric', 'min:1'],
        'qtyFrom'    => ['required', 'numeric'],
        'qtyTo'      => ['required', 'numeric', 'gt:qtyFrom'],
        'ratePph'    => ['required', 'numeric', 'min:1'],
        'batchSize'  => ['required', 'numeric', 'min:1'],
        'resourceId' => ['required', 'integer'],
        'startISO'   => ['required', 'date'],
    ]);

    $qtyFrom   = (int)$data['qtyFrom'];
    $qtyTo     = (int)$data['qtyTo'];
    $qtySplit  = (int)$data['qtySplit'];
    $batch     = (int)$data['batchSize'];
    $rate      = (int)$data['ratePph'];
    $start     = \Illuminate\Support\Carbon::parse($data['startISO'])->seconds(0)->milliseconds(0);

    // snap & guard
    $qtySplit = (int) (round($qtySplit / $batch) * $batch);
    if ($qtySplit <= $qtyFrom || $qtySplit >= $qtyTo) {
        return response()->json(['error' => 'Split outside of range'], 422);
    }
    if (($qtySplit - $qtyFrom) < $batch || ($qtyTo - $qtySplit) < $batch) {
        return response()->json(['error' => "Segments would be too short (min {$batch} pcs)"], 422);
    }

    // időpontok a mennyiségből
    $minsLeft  = ($qtySplit - $qtyFrom) / $rate * 60;
    $minsRight = ($qtyTo - $qtySplit) / $rate * 60;

    $leftStart  = $start->copy();
    $leftEnd    = $start->copy()->addMinutes((int)round($minsLeft));

    $rightStart = $leftEnd->copy();
    $rightEnd   = $rightStart->copy()->addMinutes((int)round($minsRight));

    $leftId  = (int)($task * 10 + 1);
    $rightId = (int)($task * 10 + 2);

    return response()->json([
        'ok' => true,
        'removedId' => $task,
        'newTasks' => [
            [
                'id'         => $leftId,
                'title'      => "Művelet #{$leftId}",
                'resourceId' => (int)$data['resourceId'],
                'start'      => $leftStart->toIso8601String(),
                'end'        => $leftEnd->toIso8601String(),
                'qtyTotal'   => $qtyTo,     // teljes termék
                'qtyFrom'    => $qtyFrom,
                'qtyTo'      => $qtySplit,
                'ratePph'    => $rate,
                'batchSize'  => $batch,
                'locked'     => false,
                'color'      => null,
            ],
            [
                'id'         => $rightId,
                'title'      => "Művelet #{$rightId}",
                'resourceId' => (int)$data['resourceId'],
                'start'      => $rightStart->toIso8601String(),
                'end'        => $rightEnd->toIso8601String(),
                'qtyTotal'   => $qtyTo,
                'qtyFrom'    => $qtySplit,
                'qtyTo'      => $qtyTo,
                'ratePph'    => $rate,
                'batchSize'  => $batch,
                'locked'     => false,
                'color'      => null,
            ],
        ],
    ]);
}

}
