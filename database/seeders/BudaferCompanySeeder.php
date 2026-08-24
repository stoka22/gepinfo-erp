<?php

// database/seeders/BudaferCompanySeeder.php
namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class BudaferCompanySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Budafer telephely 1', 'Budafer telephely 2', 'Budafer telephely 3'] as $name) {
            Company::firstOrCreate(['name' => $name]);
        }
    }
}
