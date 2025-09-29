<?php

namespace App\Http\Controllers\Scheduler;

use App\Models\Machine;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Schema;

class ResourceController extends Controller
{
    public function index()
    {
        // A React scheduler "resources" listájához.
        return Machine::query()
            ->when(Schema::hasColumn('machines','active'), fn($q)=>$q->where('active',1))
        ->orderBy('name')
        ->get(['id','name'])
        ->map(fn($m)=>['id'=>$m->id,'name'=>$m->name]);
    }
}
