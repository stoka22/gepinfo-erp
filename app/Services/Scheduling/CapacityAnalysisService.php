<?php

namespace App\Services\Scheduling;

use App\Models\Item;
use App\Models\Machine;
use App\Models\PartnerOrderItem;
use App\Models\ProductionSplit;
use App\Models\ProductionTask;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CapacityAnalysisService
{
    private const HORIZON_DAYS = 30;

    public function __construct(private WindowPolicy $windowPolicy)
    {
    }

    /** Gépek kihasználtsága a következő 30 napra, csökkenő sorrendben (a lista teteje a szűk keresztmetszet). */
    public function machineUtilization(): Collection
    {
        $horizonStart = Carbon::now()->startOfDay();
        $horizonEnd = $horizonStart->copy()->addDays(self::HORIZON_DAYS);

        return Machine::query()
            ->where('active', true)
            ->get()
            ->map(function (Machine $machine) use ($horizonStart, $horizonEnd) {
                $availableSeconds = $this->availableSecondsBetween($machine->id, $horizonStart, $horizonEnd);

                $taskSeconds = (int) ProductionTask::where('machine_id', $machine->id)
                    ->whereBetween('starts_at', [$horizonStart, $horizonEnd])
                    ->get(['setup_seconds', 'run_seconds'])
                    ->sum(fn (ProductionTask $t) => (int) ($t->setup_seconds ?? 0) + (int) ($t->run_seconds ?? 0));

                $splitSeconds = (int) ProductionSplit::where('machine_id', $machine->id)
                    ->whereBetween('start', [$horizonStart, $horizonEnd])
                    ->get(['start', 'end'])
                    ->sum(fn (ProductionSplit $s) => ($s->start && $s->end) ? abs($s->end->diffInSeconds($s->start)) : 0);

                $usedSeconds = $taskSeconds + $splitSeconds;
                $utilization = $availableSeconds > 0
                    ? round(($usedSeconds / $availableSeconds) * 100, 1)
                    : ($usedSeconds > 0 ? 999.0 : 0.0);

                return [
                    'machine'         => $machine,
                    'available_hours' => round($availableSeconds / 3600, 1),
                    'used_hours'      => round($usedSeconds / 3600, 1),
                    'utilization_pct' => $utilization,
                ];
            })
            ->sortByDesc('utilization_pct')
            ->values();
    }

    /**
     * Nyílt (nem teljesített) rendelési tételek, amiknél a recept alapján becsült hátralévő
     * munka nagyobb, mint a határidőig a felelős gépen elérhető munkaidő.
     * Egyszerűsítés: az első (step_no szerinti) recept-lépéshez rendelt gépet vesszük referenciának.
     */
    public function atRiskOrderItems(): Collection
    {
        return PartnerOrderItem::query()
            ->whereColumn('qty_produced', '<', 'qty_ordered')
            ->whereNotNull('due_date')
            ->with(['item.workSteps.machines', 'order'])
            ->get()
            ->map(function (PartnerOrderItem $poi) {
                $remainingQty = max(0.0, (float) $poi->qty_ordered - (float) $poi->qty_produced);
                $neededSeconds = $poi->item ? $poi->item->estimatedDurationForQty($remainingQty) : 0;

                $dueDate = Carbon::parse($poi->due_date)->endOfDay();
                $daysLeft = (int) round(Carbon::now()->diffInDays($dueDate, false));

                $bottleneckStep = $poi->item?->workSteps
                    ->sortByDesc(fn ($s) => (float) ($s->setup_time_sec ?? 0) + $remainingQty * (float) ($s->cycle_time_sec ?? 0))
                    ->first();
                $referenceMachineId = $bottleneckStep ? $this->referenceMachineIdForStep($bottleneckStep) : null;
                $availableUntilDue = $referenceMachineId
                    ? $this->availableSecondsBetween($referenceMachineId, Carbon::now(), $dueDate)
                    : null;

                $atRisk = $daysLeft < 0 || ($availableUntilDue !== null && $neededSeconds > $availableUntilDue);

                return [
                    'order_item'               => $poi,
                    'remaining_qty'            => $remainingQty,
                    'needed_hours'             => round($neededSeconds / 3600, 1),
                    'available_hours_until_due' => $availableUntilDue !== null ? round($availableUntilDue / 3600, 1) : null,
                    'days_left'                => $daysLeft,
                    'at_risk'                  => $atRisk,
                ];
            })
            ->sortBy('days_left')
            ->values();
    }

    /**
     * Gyártandó termékek listája: nyílt rendelési tételek TERMÉKENKÉNT összesítve
     * (össz. hátralévő darabszám, legkorábbi kért határidő), és egy leegyszerűsített
     * "ütemezett határidő" becslés: a tételeket kért határidő szerint (EDD) sorba rakva,
     * gépenként egymás után foglaljuk le a szükséges időt (a már véglegesített
     * ProductionTask/ProductionSplit munka utáni időponttól kezdve).
     */
    public function productionQueue(): Collection
    {
        $openItems = PartnerOrderItem::query()
            ->whereColumn('qty_produced', '<', 'qty_ordered')
            ->with(['item.workSteps.machines', 'order'])
            ->get()
            ->filter(fn (PartnerOrderItem $poi) => $poi->item_id !== null);

        $grouped = $openItems->groupBy('item_id')->map(function (Collection $rows) {
            /** @var PartnerOrderItem $first */
            $first = $rows->first();
            $remainingQty = (float) $rows->sum(fn (PartnerOrderItem $r) => max(0.0, (float) $r->qty_ordered - (float) $r->qty_produced));
            $requestedDue = $rows->pluck('due_date')->filter()->map(fn ($d) => Carbon::parse($d))->sort()->first();

            return [
                'item'           => $first->item,
                'item_name'      => $first->item_name_cache,
                'remaining_qty'  => $remainingQty,
                'order_count'    => $rows->count(),
                'requested_due'  => $requestedDue,
            ];
        })->values();

        // EDD (legkorábbi kért határidő elöl); határidő nélküliek a végére.
        $sorted = $grouped->sortBy(fn (array $g) => $g['requested_due']?->timestamp ?? PHP_INT_MAX)->values();

        $machineCursors = [];

        return $sorted->map(function (array $g) use (&$machineCursors) {
            /** @var Item|null $item */
            $item = $g['item'];
            $hasRecipe = $item && $item->workSteps->isNotEmpty();

            if (! $hasRecipe) {
                return $g + ['scheduled_finish' => null, 'reference_machine' => null, 'has_recipe' => false];
            }

            // A recept leghosszabb (setup + ciklus × mennyiség) lépése a valós szűk keresztmetszet erre a tételre.
            $bottleneckStep = $item->workSteps
                ->sortByDesc(fn ($s) => (float) ($s->setup_time_sec ?? 0) + $g['remaining_qty'] * (float) ($s->cycle_time_sec ?? 0))
                ->first();
            $machineId = $bottleneckStep ? $this->referenceMachineIdForStep($bottleneckStep, $machineCursors) : null;

            if (! $machineId) {
                return $g + ['scheduled_finish' => null, 'reference_machine' => null, 'has_recipe' => true];
            }

            $neededSeconds = $item->estimatedDurationForQty($g['remaining_qty']);

            if (! isset($machineCursors[$machineId])) {
                $machineCursors[$machineId] = $this->machineBusyUntil($machineId);
            }

            $finish = $this->projectFinish($machineId, $machineCursors[$machineId], $neededSeconds);
            $machineCursors[$machineId] = $finish;

            return $g + [
                'scheduled_finish'  => $finish,
                'reference_machine' => Machine::find($machineId),
                'has_recipe'        => true,
            ];
        })->values();
    }

    /** Nyílt rendelési tételekben szereplő termékek, amikhez még nincs (aktív) recept rögzítve. */
    public function itemsMissingRecipe(): Collection
    {
        return PartnerOrderItem::query()
            ->whereColumn('qty_produced', '<', 'qty_ordered')
            ->whereNotNull('item_id')
            ->with('item.workSteps')
            ->get()
            ->filter(fn (PartnerOrderItem $poi) => $poi->item && $poi->item->workSteps->isEmpty())
            ->unique('item_id')
            ->map(fn (PartnerOrderItem $poi) => $poi->item)
            ->values();
    }

    /**
     * Egy recept-lépéshez tartozó referencia gép: ha van közvetlen machine_id, azt használjuk
     * (régi séma), egyébként a "képes gépek" (machines() pivot) közül a legkevésbé foglaltat
     * választjuk — ez modellezi legjobban, hogy a valóságban melyik gépre kerülne a munka.
     *
     * @param  array<int, Carbon>  $cursors  már kiosztott (ebben a futásban foglalt) gép-kurzorok
     */
    private function referenceMachineIdForStep($step, array $cursors = []): ?int
    {
        if ($step->machine_id) {
            return $step->machine_id;
        }

        $candidates = $step->machines;
        if ($candidates->isEmpty()) {
            return null;
        }
        if ($candidates->count() === 1) {
            return $candidates->first()->id;
        }

        return $candidates
            ->sortBy(function ($m) use ($cursors) {
                $busyUntil = $cursors[$m->id] ?? $this->machineBusyUntil($m->id);
                return $busyUntil->timestamp;
            })
            ->first()
            ->id;
    }

    /** A gép legkésőbbi, még jövőbeli, már véglegesített foglaltsági időpontja (onnantól szabad). */
    private function machineBusyUntil(int $machineId): Carbon
    {
        $now = Carbon::now();

        $taskEnd = ProductionTask::where('machine_id', $machineId)->where('ends_at', '>', $now)->max('ends_at');
        $splitEnd = ProductionSplit::where('machine_id', $machineId)->where('end', '>', $now)->max('end');

        $latest = collect([$taskEnd, $splitEnd])
            ->filter()
            ->map(fn ($d) => Carbon::parse($d))
            ->sort()
            ->last();

        return $latest ?? $now;
    }

    /**
     * Adott gépen, adott időponttól indulva, mikor telik le a szükséges munkaidő
     * (napi elérhető kapacitással számolva). Leegyszerűsítés: a $from napján még
     * teljes napi kapacitást feltételez (nem vonja le a nap már eltelt részét).
     */
    private function projectFinish(int $machineId, Carbon $from, int $neededSeconds): Carbon
    {
        $remaining = $neededSeconds;
        $cursor = $from->copy();
        $guard = 0;

        while ($remaining > 0 && $guard < 730) {
            $dayAvailable = $this->windowPolicy->availableSecondsForDay($machineId, $cursor->copy()->startOfDay());

            if ($dayAvailable <= 0) {
                $cursor = $cursor->copy()->addDay()->startOfDay();
                $guard++;
                continue;
            }

            if ($dayAvailable >= $remaining) {
                $cursor = $cursor->copy()->addSeconds((int) $remaining);
                $remaining = 0;
            } else {
                $remaining -= $dayAvailable;
                $cursor = $cursor->copy()->addDay()->startOfDay();
            }

            $guard++;
        }

        return $cursor;
    }

    private function availableSecondsBetween(int $machineId, Carbon $start, Carbon $end): int
    {
        $total = 0;
        $cursor = $start->copy()->startOfDay();
        $endDay = $end->copy()->startOfDay();

        while ($cursor->lte($endDay)) {
            $total += $this->windowPolicy->availableSecondsForDay($machineId, $cursor->copy());
            $cursor->addDay();
        }

        return $total;
    }
}
