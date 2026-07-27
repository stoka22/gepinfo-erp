<?php

// app/Services/Scheduling/CapacityValidator.php
namespace App\Services\Scheduling;

use App\Models\MachineCalendar;
use App\Models\MachineBlocking;
use Illuminate\Support\Carbon;

class CapacityValidator
{
// CapacityValidator.php
    public function hasCapacity(int $machineId, string $start, string $end, int $setupMinutes = 0): bool
    {
        $s = Carbon::parse($start); $e = Carbon::parse($end);
        $workDate = $s->toDateString();

        $calendar = MachineCalendar::where('machine_id',$machineId)
            ->where('work_date',$workDate)->first();

        if (!$calendar) return false;

        $duration = $s->diffInMinutes($e) + $setupMinutes;

        $blocked = MachineBlocking::where('machine_id',$machineId)
            ->where(function($q) use($s,$e){
                $q->whereBetween('starts_at', [$s,$e])
                ->orWhereBetween('ends_at', [$s,$e])
                ->orWhere(fn($qq)=>$qq->where('starts_at','<=',$s)->where('ends_at','>=',$e));
            })->get();

        $blockedMinutes = 0;
        foreach ($blocked as $b) {
            $bs = max($s->timestamp, strtotime($b->starts_at));
            $be = min($e->timestamp, strtotime($b->ends_at));
            if ($be > $bs) $blockedMinutes += intdiv(($be-$bs), 60);
        }

        $netRequested = max(0, $duration - $blockedMinutes);
        return $netRequested <= $calendar->capacity_minutes;
    }

}
