<?php

namespace App\Http\Controllers\Scheduler;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\ProductionTask;
use App\Models\ProductionSplit;
use Illuminate\Support\Carbon;

class TaskController extends Controller
{
    /**
     * Gantt sávok a kért idősávban (committed + draft).
     * Kérés: from, to (date), resource_id (opcionális).
     */
    public function index(Request $req)
    {
        $req->validate([
            'from'        => ['required', 'date'],
            'to'          => ['required', 'date', 'after:from'],
            'resource_id' => ['nullable', 'integer'],
            'with_totals' => ['nullable'],
        ]);

        $from = Carbon::parse($req->input('from'));
        $to   = Carbon::parse($req->input('to'));

        // --- committed ---
        $q = ProductionTask::query()
            ->with([
                'machine:id,name',
                'item:id,sku,name',
                'workStep:id,name',
                'partner:id,name',
                'order:id,order_no',
                'workStep.machines:id',
            ])
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where('ends_at', '>=', $from)
            ->where('starts_at', '<=', $to);

        if ($req->filled('resource_id')) {
            $q->where('machine_id', (int)$req->input('resource_id'));
        }

        $tasks = $q->orderBy('starts_at')->get();

        $committedPayload = $tasks->map(function ($t) {
            $capableIds = $t->workStep ? $t->workStep->machines->pluck('id')->values()->all() : [];

            $qty = (float)($t->qty ?? 0);

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
                'start'     => optional($t->starts_at)->format('Y-m-d\TH:i:s'),
                'end'       => optional($t->ends_at)->format('Y-m-d\TH:i:s'),
                'qtyTotal'  => $qty,
                'qtyFrom'   => 0.0,
                'qtyTo'     => $qty,
                'ratePph'   => $ratePph,
                'batchSize' => null,
                'productNodeId' => $t->item_id,          // ⬅️ termék id, amivel a tree 'product' node egyezik
                'processNodeId' => $t->work_step_id,     // ⬅️ ha van ilyen
                'productSku'    => $t->item->sku ?? null,
                'productName'   => $t->item->name ?? null,
                'partnerName'   => $t->partner->name ?? null,
                'orderCode'     => $t->order->order_no ?? null,
                
                'operationName' => $t->workStep->name ?? null,
                'capableMachineIds' => $capableIds,
                'updatedAt' => optional($t->updated_at)->toIso8601String(),
                'committed' => true,
            ];
        });

        // --- draft ---
        $sq = ProductionSplit::query()
            ->with(['orderItem.order.partner', 'orderItem.product'])
            ->where('is_committed', false)
            ->whereNotNull('start')->whereNotNull('end')
            ->where('end', '>=', $from)
            ->where('start', '<=', $to);

        if ($req->filled('resource_id')) {
            $sq->where('machine_id', (int)$req->input('resource_id'));
        }

        $splits = $sq->orderBy('start')->get();

        $draftPayload = $splits->map(function (ProductionSplit $s) {
            $partnerName = $s->orderItem?->order?->partner?->name;
            $orderCode   = $s->orderItem?->order?->order_no;
            $productSku  = $s->orderItem?->product?->sku;
            $product = $s->orderItem?->product;
            $processId = $s->orderItem?->workflow_step_id;

            return [
                'id'        => 'split_' . $s->id,
                'resourceId'=> $s->machine_id,
                'title'     => $s->title ?? ($productSku ?: 'Tervezett művelet'),
                'start'     => optional($s->start)->format('Y-m-d\TH:i:s'),
                'end'       => optional($s->end)->format('Y-m-d\TH:i:s'),
                'qtyTotal'  => (int)($s->qty_total ?? 0),
                'qtyFrom'   => (int)($s->qty_from ?? 0),
                'qtyTo'     => (int)($s->qty_to ?? 0),
                'ratePph'   => $s->rate_pph !== null ? (float)$s->rate_pph : null,
                'batchSize' => $s->batch_size !== null ? (int)$s->batch_size : null,
                'partnerName'   => $partnerName,
                'orderCode'     => $orderCode,
                'productSku'    => $productSku,
                'operationName' => $s->title,
                'capableMachineIds' => [],
                'updatedAt' => optional($s->updated_at)->toIso8601String(),
                'committed' => false,
                'productNodeId' => $product?->id,       // ⬅️
                'processNodeId' => $processId,          // ⬅️
                'productSku'    => $product?->sku,
                'productName'   => $product?->name, 
            ];
        });

