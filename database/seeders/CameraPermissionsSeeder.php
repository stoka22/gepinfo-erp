<?php

// database/seeders/CameraPermissionsSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CameraPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $perms = [
            'cameras.viewAny',
            'cameras.view',
            'cameras.create',
            'cameras.update',
            'cameras.delete',
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
