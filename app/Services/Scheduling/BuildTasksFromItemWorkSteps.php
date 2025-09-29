<?php

namespace App\Services\Scheduling;

use App\Models\{Task, TaskDependency, ItemWorkStep, PartnerOrderItem};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\Scheduling\OverlapValidator;
use App\Services\Scheduling\CapacityValidator;
use App\Services\Scheduling\WindowPolicy;

class BuildTasksFromItemWorkSteps
{
    /**
     * Megrendelés tételhez (PartnerOrderItem) legenerálja a Taskokat a recept lépéseiből.
     *
     * @param int $orderItemId  PartnerOrderItem ID (tartalmazza: item_id, qty)
     * @param string|\DateTimeInterface $start  első lépés cél kezdete
     * @return array [tasks => Task[], dependencies => TaskDependency[]]
     */
    public function __construct(
        protected OverlapValidator $overlap,
        protected CapacityValidator $capacity,
        protected WindowPolicy $window,
    ) {}
    public function handle(int $orderItemId, $start): array
    {
        // ----- MAPPING: ha más a modelled, itt igazítsd -----
        /** @var \App\Models\PartnerOrderItem $orderItem */
        $orderItem = PartnerOrderItem::with('item')->findOrFail($orderItemId);
        $itemId       = $orderItem->item_id;
        $orderItemId_ = $orderItem->id;   // <-- ezt visszük a closure-be
        $qty          = (int) ($orderItem->qty ?? 1);

        // Aktív lépések rendezve
        $steps = ItemWorkStep::with('capableMachines')
            ->where('item_id', $itemId)
            ->where('is_active', 1)
            ->orderBy('step_no')
            ->get();

        if ($steps->isEmpty()) {
            throw new \RuntimeException('Ehhez a termékhez nincs aktív receptlépés.');
        }

        $cursor = Carbon::parse($start);
        $createdTasks = [];
        $createdDeps  = [];

        DB::transaction(function () use ($steps, $qty, $orderItemId_, &$cursor, &$createdTasks, &$createdDeps) {

            $prevTask = null;

            foreach ($steps as $step) {
                $setupSec   = (int) ($step->setup_time_sec ?? 0);
                $cycleSec   = (float) ($step->cycle_time_sec ?? 0.0);
                $totalSec   = $setupSec + (int) ceil($cycleSec * max(1, $qty));
                $durationMin = max(1, (int) ceil($totalSec / 60));
                $setupMin    = (int) ceil($setupSec / 60);

                // Gép választás
                $machineId = $step->machine_id ?: optional($step->capableMachines->first())->machine_id;

                // --- SLOT KERESÉS (M5–M7) ---
                $slotStart = $this->findNextFeasibleStart(
                    machineId: (int) ($machineId ?? 0),
                    from: $cursor->copy(),
                    durationMin: $durationMin,
                    setupMin: $setupMin
                );
                $slotEnd = $slotStart->copy()->addMinutes($durationMin);

                $task = Task::create([
                    'name'          => $step->name ?: ('Lépés #'.$step->step_no),
                    'machine_id'    => $machineId,
                    'order_item_id' => $orderItemId_,
                    'starts_at'     => $slotStart,
                    'ends_at'       => $slotEnd,
                    'setup_minutes' => $setupMin,
                    // 'workflow_id' => $step->workflow_id, // ha kell
                ]);
                $createdTasks[] = $task;
                // FS kapcsolat az előzőhöz (lag = 0, mert a táblában nincs lag)
                if ($prevTask) {
                    $dep = TaskDependency::create([
                        'predecessor_id' => $prevTask->id,
                        'successor_id'   => $task->id,
                        'type'           => 'FS',
                        'lag_minutes'    => 0,
                    ]);
                    $createdDeps[] = $dep;
                }
                $cursor = $task->ends_at->copy();
                $prevTask = $task;
            }
        });

        return ['tasks' => $createdTasks, 'dependencies' => $createdDeps];
    }

    private function findNextFeasibleStart(int $machineId, \Illuminate\Support\Carbon $from, int $durationMin, int $setupMin): \Illuminate\Support\Carbon
{
    $start = $from->copy();

    // Ha nincs gép kötve (null), nem tudunk M5–M7-et értelmezni -> visszaadjuk a kért időt
    if ($machineId === 0) {
        return $start;
    }

    // védett ciklus: max 500 lépés (5 perces lépések esetén ~ 41 óra)
    for ($i = 0; $i < 500; $i++) {
        $end = $start->copy()->addMinutes($durationMin);

        // 1) Engedett sáv?
        if (!$this->window->isWithinAllowed($machineId, $start->toDateTimeString(), $end->toDateTimeString())) {
            $start = $this->window->nextAllowedStart($machineId, $start)->copy();
            continue;
        }

        // 2) Ütközés?
        if ($this->overlap->hasOverlap($machineId, $start->toDateTimeString(), $end->toDateTimeString())) {
            // lépjünk a legkésőbbi ütköző task vége + 1 percre
            $latestEnd = \App\Models\Task::where('machine_id', $machineId)
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('starts_at', [$start, $end])
                      ->orWhereBetween('ends_at',   [$start, $end])
                      ->orWhere(fn($qq)=>$qq->where('starts_at','<=',$start)->where('ends_at','>=',$end));
                })
                ->max('ends_at');

            $start = $latestEnd ? Carbon::parse($latestEnd)->addMinute() : $start->addMinutes(5);
            continue;
        }

        // 3) Kapacitás?
        if (!$this->capacity->hasCapacity($machineId, $start->toDateTimeString(), $end->toDateTimeString(), $setupMin)) {
            // egyszerű stratégia: ugorjunk a következő nap 06:00-ra
            $start = $start->copy()->addDay()->startOfDay()->addHours(6);
            continue;
        }

        // minden feltétel oké
        return $start;
    }

    // ha valamiért nem találtunk slotot, visszaadjuk az eredetit (de ez gyakorlatban nem kéne előforduljon)
    return $from->copy();
}

}
