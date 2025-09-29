<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndAdminSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Panel-hozzáférési jogosultságok
        $pAdmin = Permission::firstOrCreate(['name' => 'access admin panel']);
        $pUser  = Permission::firstOrCreate(['name' => 'access user panel']);

        // 2) Szerepkörök
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $user  = Role::firstOrCreate(['name' => 'user']);

        // 3) Jogosultságok hozzárendelése
        $admin->syncPermissions([$pAdmin, $pUser]);
        $user->syncPermissions([$pUser]);

        // 4) Admin felhasználó létrehozása/frissítése
        $u = User::updateOrCreate(
            ['email' => 'admin2@gepinfo.hu'],
            [
                'name'     => 'Admin',
                'password' => Hash::make('123456789'),
            ]
        );

        // 5) Admin szerepkör hozzárendelése
        $u->syncRoles(['admin']);
    }
}
