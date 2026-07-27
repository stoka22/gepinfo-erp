<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Task;

class TaskDependencyDemoSeeder extends Seeder
{
    public function run(): void
    {
        // feltételezzük, hogy van legalább 1 gép (machine_id=1)
        $a = Task::create([
            'name' => 'Vágás',
            'machine_id' => 1,
            'starts_at' => now()->addHour()->format('Y-m-d H:00:00'),
            'ends_at'   => now()->addHours(3)->format('Y-m-d H:00:00'),
            'setup_minutes' => 10,
        ]);

        $b = Task::create([
            'name' => 'Hajlítás',
            'machine_id' => 1,
            'starts_at' => now()->addHours(2)->format('Y-m-d H:00:00'), // ez FS szerint túl korai lesz
            'ends_at'   => now()->addHours(4)->format('Y-m-d H:00:00'),
            'setup_minutes' => 5,
        ]);
    }
}
