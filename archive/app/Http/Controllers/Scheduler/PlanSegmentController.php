<?php

namespace App\Http\Controllers\Scheduler;

use App\Models\Machine;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;

class PlanSegmentController extends Controller
{
// app/Http/Controllers/Scheduler/PlanSegmentController.php (lényeg)
public function store(Request $r) {
    $data = $r->validate([
        'machine_id' => ['required','integer'],
        'starts_at'  => ['required','date'],
        'ends_at'    => ['required','date','after:starts_at'],
        'qty'        => ['required','numeric','min:0.01'],
        'rate_pph'   => ['nullable','numeric','min:0'],
        'meta'       => ['nullable','array'], // productNodeId, processNodeId, title stb.
    ]);

    $seg = PlanSegment::create([
        'machine_id' => $data['machine_id'],
        'starts_at'  => $data['starts_at'],
        'ends_at'    => $data['ends_at'],
        'qty'        => $data['qty'],
        'rate_pph'   => $data['rate_pph'] ?? null,
        'meta'       => $data['meta'] ?? null,
    ]);

    // Vissza a kliens által várt mezőkkel:
    return response()->json([
        'id'         => $seg->id,
        'resourceId' => $seg->machine_id,
        'title'      => $seg->meta['title'] ?? 'Tervezett',
        'start'      => $seg->starts_at->toIso8601String(),
        'end'        => $seg->ends_at->toIso8601String(),
        'qtyTotal'   => (float)$seg->qty,
        'ratePph'    => $seg->rate_pph,
        'updatedAt'  => $seg->updated_at->toIso8601String(),
    ], 201);
}
}