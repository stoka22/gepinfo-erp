<?php

namespace App\Http\Controllers\Scheduler;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\ProductionTask;
use App\Models\ProductionSplit;

class TaskController extends Controller
{
    /**
     * Return bars between from..to in the expected shape.
     * For simplicity this example returns committed tasks + uncommitted splits.
     */
    public function index(Request $r)
    {
        $r->validate([
            'from' => ['required','date'],
            'to'   => ['required','date','after:from'],
            'resource_id' => ['nullable','integer'],
        ]);

        $from = $r->date('from');
        $to   = $r->date('to');
        $machineId = $r->integer('resource_id') ?: null;

        $tasks = ProductionTask::query()
            ->when($machineId, fn($q) => $q->where('machine_id', $machineId))
            ->where(function($q) use ($from, $to) {
                $q->whereBetween('start', [$from, $to])
                  ->orWhereBetween('end', [$from, $to])
                  ->orWhere(function($qq) use ($from, $to) {
                      $qq->where('start','<',$from)->where('end','>',$to);
                  });
            })
            ->get();

        $splits = ProductionSplit::query()
            ->when($machineId, fn($q) => $q->where('machine_id', $machineId))
            ->where('is_committed', false)
            ->where(function($q) use ($from, $to) {
                $q->whereBetween('start', [$from, $to])
                  ->orWhereBetween('end', [$from, $to])
                  ->orWhere(function($qq) use ($from, $to) {
                      $qq->where('start','<',$from)->where('end','>',$to);
                  });
            })
            ->get();

        $map = fn($t) => [
            'id' => 'task_'.$t->id,
            'resourceId' => $t->machine_id,
            'title' => $t->title,
            'start' => $t->start->toIso8601String(),
            'end'   => $t->end->toIso8601String(),
            'qtyTotal' => (int)($t->qty_total ?? 0),
            'qtyFrom'  => (int)($t->qty_from ?? 0),
            'qtyTo'    => (int)($t->qty_to ?? 0),
            'ratePph'  => (float)($t->rate_pph ?? 0),
            'batchSize'=> (int)($t->batch_size ?? 100),
            'partnerName' => $t->partner_name,
            'orderCode'   => $t->order_code,
            'productSku'  => $t->product_sku,
            'operationName' => $t->operation_name,
            'capableMachineIds' => [],
            'committed' => true,
        ];

        $mapSplit = fn($s) => [
            'id' => 'split_'.$s->id,
            'resourceId' => $s->machine_id,
            'title' => $s->title,
            'start' => $s->start->toIso8601String(),
            'end'   => $s->end->toIso8601String(),
            'qtyTotal' => (int)($s->qty_total ?? 0),
            'qtyFrom'  => (int)($s->qty_from ?? 0),
            'qtyTo'    => (int)($s->qty_to ?? 0),
            'ratePph'  => (float)($s->rate_pph ?? 0),
            'batchSize'=> (int)($s->batch_size ?? 100),
            'partnerName' => $s->partner_name,
            'orderCode'   => $s->order_code,
            'productSku'  => $s->product_sku,
            'operationName' => $s->operation_name,
            'capableMachineIds' => [],
            'committed' => false,
        ];

        return response()->json([
            'items' => array_values(
                array_merge(
                    $tasks->map($map)->all(),
                    $splits->map($mapSplit)->all()
                )
            )
        ]);
    }

