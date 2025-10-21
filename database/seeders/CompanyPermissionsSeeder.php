<?php

// database/seeders/CompanyPermissionsSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CompanyPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $perms = [
            'companies.viewAny',
            'companies.view',
            'companies.create',
            'companies.update',
            'companies.delete',
            'companies.attachUsers',
        ];

        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }

        // admin szerepkör kapja meg mindet
        if ($admin = Role::where('name', 'admin')->first()) {
            $admin->givePermissionTo($perms);
        }
    }
}
