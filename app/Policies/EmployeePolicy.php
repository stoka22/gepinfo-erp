<?php
// app/Policies/EmployeePolicy.php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    /** Gyors segédfüggvény: ugyanaz-e a cégcsoport? */
    private function sameGroup(?int $a, ?int $b): bool
    {
        return $a !== null && $b !== null && $a === $b;
    }

    /** Bárki, aki látja a panelt, láthat listát – a tényleges rekord jogosultságok külön dőlnek el. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Employee $employee): bool
    {
        // admin mindent
        if ($user->hasRole('admin') || $user->can('employees.any.view')) {
            return true;
        }

        $uc = $user->company;
        $ec = $employee->company;

        if (! $uc || ! $ec) {
            return false;
        }

        // csoporton belüli megtekintés
        if ($user->can('employees.group.edit') || $user->can('employees.group.view')) {
            return $this->sameGroup($uc->company_group_id, $ec->company_group_id);
        }

        // saját cég
        return $user->can('employees.view') && $uc->id === $ec->id;
    }

    public function create(User $user): bool
    {
        // admin vagy csoportos létrehozás
        if ($user->hasRole('admin') || $user->can('employees.group.edit') || $user->can('employees.group.create')) {
            return true;
        }

        return $user->can('employees.create') || $user->can('employees.edit');
    }

    public function update(User $user, Employee $employee): bool
    {
        if ($user->hasRole('admin') || $user->can('employees.any.edit')) {
            return true;
        }

        $uc = $user->company;
        $ec = $employee->company;

        if (! $uc || ! $ec) {
            return false;
        }

        // cégcsoporton belül
        if ($user->can('employees.group.edit')) {
            return $this->sameGroup($uc->company_group_id, $ec->company_group_id);
        }

        // csak saját cég
        return $user->can('employees.edit') && $uc->id === $ec->id;
    }

    public function delete(User $user, Employee $employee): bool
    {
        if ($user->hasRole('admin') || $user->can('employees.any.delete')) {
            return true;
        }

        $uc = $user->company;
        $ec = $employee->company;

        if (! $uc || ! $ec) {
            return false;
        }

        if ($user->can('employees.group.edit') || $user->can('employees.group.delete')) {
            return $this->sameGroup($uc->company_group_id, $ec->company_group_id);
        }

        return $user->can('employees.delete') && $uc->id === $ec->id;
    }

    public function restore(User $user, Employee $employee): bool
    {
        return $this->update($user, $employee);
    }

    public function forceDelete(User $user, Employee $employee): bool
    {
        return $user->hasRole('admin');
    }
}