    /**
     * Create or update a draft split.
     * Qty is recomputed from duration * rate_pph, snapped to 100s (batch_size).
     */
    public function storeSplit(Request $r)
    {
        $data = $r->validate([
            'id'         => ['nullable','string'],
            'machine_id' => ['required','integer','exists:machines,id'],
            'title'      => ['nullable','string','max:255'],
            'start'      => ['required','date'],
            'end'        => ['required','date','after:start'],
            'ratePph'    => ['required','numeric','min:0'],
            'batchSize'  => ['nullable','integer','min:1'],
            'qtyFrom'    => ['nullable','integer','min:0'],
        ]);

        $batch = $data['batchSize'] ?? 100;
        $rate  = (float) $data['ratePph'];
        $start = new \Carbon\Carbon($data['start']);
        $end   = new \Carbon\Carbon($data['end']);
        $hours = max(0.0, $start->floatDiffInRealHours($end));

        $rawQty = (int) floor($hours * $rate);
        // snap to batch size (100s default)
        $snapped = (int) (floor($rawQty / $batch) * $batch);
        if ($snapped < $batch && $rawQty > 0) {
            $snapped = $batch; // at least one batch if any work
        }

        $payload = [
            'machine_id' => $data['machine_id'],
            'title'      => $data['title'] ?? null,
            'start'      => $start,
            'end'        => $end,
            'qty_total'  => $snapped,
            'qty_from'   => $data['qtyFrom'] ?? 0,
            'qty_to'     => $snapped + ($data['qtyFrom'] ?? 0),
            'rate_pph'   => $rate,
            'batch_size' => $batch,
            'is_committed' => false,
            'created_by' => $r->user()?->id,
            'updated_by' => $r->user()?->id,
        ];

        DB::beginTransaction();
        try {
            if (!empty($data['id']) && str_starts_with($data['id'], 'split_')) {
                $id = (int) str_replace('split_', '', $data['id']);
                $split = ProductionSplit::lockForUpdate()->findOrFail($id);
                $split->fill($payload)->save();
            } else {
                $split = ProductionSplit::create($payload);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'item' => [
                'id' => 'split_'.$split->id,
                'resourceId' => $split->machine_id,
                'title' => $split->title,
                'start' => $split->start->toIso8601String(),
                'end'   => $split->end->toIso8601String(),
                'qtyTotal' => (int)$split->qty_total,
                'qtyFrom'  => (int)$split->qty_from,
                'qtyTo'    => (int)$split->qty_to,
                'ratePph'  => (float)$split->rate_pph,
                'batchSize'=> (int)$split->batch_size,
                'committed'=> false,
            ]
        ]);
    }

    /**
     * Return occupied time ranges (committed tasks + uncommitted splits) for a machine.
     */
    public function occupancy(Request $r)
    {
        $r->validate([
            'from' => ['required','date'],
            'to'   => ['required','date','after:from'],
            'resource_id' => ['required','integer'],
        ]);
        $from = $r->date('from');
        $to   = $r->date('to');
        $machineId = $r->integer('resource_id');

        $ranges = [];

        $q1 = ProductionTask::query()->where('machine_id', $machineId);
        $q2 = ProductionSplit::query()->where('machine_id', $machineId);

        foreach ($q1->whereBetween('start', [$from, $to])->orWhereBetween('end', [$from,$to])->get() as $t) {
            $ranges[] = ['start' => $t->start->toIso8601String(), 'end' => $t->end->toIso8601String()];
        }
        foreach ($q2->where('is_committed', false)->where(function($q) use ($from,$to){
            $q->whereBetween('start', [$from, $to])->orWhereBetween('end', [$from,$to]);
        })->get() as $s) {
            $ranges[] = ['start' => $s->start->toIso8601String(), 'end' => $s->end->toIso8601String()];
        }

        return response()->json(['ranges' => $ranges]);
    }

    /**
     * Example commit endpoint to move splits to tasks.
     */
    public function commit(Request $r)
    {
        $ids = $r->input('splitIds', []);
        if (!is_array($ids) || empty($ids)) {
            return response()->json(['ok' => true, 'moved' => 0]);
        }

        $splits = ProductionSplit::whereIn('id', $ids)->get();
        $count = 0;
        DB::transaction(function () use ($splits, &$count) {
            foreach ($splits as $s) {
                $t = new ProductionTask();
                $t->machine_id = $s->machine_id;
                $t->title = $s->title;
                $t->start = $s->start;
                $t->end   = $s->end;
                $t->qty_total = $s->qty_total;
                $t->qty_from  = $s->qty_from;
                $t->qty_to    = $s->qty_to;
                $t->rate_pph  = $s->rate_pph;
                $t->batch_size= $s->batch_size;
                $t->partner_name = $s->partner_name;
                $t->order_code   = $s->order_code;
                $t->product_sku  = $s->product_sku;
                $t->operation_name = $s->operation_name;
                $t->save();

                $s->is_committed = true;
                $s->save();
                $count++;
            }
        });

        return response()->json(['ok' => true, 'moved' => $count]);
    }
}
