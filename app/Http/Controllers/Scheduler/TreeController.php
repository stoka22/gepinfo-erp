<?php

namespace App\Http\Controllers\Scheduler;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\ItemWorkStep;
use App\Models\Machine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TreeController extends Controller
{
    public function index(Request $req)
    {
        // Opcionális időablak – most csak átvezetjük a kompatibilitás miatt
        $from = $req->date('from');
        $to   = $req->date('to');

        // --- 1) Partnerek + megrendelések + tételek előtöltése (csak szükséges oszlopok) ---
        $partners = Partner::query()
            ->select(['id', 'name'])
            ->with([
                'orders' => function ($q) {
                    $q->select(['id', 'partner_id', 'order_no']);
                },
                'orders.items' => function ($q) {
                    $q->select(['id', 'partner_order_id', 'item_id', 'qty_ordered']);
                },
                'orders.items.item' => function ($q) {
                    $q->select(['id', 'sku', 'name']);
                },
            ])
            ->limit(200)
            ->get();

        // Gyors kilépés, ha nincs adat
        if ($partners->isEmpty()) {
            return response()->json([]);
        }

        // --- 2) Érintett item_id-k összegyűjtése a tömeges betöltésekhez ---
        $itemIds = [];
        foreach ($partners as $p) {
            foreach ($p->orders as $o) {
                foreach ($o->items as $oi) {
                    if ($oi->item) {
                        $itemIds[] = $oi->item->id;
                    }
                }
            }
        }
        $itemIds = array_values(array_unique($itemIds));
        if (empty($itemIds)) {
            return response()->json([]);
        }

        // --- 3) Lépések előtöltése MINDEN érintett itemhez (gépekkel együtt), egyetlen query-vel ---
        $hasIsActive = Schema::hasColumn('item_work_steps', 'is_active');
        $stepsByItem = ItemWorkStep::query()
            ->with(['machines:id,name'])
            ->whereIn('item_id', $itemIds)
            ->when($hasIsActive, fn ($q) => $q->where('is_active', 1))
            ->orderBy('step_no')
            ->get()
            ->groupBy('item_id');

        // --- 4) item_machine pivot előtöltése fallback-hez (szintén egyszerre) ---
        $pivot = DB::table('item_machine')
            ->select(['item_id', 'machine_id'])
            ->whereIn('item_id', $itemIds)
            ->get();

        $pivotByItem = [];
        foreach ($pivot as $row) {
            $pivotByItem[$row->item_id][] = (int) $row->machine_id;
        }

        // Ha valamelyik termékhez se lépés, se pivot nincs, akkor globális aktív géplistát is kérhetünk előre
        $hasMachinesActive = Schema::hasColumn('machines', 'active');
        $allActiveMachineIds = Machine::query()
            ->when($hasMachinesActive, fn ($q) => $q->where('active', 1))
            ->pluck('id')
            ->all();

        $allMachinesById = Machine::query()
            ->whereIn('id', $allActiveMachineIds ?: [-1])
            ->get(['id', 'name'])
            ->keyBy('id');

        // --- 5) Fa felépítése memóriában ---
        $tree = [];

        foreach ($partners as $p) {
            $pNode = [
                'id'       => "partner:{$p->id}",
                'type'     => 'partner',
                'name'     => $p->name,
                'children' => [],
                'sumQty'   => 0.0,
                'sumHours' => 0.0, // itt később becsült órát is lehet aggregálni, ha meglesz a képlet
            ];

            foreach ($p->orders as $o) {
                $oNode = [
                    'id'       => "{$pNode['id']}/order:{$o->id}",
                    'type'     => 'order',
                    'name'     => $o->order_no ?? "#{$o->id}",
                    'children' => [],
                ];

                foreach ($o->items as $oi) {
                    $item = $oi->item;
                    if (!$item) {
                        continue;
                    }

                    $prodNode = [
                        'id'       => "{$oNode['id']}/product:{$item->id}",
                        'type'     => 'product',
                        'name'     => $item->name ?: ($item->sku ?: "Termék #{$item->id}"),
                        'children' => [],
                        'sumQty'   => (float) ($oi->qty_ordered ?? 0),
                    ];
                    $pNode['sumQty'] += $prodNode['sumQty'];

                    // Lépések ehhez az itemhez
                    $steps = $stepsByItem->get($item->id, collect());

                    if ($steps->isEmpty()) {
                        // --- Fallback: 1 db "Gyártás" process + gépek pivotból, ha az sincs, minden aktív gép ---
                        $procId = "{$prodNode['id']}/process:default";
                        $procNode = [
                            'id'       => $procId,
                            'type'     => 'process',
                            'name'     => 'Gyártás',
                            'children' => [],
                        ];

                        $machineIds = $pivotByItem[$item->id] ?? [];
                        if (empty($machineIds)) {
                            $machineIds = $allActiveMachineIds; // minden aktív gép
                        }

                        foreach ($machineIds as $mid) {
                            if (!isset($allMachinesById[$mid])) {
                                // ha pivotban van, de a gép inaktív / hiányzik, ugorjuk
                                continue;
                            }
                            $m = $allMachinesById[$mid];
                            $procNode['children'][] = [
                                'id'         => "{$procId}/machine:{$m->id}",
                                'type'       => 'machine',
                                'name'       => $m->name,
                                'resourceId' => $m->id,
                            ];
                        }

                        $prodNode['children'][] = $procNode;
                    } else {
                        // --- Normál: lépések + a lépéshez rendelt gépek ---
                        foreach ($steps as $step) {
                            $procId = "{$prodNode['id']}/process:{$step->id}";
                            $procNode = [
                                'id'       => $procId,
                                'type'     => 'process',
                                'name'     => $step->name ?? "Lépés #{$step->id}",
                                'children' => [],
                            ];

                            // a ->machines kapcsolaton csak id,name van betöltve
                            foreach ($step->machines as $m) {
                                $procNode['children'][] = [
                                    'id'         => "{$procId}/machine:{$m->id}",
                                    'type'       => 'machine',
                                    'name'       => $m->name,
                                    'resourceId' => $m->id,
                                ];
                            }

                            $prodNode['children'][] = $procNode;
                        }
                    }

                    $oNode['children'][] = $prodNode;
                }

                $pNode['children'][] = $oNode;
            }

            $tree[] = $pNode;
        }

        return response()->json($tree);
    }
}
