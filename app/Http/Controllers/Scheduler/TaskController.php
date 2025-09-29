<?php

namespace App\Http\Controllers\Scheduler;

use App\Http\Controllers\Controller;
use App\Models\ProductionTask;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    /**
     * Visszaadja a Gantt sávokat a kért idősávban.
     * Most: committed út (production_tasks). A preview (production_splits) későbbi kör lesz.
     *
     * Kérés paraméterek:
     * - from (required, date)
     * - to   (required, date, after:from)
     * - resource_id (optional, int)  → machine_id szűrés
     *
     * Kimenet: a kliens által várt mezők:
     * id, resourceId, title, start, end, qtyTotal, qtyFrom, qtyTo, ratePph, batchSize,
     * partnerName, orderCode, productSku, operationName, capableMachineIds
     */
    public function index(Request $req)
    {
        $req->validate([
            'from'        => ['required', 'date'],
            'to'          => ['required', 'date', 'after:from'],
            'resource_id' => ['nullable', 'integer'],
        ]);

        $q = ProductionTask::query()
            ->with([ 'machine:id,name', 'item:id,sku,name', 'workStep:id,name', 'partner:id,name', 'order:id,order_no', 'workStep.machines:id' ])
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where('ends_at', '>=', $req->date('from'))   // a sáv vége a nézet eleje után legyen
            ->where('starts_at', '<=', $req->date('to'));  // a sáv kezdete a nézet vége előtt legyen

        if ($req->filled('resource_id')) {
            $q->where('machine_id', (int) $req->input('resource_id'));
        }

        $tasks = $q->orderBy('starts_at')->get();

        // Map → a kliens sémájára
        $payload = $tasks->map(function ($t) {
            $capableIds = $t->workStep
                ? $t->workStep->machines->pluck('id')->values()->all()
                : [];

            $qty = (float) ($t->qty ?? 0);

            // ratePph becslés: ha van run_seconds összesen és qty, akkor PPH = qty / (run_seconds / 3600)
            $ratePph = null;
            if (!empty($t->run_seconds) && $t->run_seconds > 0 && $qty > 0) {
                $ratePph = round($qty / ((float) $t->run_seconds / 3600), 2);
            }

            // batchSize: ha nincs külön mező, hagyjuk null-on (később tölthetjük workflow-ból)
            $batchSize = null;

            // Címke – finom fallback: Művelet – SKU/Név
            $itemLabel = $t->item->name ?? $t->item->sku ?? '';
            $opLabel   = $t->workStep->name ?? 'Művelet';
            $title     = trim($opLabel . (strlen($itemLabel) ? ' – ' . $itemLabel : ''));

            return [
                'id'        => $t->id, // marad int → kliens így használja most
                'resourceId'=> $t->machine_id, // marad int → a resource-tree id-jával fed
                'title'     => $title,
                'start'     => optional($t->starts_at)->toIso8601String(),
                'end'       => optional($t->ends_at)->toIso8601String(),

                // a BarsLayer által használt mezők:
                'qtyTotal'  => $qty,
                'qtyFrom'   => 0.0,
                'qtyTo'     => $qty,
                'ratePph'   => $ratePph,
                'batchSize' => $batchSize,

                'partnerName'   => $t->partner->name ?? null,
                'orderCode'     => $t->order->order_no ?? null,
                'productSku'    => $t->item->sku ?? null,
                'operationName' => $t->workStep->name ?? null,

                'capableMachineIds' => $capableIds,
                'updatedAt' => optional($t->updated_at)->toIso8601String(),
            ];
        });

        return response()->json($payload);
    }

    /**
     * Új committed feladat létrehozása (production_tasks).
     */
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

        // kompat: ha workflow_step_id érkezik, töltsük item_work_step_id-be
        if (empty($data['item_work_step_id']) && !empty($data['workflow_step_id'])) {
            $data['item_work_step_id'] = (int) $data['workflow_step_id'];
        }
        unset($data['workflow_step_id']);

        $task = ProductionTask::create($data);

        return response()->json(['id' => $task->id], 201);
    }

    /**
     * Részleges módosítás (státusz / megjegyzés / qty / step).
     */
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
            $data['item_work_step_id'] = (int) $r->input('workflow_step_id');
            unset($data['workflow_step_id']);
        }

        $task->update($data);

        return response()->noContent();
    }

    /**
     * Mozgatás (gép és/vagy időablak változik) – optimista lock-kal.
     */
    public function move(Request $r, ProductionTask $task)
    {
        $data = $r->validate([
            'machine_id' => ['nullable', 'integer'],
            'starts_at'  => ['required', 'date'],
            'ends_at'    => ['required', 'date', 'after:starts_at'],
            'updated_at' => ['required', 'date'], // optimista lock
        ]);

        abort_if($task->updated_at->ne($r->date('updated_at')), 409, 'A feladat időközben módosult.');

        $task->fill($data)->save();

        return response()->noContent();
    }

    /**
     * Időablak átméretezése – optimista lock-kal.
     */
    public function resize(Request $r, ProductionTask $task)
    {
        $data = $r->validate([
            'starts_at'  => ['required', 'date'],
            'ends_at'    => ['required', 'date', 'after:starts_at'],
            'updated_at' => ['required', 'date'],
        ]);

        abort_if($task->updated_at->ne($r->date('updated_at')), 409, 'A feladat időközben módosult.');

        $task->update($data);

        return response()->noContent();
    }

    /**
     * Törlés.
     */
    public function destroy(ProductionTask $task)
    {
        $task->delete();

        return response()->noContent();
    }
}
