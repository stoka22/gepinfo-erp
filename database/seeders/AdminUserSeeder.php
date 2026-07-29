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
        $user = User::firstOrCreate(
            ['email' => 'admin@gepinfo.hu'], // ha már létezik, nem hozza létre újra
            [
                'name' => 'Admin',
                'role' => 'admin',
                'password' => Hash::make('123456'), // erős jelszóra cseréld!
            ]
        );

        // A rendszerben KÉT párhuzamos admin-ellenőrzés is él (a users.role oszlop és a
        // Spatie 'admin' szerepkör) — mindkettőt be kell állítani, különben ez a fiók
        // (bár a neve "admin") a gyakorlatban semmilyen admin jogosultságot nem kap.
        if ($user->role !== 'admin') {
            $user->role = 'admin';
            $user->save();
        }

        if (! $user->hasRole('admin')) {
            $user->assignRole('admin');
        }
    }
}