        $items  = $committedPayload->concat($draftPayload)->values();

        // totals erőforrásonként
        $totals = [];
        foreach ($items as $it) {
            $rid = (int)$it['resourceId'];
            $q   = (int)($it['qtyTotal'] ?? 0);
            $totals[$rid] = ($totals[$rid] ?? 0) + $q;
        }

        if ($req->boolean('with_totals')) {
            return response()->json(['items' => $items, 'totals' => $totals]);
        }

        return response()
            ->json($items)
            ->header('X-Scheduler-ResourceTotals', json_encode($totals));
    }


    /** Új committed task létrehozása. */
    public function store(Request $r)
    {
        $data = $r->validate([
            'id'         => ['nullable','string'],
            'machine_id' => ['required','integer','exists:machines,id'],
            'partner_order_item_id' => ['nullable','integer','exists:partner_order_items,id'],
            'title'      => ['nullable','string','max:255'],
            'start'      => ['required','date'],
            'end'        => ['required','date','after:start'],
            'ratePph'    => ['required','numeric','min:0'],
            'batchSize'  => ['nullable','integer','min:1'],
            'qtyFrom'    => ['nullable','integer','min:0'],
        ]);

        $rid   = (int)$data['machine_id'];
        $rate  = (float)$data['ratePph'];
        $batch = $data['batchSize'] ?? null;

        // ⬅️ NEM vágjuk le a másodperceket
        $start = Carbon::parse($data['start']);
        $end   = Carbon::parse($data['end']);
        $durSec = max(60, $end->diffInSeconds($start)); // min. 60s

        DB::beginTransaction();
        try {
            // --- Ütközésvizsgálat ugyanazon a gépen (szélérintkezés megengedett) ---
            $overlap = function(Carbon $s, Carbon $e) use ($rid) {
                $has = ProductionTask::query()
                    ->where('machine_id', $rid)
                    ->whereNotNull('starts_at')->whereNotNull('ends_at')
                    ->where(function($q) use ($s,$e){
                        $q->where('ends_at','>', $s)->where('starts_at','<', $e);
                    })
                    ->exists();

                if (!$has) {
                    $has = ProductionSplit::query()
                        ->where('machine_id', $rid)->where('is_committed', false)
                        ->whereNotNull('start')->whereNotNull('end')
                        ->where(function($q) use ($s,$e){
                            $q->where('end','>', $s)->where('start','<', $e);
                        })
                        ->exists();
                }
                return $has;
            };

            // ha ütközik → kérjünk azonnal új szabad slotot és oda tegyük
            if ($overlap($start, $end)) {
                // egyszerű next-slot: lépegessünk a következő szabad helyre
                [$s, $e] = $this->computeNextSlot($rid, $start, $durSec);
                $start = $s; $end = $e;
            }

            // darabszám számolása a VÉGSŐ tartamból
            $hours  = $durSec / 3600.0;
            $rawQty = (int)floor($hours * $rate);
            $qty    = $rawQty <= 0 ? 0 : ($batch ? (int)floor($rawQty / $batch) * $batch : $rawQty);

            $payload = [
                'machine_id' => $rid,
                'partner_order_item_id' => $data['partner_order_item_id'] ?? null,
                'title'      => $data['title'] ?? null,
                'start'      => $start,
                'end'        => $end,
                'qty_total'  => $qty,
                'qty_from'   => (int)($data['qtyFrom'] ?? 0),
                'qty_to'     => (int)($data['qtyFrom'] ?? 0) + $qty,
                'rate_pph'   => $rate,
                'batch_size' => $batch,
                'is_committed' => false,
            ];

            if (!empty($data['id']) && str_starts_with($data['id'], 'split_')) {
                $id    = (int)substr($data['id'], 6);
                $split = ProductionSplit::lockForUpdate()->findOrFail($id);
                $split->fill($payload)->save();
            } else {
                $split = ProductionSplit::create($payload);
            }
            DB::commit();

            return response()->json([
                'ok'   => true,
                'item' => [
                    'id'           => 'split_'.$split->id,
                    'resourceId'   => $split->machine_id,
                    'title'        => $split->title ?? 'Tervezett művelet',
                    'start'        => $split->start->toIso8601String(),
                    'end'          => $split->end->toIso8601String(),
                    'qtyTotal'     => (int)$split->qty_total,
                    'qtyFrom'      => (int)$split->qty_from,
                    'qtyTo'        => (int)$split->qty_to,
                    'ratePph'      => (float)$split->rate_pph,
                    'batchSize'    => $split->batch_size !== null ? (int)$split->batch_size : null,
                    'committed'    => false,
                    'productNodeId' => $split->partner_order_item_id
                        ? optional($split->orderItem->product)->id
                        : null,
                    'processNodeId' => $split->partner_order_item_id
                        ? $split->orderItem->workflow_step_id
                        : null,
                    'productSku'    => optional($split->orderItem->product)->sku,
                    'productName'   => optional($split->orderItem->product)->name,
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => 'Split save failed: '.$e->getMessage()], 422);
        }
    }

    /** Részleges módosítás committed taskon. */
    public function update(Request $r, ProductionTask $task)
    {
        $data = $r->validate([
            'item_work_step_id' => ['sometimes', 'nullable', 'integer'],
            'workflow_step_id'  => ['sometimes', 'nullable', 'integer'],
            'qty'               => ['sometimes', 'numeric'],
            'status'            => ['sometimes', Rule::in(['planned','in_progress','done','blocked','canceled'])],
            'note'              => ['sometimes', 'string', 'nullable'],
        ]);

        if (!isset($data['item_work_step_id']) && $r->filled('workflow_step_id')) {
            $data['item_work_step_id'] = (int)$r->input('workflow_step_id');
            unset($data['workflow_step_id']);
        }

        $task->update($data);
        return response()->noContent();
    }

    /** Mozgatás (optimista lock). */
    public function move(Request $r, ProductionTask $task)
    {
        $data = $r->validate([
            'machine_id' => ['nullable', 'integer'],
            'starts_at'  => ['required', 'date'],
            'ends_at'    => ['required', 'date', 'after:starts_at'],
            'updated_at' => ['required', 'date'],
        ]);

        if ($task->updated_at->ne(Carbon::parse($r->input('updated_at')))) {
            abort(409, 'A feladat időközben módosult.');
        }

        $task->fill($data)->save();
        return response()->noContent();
    }

    /** Átméretezés (optimista lock). */
    public function resize(Request $r, ProductionTask $task)
    {
        $data = $r->validate([
            'starts_at'  => ['required', 'date'],
            'ends_at'    => ['required', 'date', 'after:starts_at'],
            'updated_at' => ['required', 'date'],
        ]);

        if ($task->updated_at->ne(Carbon::parse($r->input('updated_at')))) {
            abort(409, 'A feladat időközben módosult.');
        }

        $task->update($data);
        return response()->noContent();
    }

    /** Törlés. */
    public function destroy(ProductionTask $task)
    {
        $task->delete();
        return response()->noContent();
    }

    /**
     * Draft split mentése/létrehozása (production_splits).
     * A mennyiséget (qty_total) az időtartam * rate_pph alapján számoljuk, batch-re kerekítve.
     */
    public function storeSplit(Request $r)
    {
        $data = $r->validate([
            'id'         => ['nullable','string'],
            'machine_id' => ['required','integer','exists:machines,id'],
            'partner_order_item_id' => ['nullable','integer','exists:partner_order_items,id'],
            'title'      => ['nullable','string','max:255'],
            'start'      => ['required','date'],
            'end'        => ['required','date','after:start'],
            'ratePph'    => ['required','numeric','min:0'],
            'batchSize'  => ['nullable','integer','min:1'],
            'qtyFrom'    => ['nullable','integer','min:0'],
        ]);

        $rid   = (int)$data['machine_id'];
        $rate  = (float)$data['ratePph'];
        $batch = $data['batchSize'] ?? null;

        // ⬅️ NEM vágjuk le a másodperceket
        $start = Carbon::parse($data['start']);
        $end   = Carbon::parse($data['end']);
        $durSec = max(60, $end->diffInSeconds($start)); // min. 60s

        DB::beginTransaction();
        try {
            // --- Ütközésvizsgálat ugyanazon a gépen (szélérintkezés megengedett) ---
            $overlap = function(Carbon $s, Carbon $e) use ($rid) {
                $has = ProductionTask::query()
                    ->where('machine_id', $rid)
                    ->whereNotNull('starts_at')->whereNotNull('ends_at')
                    ->where(function($q) use ($s,$e){
                        $q->where('ends_at','>', $s)->where('starts_at','<', $e);
                    })
                    ->exists();

                if (!$has) {
                    $has = ProductionSplit::query()
                        ->where('machine_id', $rid)->where('is_committed', false)
                        ->whereNotNull('start')->whereNotNull('end')
                        ->where(function($q) use ($s,$e){
                            $q->where('end','>', $s)->where('start','<', $e);
                        })
                        ->exists();
                }
                return $has;
            };

            // ha ütközik → kérjünk azonnal új szabad slotot és oda tegyük
            if ($overlap($start, $end)) {
                // egyszerű next-slot: lépegessünk a következő szabad helyre
                [$s, $e] = $this->computeNextSlot($rid, $start, $durSec);
                $start = $s; $end = $e;
            }

            // darabszám számolása a VÉGSŐ tartamból
            $hours  = $durSec / 3600.0;
            $rawQty = (int)floor($hours * $rate);
            $qty    = $rawQty <= 0 ? 0 : ($batch ? (int)floor($rawQty / $batch) * $batch : $rawQty);

            $payload = [
                'machine_id' => $rid,
                'partner_order_item_id' => $data['partner_order_item_id'] ?? null,
                'title'      => $data['title'] ?? null,
                'start'      => $start,
                'end'        => $end,
                'qty_total'  => $qty,
                'qty_from'   => (int)($data['qtyFrom'] ?? 0),
                'qty_to'     => (int)($data['qtyFrom'] ?? 0) + $qty,
                'rate_pph'   => $rate,
                'batch_size' => $batch,
                'is_committed' => false,
            ];

            if (!empty($data['id']) && str_starts_with($data['id'], 'split_')) {
                $id    = (int)substr($data['id'], 6);
                $split = ProductionSplit::lockForUpdate()->findOrFail($id);
                $split->fill($payload)->save();
            } else {
                $split = ProductionSplit::create($payload);
            }
            DB::commit();

            return response()->json([
                'ok'   => true,
                'item' => [
                    'id'           => 'split_'.$split->id,
                    'resourceId'   => $split->machine_id,
                    'title'        => $split->title ?? 'Tervezett művelet',
                    'start'        => $split->start->toIso8601String(),
                    'end'          => $split->end->toIso8601String(),
                    'qtyTotal'     => (int)$split->qty_total,
                    'qtyFrom'      => (int)$split->qty_from,
                    'qtyTo'        => (int)$split->qty_to,
                    'ratePph'      => (float)$split->rate_pph,
                    'batchSize'    => $split->batch_size !== null ? (int)$split->batch_size : null,
                    'committed'    => false,
                ],
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => 'Split save failed: '.$e->getMessage()], 422);
        }
    }

    /**
     * Foglaltság (committed + draft) egy gépre.
     * GET /api/scheduler/occupancy?from=...&to=...&resource_id=...
     */
    public function occupancy(Request $r)
    {
        $r->validate([
            'from' => ['required','date'],
            'to'   => ['required','date','after:from'],
            'resource_id' => ['required','integer'],
        ]);

        $from = Carbon::parse($r->input('from'));
        $to   = Carbon::parse($r->input('to'));
        $mid  = (int)$r->input('resource_id');

        $committed = ProductionTask::query()
            ->where('machine_id', $mid)
            ->whereNotNull('starts_at')->whereNotNull('ends_at')
            ->where('ends_at', '>=', $from)
            ->where('starts_at', '<=', $to)
            ->get()
            ->map(fn($t)=>[
                'start'=>$t->starts_at->toIso8601String(),
                'end'  =>$t->ends_at->toIso8601String()
            ]);

        $draft = ProductionSplit::query()
            ->where('machine_id', $mid)
            ->where('is_committed', false)
            ->whereNotNull('start')->whereNotNull('end')
            ->where('end', '>=', $from)
            ->where('start', '<=', $to)
            ->get()
            ->map(fn($s)=>[
                'start'=>$s->start->toIso8601String(),
                'end'  =>$s->end->toIso8601String()
            ]);

        return response()->json(['ranges' => $committed->concat($draft)->values()]);
    }

    public function destroySplit(ProductionSplit $split)
    {
        if ($split->is_committed) abort(400,'Committed split nem törölhető ezen az endpointon.');
        $split->delete();
        return response()->noContent();
    }

     /**
     * Következő szabad idősáv egy gépen az adott hosszal.
     * GET /api/scheduler/next-slot?resource_id=..&from=..&seconds=..
     */
    private function computeNextSlot(int $rid, Carbon $from, int $durSec): array
    {
        $ranges = collect()
            ->merge(
                ProductionTask::query()
                ->where('machine_id',$rid)
                ->whereNotNull('starts_at')->whereNotNull('ends_at')
                ->where('ends_at','>=',$from)
                ->get(['starts_at as start','ends_at as end'])
            )
            ->merge(
                ProductionSplit::query()
                ->where('machine_id',$rid)->where('is_committed',false)
                ->whereNotNull('start')->whereNotNull('end')
                ->where('end','>=',$from)
                ->get(['start','end'])
            )
            ->sortBy('start')
            ->values();

        $cursor = (clone $from);
        foreach ($ranges as $rng) {
            $s = Carbon::parse($rng->start);
            $e = Carbon::parse($rng->end);
            // szélérintkezés megengedett
            if ($cursor->copy()->addSeconds($durSec)->lte($s)) {
                return [$cursor->copy(), $cursor->copy()->addSeconds($durSec)];
            }
            if ($e->gt($cursor)) $cursor = $e->copy();
        }
        return [$cursor->copy(), $cursor->copy()->addSeconds($durSec)];
    }

    // /api/scheduler/next-slot – használja ugyanazt a logikát
    public function nextSlot(Request $r)
    {
        $r->validate([
            'resource_id' => ['required','integer','exists:machines,id'],
            'from'        => ['required','date'],
            'seconds'     => ['required','integer','min:60']
        ]);
        [$s, $e] = $this->computeNextSlot(
            (int)$r->input('resource_id'),
            Carbon::parse($r->input('from')),
            (int)$r->input('seconds')
        );
        return response()->json([
            'start' => $s->toIso8601String(),
            'end'   => $e->toIso8601String(),
        ]);
    }
}
