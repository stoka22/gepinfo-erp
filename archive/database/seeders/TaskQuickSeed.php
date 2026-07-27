<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Task;

class TaskQuickSeed extends Seeder
{
    public function run(): void
    {
        // két egymást érintő feladat ugyanazon machine_id-n (1)
        Task::create([
            'name' => 'Vágás',
            'machine_id' => 1,
            'starts_at' => now()->addHour()->startOfHour(),
            'ends_at'   => now()->addHours(3)->startOfHour(),
            'setup_minutes' => 10,
        ]);

        Task::create([
            'name' => 'Hajlítás',
            'machine_id' => 1,
            'starts_at' => now()->addHours(2)->startOfHour(), // FS szempontból korai
            'ends_at'   => now()->addHours(4)->startOfHour(),
            'setup_minutes' => 5,
        ]);
    }
}
