<?php

namespace Database\Seeders;   // ← EZ HIÁNYZOTT

use Illuminate\Database\Seeder;
use App\Models\ProductionTask;
use Carbon\Carbon;

class ProductionTaskSeeder extends Seeder
{
    public function run(): void
    {
        $start = Carbon::now()->startOfHour();

        ProductionTask::create([
            'partner_id'            => 1,
            'partner_order_id'      => 2,
            'partner_order_item_id' => 2,
            'item_id'               => 4,
            'item_work_step_id'     => 5, // Darabolás
            'machine_id'            => 1, // 300KN 1 dar. BP
            'qty'                   => 6000,
            'starts_at'             => $start,
            'ends_at'               => (clone $start)->addHours(3),
            'status'                => 'planned',
        ]);
    }
}
