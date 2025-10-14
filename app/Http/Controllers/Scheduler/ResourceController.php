<?php

namespace App\Http\Controllers\Scheduler;

use App\Models\Machine;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResourceController extends Controller
{
    public function index(Request $r)
    {
        $data = $r->validate([
            'q'            => ['nullable','string'],
            'ids'          => ['nullable','string'],     // "1,2,3"
            'only_active'  => ['nullable','boolean'],    // default: true (ha van oszlop)
            'include_meta' => ['nullable','boolean'],    // default: false
            'work_step_id' => ['nullable','integer'],    // csak az adott műveletre alkalmas gépek
        ]);

        $q = Machine::query();

        // active szűrés (ha van ilyen oszlop)
        if (Schema::hasColumn('machines', 'active')) {
            $onlyActive = $r->boolean('only_active', true);
            if ($onlyActive) {
                $q->where('active', 1);
            }
        }

        // keresés
        if (!empty($data['q'])) {
            $q->where('name', 'like', '%'.$data['q'].'%');
        }

        // szűrés konkrét ID-kre
        if (!empty($data['ids'])) {
            $ids = collect(explode(',', $data['ids']))
                ->map(fn ($v) => (int) trim($v))
                ->filter()->all();
            if ($ids) $q->whereIn('id', $ids);
        }

        // opcionális: csak az adott work stepre alkalmas gépek (táblanév-felismeréssel)
        if (!empty($data['work_step_id'])) {
            $pivot = null;
            foreach (['work_step_machine', 'item_work_step_machines', 'item_work_step_machine'] as $tbl) {
                if (Schema::hasTable($tbl)) { $pivot = $tbl; break; }
            }
            if ($pivot && Schema::hasColumn($pivot, 'machine_id') && Schema::hasColumn($pivot, 'work_step_id')) {
                $q->whereIn('id', function ($sub) use ($pivot, $data) {
                    $sub->from($pivot)->select('machine_id')->where('work_step_id', $data['work_step_id']);
                });
            }
        }

        // lekérendő oszlopok
        $cols = ['id','name'];
        if ($r->boolean('include_meta')) {
            foreach (['code','color','timezone','active'] as $opt) {
                if (Schema::hasColumn('machines', $opt)) $cols[] = $opt;
            }
        }

        $machines = $q->orderBy('name')->get($cols);

        // kimenet: include_meta nélkül a régi forma marad
        if (!$r->boolean('include_meta')) {
            return response()->json(
                $machines->map(fn($m) => ['id' => (int)$m->id, 'name' => $m->name])->values()
            );
        }

        // meta-val bővített forma
        return response()->json(
            $machines->map(function ($m) {
                return [
                    'id'       => (int)$m->id,
                    'name'     => $m->name,
                    'code'     => $m->code   ?? null,
                    'color'    => $m->color  ?? null,
                    'timezone' => $m->timezone ?? null,
                    'active'   => isset($m->active) ? (bool)$m->active : null,
                ];
            })->values()
        );
    }
}
