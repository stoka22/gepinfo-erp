<?php
// app/Policies/EmployeePolicy.php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    /** Admin mindent lát / tehet — ha nincs isAdmin(), a role='admin' az alap. */
    protected function isAdmin(User $user): bool
    {
        return method_exists($user, 'isAdmin')
            ? (bool) $user->isAdmin()
            : (($user->role ?? null) === 'admin');
    }

    public function viewAny(User $user): bool
    {
        return true; // a tényleges rekordkört a Resource query szűri
    }

    public function view(User $user, Employee $employee): bool
    {
        if ($this->isAdmin($user)) return true;

        // ha van employees.company_id mező:
        if (isset($employee->company_id)) {
            return (int) $employee->company_id === (int) $user->company_id;
        }

        // fallback: a dolgozó tulaj user cégéhez tartozik?
        $ownerCompanyId = $employee->relationLoaded('owner')
            ? optional($employee->owner)->company_id
            : optional($employee->owner()->select('company_id')->first())->company_id;

        return (int) $ownerCompanyId === (int) $user->company_id;
    }

    /** Létrehozás: admin bármikor; nem-admin csak ha van cége (és ott fogjuk rögzíteni). */
    public function create(User $user): bool
    {
        if ($this->isAdmin($user)) return true;
        return ! empty($user->company_id);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->view($user, $employee);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->view($user, $employee);
    }
}