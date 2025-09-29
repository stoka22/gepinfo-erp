<?php

// app/Services/Scheduling/DependencyValidator.php
namespace App\Services\Scheduling;

use App\Models\TaskDependency;
use App\Models\Task;
use Illuminate\Support\Carbon;

class DependencyValidator
{
    public function checkFs(Task $succ): bool
    {
        $deps = TaskDependency::with('predecessor')
            ->where('successor_id', $succ->id)->get();

        foreach ($deps as $dep) {
            $pred = $dep->predecessor;
            if (!$pred) continue;

            $minStart = Carbon::parse($pred->ends_at)->addMinutes($dep->lag_minutes);
            if (Carbon::parse($succ->starts_at)->lt($minStart)) {
                return false; // megsértette az FS+lag szabályt
            }
        }
        return true;
    }

    public function wouldCreateCycle(int $predId, int $succId): bool
    {
        // DFS a successor -> ... -> pred útvonalra
        return $this->dfsHasPath($succId, $predId);
    }

    protected function dfsHasPath(int $from, int $to, array $visited = []): bool
    {
        if ($from === $to) return true;
        if (in_array($from, $visited, true)) return false;
        $visited[] = $from;

        $next = TaskDependency::where('predecessor_id',$from)->pluck('successor_id');
        foreach ($next as $n) {
            if ($this->dfsHasPath($n, $to, $visited)) return true;
        }
        return false;
    }
}
