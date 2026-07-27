<?php

// app/Services/Scheduling/OverlapValidator.php
namespace App\Services\Scheduling;

use App\Models\Task;

class OverlapValidator
{
    public function hasOverlap(int $machineId, string $start, string $end, ?int $ignoreTaskId = null): bool
    {
        return Task::query()
            ->where('machine_id', $machineId)
            ->when($ignoreTaskId, fn($q)=>$q->where('id','!=',$ignoreTaskId))
            ->where(function($q) use ($start,$end){
                $q->whereBetween('starts_at', [$start,$end])
                ->orWhereBetween('ends_at', [$start,$end])
                ->orWhere(fn($qq)=>$qq->where('starts_at','<=',$start)->where('ends_at','>=',$end));
            })
            ->exists();
    }
}
