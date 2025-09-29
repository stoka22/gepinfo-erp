<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@gepinfo.hu'], // ha már létezik, nem hozza létre újra
            [
                'name' => 'Admin',
                'password' => Hash::make('123456'), // erős jelszóra cseréld!
            ]
        );
    }
}
