<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use Illuminate\Http\Request;

class MachineController extends Controller
{
    public function index()
    {
        $machines = Machine::withCount('devices')->latest()->paginate(15);
        return view('machines.index', compact('machines'));
    }

    public function create()
    {
        return view('machines.create');
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'name'            => 'required|string|max:255',
            'code'            => 'required|string|max:100|unique:machines,code',
            'location'        => 'nullable|string|max:255',
            'vendor'          => 'nullable|string|max:255',
            'model'           => 'nullable|string|max:255',
            'serial'          => 'nullable|string|max:255',
            'commissioned_at' => 'nullable|date',
            'active'          => 'boolean',
            'notes'           => 'nullable|string',
        ]);
        Machine::create($data);
        return redirect()->route('machines.index')->with('ok','Gép létrehozva');
    }

    public function edit(Machine $machine)
    {
        return view('machines.edit', compact('machine'));
    }

    public function update(Request $r, Machine $machine)
    {
        $data = $r->validate([
            'name'            => 'required|string|max:255',
            'code'            => 'required|string|max:100|unique:machines,code,'.$machine->id,
            'location'        => 'nullable|string|max:255',
            'vendor'          => 'nullable|string|max:255',
            'model'           => 'nullable|string|max:255',
            'serial'          => 'nullable|string|max:255',
            'commissioned_at' => 'nullable|date',
            'active'          => 'boolean',
            'notes'           => 'nullable|string',
        ]);
        $machine->update($data);
        return redirect()->route('machines.index')->with('ok','Gép frissítve');
    }

    public function destroy(Machine $machine)
    {
        $machine->delete();
        return back()->with('ok','Gép törölve');
    }
}
