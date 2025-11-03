<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Permissions
        $permissions = [
            'manage users',
            'access admin panel',
            'employees.create',
            'employees.group.edit',   // cégcsoporton belüli szerkesztés
        ];

        foreach ($permissions as $p) {
            Permission::findOrCreate($p);
        }

        // Roles
        $admin = Role::findOrCreate('admin');
        $hr    = Role::findOrCreate('hr');        // csak saját cég
        $gHr   = Role::findOrCreate('group-hr');  // cégcsoport HR

        $admin->givePermissionTo($permissions);
        $hr->givePermissionTo(['employees.create']);
        $gHr->givePermissionTo(['employees.create', 'employees.group.edit']);
    }
}
