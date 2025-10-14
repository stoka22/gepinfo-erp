<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Machine;
use Illuminate\Http\Request;
use App\Models\ProductionTask;
use App\Models\ProductionSplit;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SchedulerController extends Controller
{
    private const DEFAULT_BATCH = 100;
    private const MIN_BAR_MS = 60_000; // 1 perc vizuális minimum

    /** Hasznos: qty kiszámítása (óra × rate), batch-re pattintva */
    private function calcQty(int|float $ratePph, Carbon $start, Carbon $end, int $batch): int
    {
        if ($ratePph <= 0) return 0;
        $hours = max(0.0, $start->floatDiffInRealHours($end));
        $raw   = (int) floor($hours * $ratePph);
        if ($raw <= 0) return 0;
        $b = max(1, $batch);
        $snap = (int) floor($raw / $b) * $b;
        return max($b, $snap);
    }

    /** DB → kliens sémára: committed task */
    private function mapTask(ProductionTask $t): array
    {
        $qty     = (float)($t->qty ?? 0);
        $ratePph = null;
        if (!empty($t->run_seconds) && $t->run_seconds > 0 && $qty > 0) {
            $ratePph = round($qty / ((float)$t->run_seconds / 3600), 2);
        }

        $itemLabel = $t->item->name ?? $t->item->sku ?? '';
        $opLabel   = $t->workStep->name ?? 'Művelet';
        $title     = trim($opLabel . (strlen($itemLabel) ? ' – ' . $itemLabel : ''));

        return [
            'id'        => $t->id,
            'resourceId'=> $t->machine_id,
            'title'     => $title,
            'start'     => optional($t->starts_at)->toIso8601String(),
            'end'       => optional($t->ends_at)->toIso8601String(),
            'qtyTotal'  => $qty,
            'qtyFrom'   => 0.0,
            'qtyTo'     => $qty,
            'ratePph'   => $ratePph,
            'batchSize' => null,
            'partnerName'   => $t->partner->name ?? null,
            'orderCode'     => $t->order->order_no ?? null,
            'productSku'    => $t->item->sku ?? null,
            'operationName' => $t->workStep->name ?? null,
            'capableMachineIds' => $t->workStep?->machines?->pluck('id')->values()->all() ?? [],
            'updatedAt' => optional($t->updated_at)->toIso8601String(),
            'committed' => true,
        ];
    }

    /** DB → kliens sémára: draft split */
    private function mapSplit(ProductionSplit $s): array
    {
        $partnerName = $s->orderItem?->order?->partner?->name;
        $orderCode   = $s->orderItem?->order?->order_no;
        $productSku  = $s->orderItem?->product?->sku;

        return [
            'id'        => 'split_' . $s->id,
            'resourceId'=> $s->machine_id,
            'title'     => $s->title ?? ($productSku ?: 'Tervezett művelet'),
            'start'     => $s->start?->toIso8601String(),
            'end'       => $s->end?->toIso8601String(),
            'qtyTotal'  => (int)($s->qty_total ?? 0),
            'qtyFrom'   => (int)($s->qty_from ?? 0),
            'qtyTo'     => (int)($s->qty_to ?? 0),
            'ratePph'   => $s->rate_pph !== null ? (float)$s->rate_pph : null,
            'batchSize' => $s->batch_size !== null ? (int)$s->batch_size : self::DEFAULT_BATCH,
            'partnerName'   => $partnerName,
            'orderCode'     => $orderCode,
            'productSku'    => $productSku,
            'operationName' => $s->title,
            'capableMachineIds' => [],
            'updatedAt' => optional($s->updated_at)->toIso8601String(),
            'committed' => false,
        ];
    }

    // ------------------------------------------------------------------
    // Resources – lehet Machine-ből, óvatos fallback-kel
    // ------------------------------------------------------------------
    public function resources(Request $request): JsonResponse
    {
        if (Schema::hasTable('machines')) {
            $q = Machine::query();
            if (Schema::hasColumn('machines','active')) $q->where('active',1);
            $list = $q->orderBy('name')->get(['id','name']);
            $payload = $list->map(fn($m)=>['id'=>(int)$m->id, 'name'=>$m->name, 'group'=>null, 'calendarId'=>1]);
            return response()->json($payload->values());
        }

        // fallback mock (ha még nincs machines tábla)
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

    // ------------------------------------------------------------------
    // Tasks – committed (production_tasks) + draft (production_splits)
    // ------------------------------------------------------------------
    public function tasks(Request $request): JsonResponse
    {
        $from = $request->query('from') ? Carbon::parse($request->query('from')) : now()->startOfDay()->subDays(1);
        $to   = $request->query('to')   ? Carbon::parse($request->query('to'))   : now()->endOfDay()->addDays(3);
        if ($to->lt($from)) [$from, $to] = [$to, $from];

        // committed
        $tasks = ProductionTask::query()
            ->with(['machine:id,name','item:id,sku,name','workStep:id,name','partner:id,name','order:id,order_no','workStep.machines:id'])
            ->whereNotNull('starts_at')->whereNotNull('ends_at')
            ->where('ends_at','>=',$from)->where('starts_at','<=',$to)
            ->orderBy('starts_at')
            ->get()
            ->map(fn($t)=>$this->mapTask($t));

        // draft splits
        $splits = ProductionSplit::query()
            ->with(['orderItem.order.partner','orderItem.product'])
            ->where('is_committed', false)
            ->whereNotNull('start')->whereNotNull('end')
            ->where('end','>=',$from)->where('start','<=',$to)
            ->orderBy('start')
            ->get()
            ->map(fn($s)=>$this->mapSplit($s));

        return response()->json($tasks->concat($splits)->values());
    }

    // ------------------------------------------------------------------
    // Drag/resize utáni mentés – MOSTANTÓL DB-ÍRÁS
    // ------------------------------------------------------------------
    /**
     * PATCH /api/scheduler/schedule/{taskId}
     * Body:
     *   start (ISO), end (ISO), resourceId (int),
     *   ratePph? (float), batchSize? (int), qtyFrom? (int),
     *   title?, partner_order_item_id?
     *
     * $taskId lehet:
     *   - "split_{id}"  → meglévő draft ProductionSplit frissítése
     *   - "new" | "split_new" → új draft ProductionSplit létrehozása
     *   - "{int}"       → committed ProductionTask mozgatása/átméretezése
     */
    public function schedule(string $taskId, Request $request): JsonResponse
    {
         Log::info('schedule() ENTER', [
            'taskId' => $taskId,
            'body'   => $request->all(),
        ]);
        $data = $request->validate([
            'start'      => ['required','date'],
            'end'        => ['required','date','after:start'],
            'resourceId' => ['required','integer'],
            'ratePph'    => ['nullable','numeric','min:0'],
            'batchSize'  => ['nullable','integer','min:1'],
            'qtyFrom'    => ['nullable','integer','min:0'],
            'title'      => ['nullable','string','max:255'],
            'partner_order_item_id' => ['nullable','integer'],
        ]);

        $start = Carbon::parse($data['start'])->seconds(0)->milliseconds(0);
        $end   = Carbon::parse($data['end'])->seconds(0)->milliseconds(0);
        if ($end->diffInMilliseconds($start) < self::MIN_BAR_MS) {
            return response()->json(['error'=>'Túl rövid idősáv.'], 422);
        }

        // ÚJ SPLIT LÉTREHOZÁSA?
        if ($taskId === 'new' || $taskId === 'split_new') {
            $rate  = isset($data['ratePph']) ? (float)$data['ratePph'] : 0.0;
            if ($rate <= 0) {
                return response()->json(['error'=>'Új hasábhoz kötelező a ratePph > 0'], 422);
            }
            $batch = (int)($data['batchSize'] ?? self::DEFAULT_BATCH);
            $qtyFrom = (int)($data['qtyFrom'] ?? 0);
            $qty = $this->calcQty($rate, $start, $end, $batch);

            $payload = [
                'machine_id' => (int)$data['resourceId'],
                'partner_order_item_id' => $data['partner_order_item_id'] ?? null,
                'title'      => $data['title'] ?? null,
                'start'      => $start,
                'end'        => $end,
                'qty_total'  => $qty,
                'qty_from'   => $qtyFrom,
                'qty_to'     => $qtyFrom + $qty,
                'rate_pph'   => $rate,
                'batch_size' => $batch,
                'is_committed' => false,
            ];

            $split = new ProductionSplit();
            // ha a model fillable-je még nincs bővítve, ez is működik:
            $split->forceFill($payload)->save();

            return response()->json([
                'ok'   => true,
                'item' => $this->mapSplit($split),
            ]);
        }

        // MEGLÉVŐ SPLIT FRISSÍTÉSE?
        if (preg_match('/^split_(\d+)$/', $taskId, $m)) {
            $id = (int)$m[1];

            return DB::transaction(function () use ($id, $data, $start, $end) {
                /** @var ProductionSplit $split */
                $split = ProductionSplit::lockForUpdate()->findOrFail($id);

                $rate  = isset($data['ratePph'])   ? (float)$data['ratePph']   : (float)($split->rate_pph ?? 0);
                $batch = isset($data['batchSize']) ? (int)$data['batchSize']   : (int)($split->batch_size ?? self::DEFAULT_BATCH);
                $qtyFrom = isset($data['qtyFrom']) ? (int)$data['qtyFrom']     : (int)($split->qty_from ?? 0);
                $qty = $this->calcQty($rate, $start, $end, $batch);

                $split->forceFill([
                    'machine_id' => (int)$data['resourceId'],
                    'start'      => $start,
                    'end'        => $end,
                    'qty_total'  => $qty,
                    'qty_from'   => $qtyFrom,
                    'qty_to'     => $qtyFrom + $qty,
                    'rate_pph'   => $rate,
                    'batch_size' => $batch,
                    'title'      => $data['title'] ?? $split->title,
                    'partner_order_item_id' => $data['partner_order_item_id'] ?? $split->partner_order_item_id,
                    'is_committed' => false,
                ])->save();

                return response()->json([
                    'ok'   => true,
                    'item' => $this->mapSplit($split->fresh()),
                ]);
            });
        }

        // COMMITTED TASK MOZGATÁSA / ÁTMÉRETEZÉSE
        if (ctype_digit($taskId)) {
            /** @var ProductionTask $task */
            $task = ProductionTask::findOrFail((int)$taskId);
            $task->fill([
                'machine_id' => (int)$data['resourceId'],
                'starts_at'  => $start,
                'ends_at'    => $end,
            ])->save();

            return response()->json([
                'ok'   => true,
                'item' => $this->mapTask($task->fresh()->load(['machine','item','workStep','partner','order','workStep.machines'])),
            ]);
        }

        return response()->json(['error' => 'Ismeretlen task azonosító'], 400);
    }

    // ------------------------------------------------------------------
    // What-if validátor – most még üres (tartható a mock)
    // ------------------------------------------------------------------
    public function validateBatch(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'changes' => ['required', 'array'],
        ]);
        return response()->json(['issues' => []]);
    }

    // ------------------------------------------------------------------
    // Felosztás – egyelőre mock maradhat (később DB műveletre cserélhető)
    // ------------------------------------------------------------------
    public function split(string $task, Request $request): JsonResponse
    {
        $data = $request->validate([
            'splitAt'    => ['required','date'],
            'start'      => ['required','date'],
            'end'        => ['required','date','after:start'],
            'resourceId' => ['required','integer'],
            'minMinutes' => ['nullable','integer','min:1'],
        ]);

        $min = max(15, (int)($data['minMinutes'] ?? 15)); // állítható minimum

        $start = Carbon::parse($data['start'])->seconds(0)->milliseconds(0);
        $end   = Carbon::parse($data['end'])->seconds(0)->milliseconds(0);
        $split = Carbon::parse($data['splitAt'])->seconds(0)->milliseconds(0);

        if ($split->lte($start) || $split->gte($end)) {
            return response()->json(['error' => 'Invalid split point (outside segment)'], 422);
        }
        $leftSec  = $start->diffInSeconds($split);
        $rightSec = $split->diffInSeconds($end);
        if ($leftSec < $min * 60 || $rightSec < $min * 60) {
            return response()->json([
                'error' => "Segments would be too short (min {$min} min). Left=" . floor($leftSec/60) . " min, Right=" . floor($rightSec/60) . " min"
            ], 422);
        }

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

    public function splitQty(string $task, Request $request): JsonResponse
    {
        $data = $request->validate([
            'qtySplit'   => ['required','numeric','min:1'],
            'qtyFrom'    => ['required','numeric'],
            'qtyTo'      => ['required','numeric','gt:qtyFrom'],
            'ratePph'    => ['required','numeric','min:1'],
            'batchSize'  => ['required','numeric','min:1'],
            'resourceId' => ['required','integer'],
            'startISO'   => ['required','date'],
        ]);

        $qtyFrom   = (int)$data['qtyFrom'];
        $qtyTo     = (int)$data['qtyTo'];
        $qtySplit  = (int)$data['qtySplit'];
        $batch     = (int)$data['batchSize'];
        $rate      = (int)$data['ratePph'];
        $start     = Carbon::parse($data['startISO'])->seconds(0)->milliseconds(0);

        $qtySplit = (int) (round($qtySplit / $batch) * $batch);
        if ($qtySplit <= $qtyFrom || $qtySplit >= $qtyTo) {
            return response()->json(['error' => 'Split outside of range'], 422);
        }
        if (($qtySplit - $qtyFrom) < $batch || ($qtyTo - $qtySplit) < $batch) {
            return response()->json(['error' => "Segments would be too short (min {$batch} pcs)"], 422);
        }

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
                    'qtyTotal'   => $qtyTo,
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
