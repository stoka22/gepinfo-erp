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
        ]);

        $from = Carbon::parse($req->input('from'));
        $to   = Carbon::parse($req->input('to'));

        // --- Committed (production_tasks) ---
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
            $capableIds = $t->workStep
                ? $t->workStep->machines->pluck('id')->values()->all()
                : [];

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
                'capableMachineIds' => $capableIds,
                'updatedAt' => optional($t->updated_at)->toIso8601String(),
                'committed' => true,
            ];
        });

        // --- Draft (production_splits) ---
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

            return [
                'id'        => 'split_' . $s->id, // STRING, ne ütközzön
                'resourceId'=> $s->machine_id,
                'title'     => $s->title ?? ($productSku ?: 'Tervezett művelet'),
                'start'     => optional($s->start)->toIso8601String(),
                'end'       => optional($s->end)->toIso8601String(),
                'qtyTotal'  => (int)($s->qty_total ?? 0),
                'qtyFrom'   => (int)($s->qty_from ?? 0),
                'qtyTo'     => (int)($s->qty_to ?? 0),
                'ratePph'   => $s->rate_pph !== null ? (float)$s->rate_pph : null,
                'batchSize' => $s->batch_size !== null ? (int)$s->batch_size : 100,
                'partnerName'   => $partnerName,
                'orderCode'     => $orderCode,
                'productSku'    => $productSku,
                'operationName' => $s->title,
                'capableMachineIds' => [],
                'updatedAt' => optional($s->updated_at)->toIso8601String(),
                'committed' => false,
            ];
        });

        $payload = $committedPayload->concat($draftPayload)->values();

        // Biztonságos logolás (mindig tömb a context!)
        Log::info('scheduler.index splits', [
            'draft_count' => $splits->count(),
            'draft_sample'=> $splits->take(3)->toArray(),
            'committed_count' => $tasks->count(),
        ]);

        return response()->json($payload);
    }

    /** Új committed task létrehozása. */
    public function store(Request $r)
    {
        $data = $r->validate([
            'partner_id'            => ['required', 'integer'],
            'partner_order_id'      => ['required', 'integer'],
            'partner_order_item_id' => ['required', 'integer'],
            'item_id'               => ['required', 'integer'],
            'item_work_step_id'     => ['nullable', 'integer'],
            'workflow_id'           => ['nullable', 'integer'],
            'workflow_step_id'      => ['nullable', 'integer'],
            'machine_id'            => ['nullable', 'integer'],
            'qty'                   => ['required', 'numeric'],
            'setup_seconds'         => ['nullable', 'integer'],
            'run_seconds'           => ['nullable', 'integer'],
            'starts_at'             => ['required', 'date'],
            'ends_at'               => ['required', 'date', 'after:starts_at'],
            'status'                => ['nullable', Rule::in(['planned','in_progress','done','blocked','canceled'])],
            'note'                  => ['nullable', 'string'],
        ]);

        if (empty($data['item_work_step_id']) && !empty($data['workflow_step_id'])) {
            $data['item_work_step_id'] = (int)$data['workflow_step_id'];
        }
        unset($data['workflow_step_id']);

        $task = ProductionTask::create($data);
        return response()->json(['id' => $task->id], 201);
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
            'id'         => ['nullable', 'string'],              // "split_{id}" vagy üres
            'machine_id' => ['required', 'integer', 'exists:machines,id'],
            'partner_order_item_id' => ['nullable', 'integer', 'exists:partner_order_items,id'],
            'title'      => ['nullable', 'string', 'max:255'],
            'start'      => ['required', 'date'],
            'end'        => ['required', 'date', 'after:start'],
            'ratePph'    => ['required', 'numeric', 'min:0'],
            'batchSize'  => ['nullable', 'integer', 'min:1'],
            'qtyFrom'    => ['nullable', 'integer', 'min:0'],
        ]);

        $batch = (int)($data['batchSize'] ?? 100);
        $rate  = (float)$data['ratePph'];
        $start = Carbon::parse($data['start'])->seconds(0)->milliseconds(0);
        $end   = Carbon::parse($data['end'])->seconds(0)->milliseconds(0);

        $seconds = max(0, $end->diffInSeconds($start)); // stabil minden verzión
        $hours   = $seconds / 3600.0;
        $raw     = (int)floor($hours * $rate);
        $qty     = $raw <= 0 ? 0 : max($batch, (int)(floor($raw / $batch) * $batch));

        $payload = [
            'machine_id' => (int)$data['machine_id'],
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

        DB::beginTransaction();
        try {
            if (!empty($data['id']) && str_starts_with($data['id'], 'split_')) {
                $id = (int)substr($data['id'], 6);
                $split = ProductionSplit::lockForUpdate()->findOrFail($id);
                $split->fill($payload)->save();
            } else {
                $split = ProductionSplit::create($payload);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('storeSplit failed', ['msg' => $e->getMessage()]);
            return response()->json(['error' => 'Split save failed: '.$e->getMessage()], 422);
        }

        $partnerName = $split->orderItem?->order?->partner?->name;
        $orderCode   = $split->orderItem?->order?->order_no;
        $productSku  = $split->orderItem?->product?->sku;

        Log::info('storeSplit OK', ['id' => $split->id, 'qty_total' => $split->qty_total]);

        return response()->json([
            'ok'   => true,
            'item' => [
                'id'           => 'split_'.$split->id,
                'resourceId'   => $split->machine_id,
                'title'        => $split->title ?? ($productSku ?: 'Tervezett művelet'),
                'start'        => $split->start->toIso8601String(),
                'end'          => $split->end->toIso8601String(),
                'qtyTotal'     => (int)$split->qty_total,
                'qtyFrom'      => (int)$split->qty_from,
                'qtyTo'        => (int)$split->qty_to,
                'ratePph'      => (float)$split->rate_pph,
                'batchSize'    => (int)$split->batch_size,
                'partnerName'  => $partnerName,
                'orderCode'    => $orderCode,
                'productSku'   => $productSku,
                'operationName'=> $split->title,
                'committed'    => false,
            ],
        ]);
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
}
