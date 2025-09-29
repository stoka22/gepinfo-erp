<?php

namespace Database\Seeders;

use App\Enums\Shift;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class FakeEmployeesSeeder extends Seeder
{
    public function run(): void
    {
        // ha nincs user, próbáljuk meg létrehozni az alap admin(oka)t
        if (User::count() === 0 && class_exists(\Database\Seeders\RolesAndAdminSeeder::class)) {
            $this->call(\Database\Seeders\RolesAndAdminSeeder::class);
        }

        $users = User::query()->get();
        if ($users->isEmpty()) {
            $this->command->warn('Nincs felhasználó a rendszerben – létrehozok 1 alap usert.');
            $users->push(User::factory()->create([
                'name' => 'Demo Owner',
                'email' => 'owner@example.com',
                'password' => 'password',
            ]));
        }

        $faker = fake('hu_HU');
        $positions = ['Gépkezelő', 'Karbantartó', 'Minőségellenőr', 'Raktáros', 'Operátor', 'CNC gépkezelő', 'Lakatos'];

        $shifts = [Shift::Morning, Shift::Afternoon, Shift::Night];

        // 50 dolgozó létrehozása
        for ($i = 0; $i < 50; $i++) {
            $owner = $users->random();

            Employee::create([
                'user_id'  => $owner->id,
                'name'     => $faker->name(),
                'email'    => $faker->unique()->safeEmail(),
                'phone'    => $faker->phoneNumber(),
                'position' => Arr::random($positions),
                'hired_at' => $faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
                'shift'    => Arr::random($shifts), // enum érték (automatikus cast)
            ]);
        }

        $this->command->info('50 fake dolgozó létrehozva műszakbeosztással.');
    }
}
