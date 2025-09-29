<?php

namespace App\Services\Scheduling;

use App\Models\MachineBlocking;
use App\Models\ResourceShiftAssignment;
use App\Models\ShiftPattern;
use Illuminate\Support\Carbon;
use App\Models\ShiftBreak;
use Illuminate\Support\Collection;

class WindowPolicy
{
    /**
     * Visszaadja: a javasolt [start,end] teljesen engedett-e.
     * Első verzióban: tiltott sávok (MachineBlocking) + opcionális műszakok (ha vannak).
     */
    public function isWithinAllowed(int $machineId, string $start, string $end): bool
    {
        $s = Carbon::parse($start);
        $e = Carbon::parse($end);

        // 1) Tiltott sávok ütközése?
        $blocked = MachineBlocking::where('machine_id', $machineId)
            ->where(function ($q) use ($s, $e) {
                $q->whereBetween('starts_at', [$s, $e])
                  ->orWhereBetween('ends_at', [$s, $e])
                  ->orWhere(fn($qq)=>$qq->where('starts_at','<=',$s)->where('ends_at','>=',$e));
            })->exists();

        if ($blocked) return false;

        // 2) Ha nincs shift konfigurálva, minden idő engedett
        $hasShift = ResourceShiftAssignment::where('resource_id', $machineId)->exists();
        if (!$hasShift) return true;

        // 3) Műszakok: az adott napra kiszámoljuk az engedett időablakokat
        return $this->isInsideShifts($machineId, $s, $e);
    }

    /**
     * Megadja a legközelebbi engedett startot (>= from).
     */
    public function nextAllowedStart(int $machineId, Carbon $from): Carbon
    {
        // Ha tiltott sávba esik, lépjünk a tiltás végére; ha műszakok vannak, a következő műszak kezdésére
        $probe = $from->copy();

        // ha van shiftkonfig, ugorjunk a legközelebbi műszakkezdésre
        $hasShift = ResourceShiftAssignment::where('resource_id', $machineId)->exists();
        if ($hasShift) {
            $next = $this->nextShiftStart($machineId, $probe);
            if ($next) $probe = $next;
        }

        // tiltott sávok végére ugrás
        $blk = MachineBlocking::where('machine_id', $machineId)
            ->where('starts_at', '<=', $probe)
            ->where('ends_at', '>',  $probe)
            ->orderBy('ends_at')
            ->first();

        if ($blk) {
            return Carbon::parse($blk->ends_at)->copy();
        }

        return $probe;
    }

    // --- belső segédek (egyszerű implementáció) ---

    protected function isInsideShifts(int $machineId, Carbon $s, Carbon $e): bool
    {
        // végigmegyünk minden érintett napon; bármelyik napon eső résznek bele kell férnie a shift-sávok uniójába
        $cursor = $s->copy();
        while ($cursor->lt($e)) {
            $dayEnd = $cursor->copy()->endOfDay()->min($e);
            $allowed = $this->dayShiftWindows($machineId, $cursor->copy());

            // ha nincs a napon egyetlen allowed sáv sem -> bukó
            if (empty($allowed)) return false;

            $ok = false;
            foreach ($allowed as [$as, $ae]) {
                if ($s->gte($as) && $e->lte($ae)) { $ok = true; break; }
            }
            if (!$ok) return false;

            $cursor = $dayEnd->addSecond();
        }
        return true;
    }

    protected function nextShiftStart(int $machineId, Carbon $from): ?Carbon
    {
        // visszaadja a legközelebbi shift-sáv kezdetét, ami >= from
        for ($i = 0; $i < 7; $i++) {
            $day = $from->copy()->addDays($i)->startOfDay();
            foreach ($this->dayShiftWindows($machineId, $day) as [$as, $ae]) {
                if ($as->gte($from)) return $as->copy();
                if ($from->between($as, $ae)) return $from->copy(); // már műszakban vagyunk
            }
        }
        return null;
    }

    /**
     * Az adott nap (00:00–24:00) engedett shift-sávjai (ShiftPattern + Assignment alapján).
     * Ha nincs Assignment, üres tömb – és ezt a hívó kezeli.
     * Formátum: array of [Carbon $start, Carbon $end]
     */
   

    protected function dayShiftWindows(int $machineId, Carbon $day): array
    {
        $assigns = ResourceShiftAssignment::where('resource_id', $machineId)
            ->where('valid_from', '<=', $day->toDateString())
            ->where(function ($q) use ($day) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $day->toDateString());
            })->pluck('shift_pattern_id');

        if ($assigns->isEmpty()) return [];

        $patterns = ShiftPattern::with('breaks')->whereIn('id', $assigns)->get();

        $windows = [];

        foreach ($patterns as $p) {
            if (!$p->appliesToDow((int)$day->dayOfWeek)) continue; // napmaszk

            $start = $day->copy()->setTimeFromTimeString($p->start_time);
            $end   = $day->copy()->setTimeFromTimeString($p->end_time);
            if ($end->lte($start)) $end->addDay(); // éjfél után ér véget

            // kezdő sáv: műszak teljes ideje
            $slots = [[clone $start, clone $end]];

            // vágjuk ki a szüneteket
            foreach ($p->breaks as $br) {
                $bs = $day->copy()->setTimeFromTimeString($br->start_time);
                $be = (clone $bs)->addMinutes((int)$br->duration_min);
                if ($be->lte($start) || $bs->gte($end)) continue; // kívül esik

                $new = [];
                foreach ($slots as [$s,$e]) {
                    // nincs átfedés
                    if ($be->lte($s) || $bs->gte($e)) { $new[] = [$s,$e]; continue; }
                    // van: [s,bs) és (be,e]
                    if ($bs->gt($s)) $new[] = [$s, clone $bs];
                    if ($be->lt($e)) $new[] = [clone $be, $e];
                }
                $slots = $new;
            }

            foreach ($slots as $slot) $windows[] = $slot;
        }

        // összevonás, ha több minta ad egymást érintő sávokat
        usort($windows, fn($a,$b)=>$a[0]<=>$b[0]);
        $merged = [];
        foreach ($windows as [$s,$e]) {
            if (empty($merged)) { $merged[] = [$s,$e]; continue; }
            [$ls,$le] = $merged[count($merged)-1];
            if ($s->lte($le)) {
                if ($e->gt($le)) $merged[count($merged)-1][1] = $e;
            } else {
                $merged[] = [$s,$e];
            }
        }
        return $merged;
    }
}
