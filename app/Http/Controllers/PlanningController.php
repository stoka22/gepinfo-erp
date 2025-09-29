<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Scheduling\BuildTasksFromItemWorkSteps;

class PlanningController extends Controller
{
    public function generateFromItemSteps(Request $req, BuildTasksFromItemWorkSteps $builder)
    {
        $data = $req->validate([
            'order_item_id' => ['required','integer'],
            'start'         => ['required','date'],
        ]);

        $result = $builder->handle($data['order_item_id'], $data['start']);

        return response()->json([
            'created_tasks'        => collect($result['tasks'])->pluck('id'),
            'created_dependencies' => collect($result['dependencies'])->pluck('id'),
        ], 201);
    }
}
