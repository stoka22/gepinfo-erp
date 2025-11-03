<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class PermissionRoleSeeder extends Seeder
{
    public function run(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        // --- Permissions ---
        $perms = [
            'access user panel',
            'view time entries',
            'create time entries',
            'edit time entries',
            'edit approved time entries',
            'delete time entries',
            'delete approved time entries',
            'approve time entries',
            'manage group time entries',
        ];

        foreach ($perms as $p) {
            Permission::findOrCreate($p, $guard);
        }

        // --- Roles ---
        foreach (['admin','hr','manager','supervisor','user'] as $r) {
            Role::findOrCreate($r, $guard);
        }

        // Map roles → permissions
        Role::findByName('admin', $guard)->syncPermissions(Permission::all());

        Role::findByName('hr', $guard)->syncPermissions([
            'access user panel',
            'view time entries',
            'create time entries',
            'edit time entries',
            'edit approved time entries',
            'delete time entries',
            'delete approved time entries',
            'approve time entries',
        ]);

        Role::findByName('manager', $guard)->syncPermissions([
            'access user panel',
            'view time entries',
            'create time entries',
            'edit time entries',
            'delete time entries',
            'approve time entries',
        ]);

        Role::findByName('supervisor', $guard)->syncPermissions([
            'view time entries',
            'create time entries',
            'edit time entries',
            'delete time entries',
        ]);

        Role::findByName('user', $guard)->syncPermissions([
            'view time entries',
            'create time entries',
        ]);

        // Optionally promote an ADMIN_EMAIL user
        if ($email = env('ADMIN_EMAIL')) {
            if ($u = User::where('email', $email)->first()) {
                $u->assignRole('admin');
            }
        }

        // Bootstrap: first user becomes admin if nobody has a role
        if (User::count() === 1) {
            $u = User::first();
            if ($u && method_exists($u, 'hasAnyRole') && ! $u->hasAnyRole(['admin','hr','manager','supervisor','user'])) {
                $u->assignRole('admin');
            }
        }
    }
}
