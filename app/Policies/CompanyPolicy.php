<?php

// app/Policies/CompanyPolicy.php
namespace App\Policies;

use App\Models\User;
use App\Models\Company;

class CompanyPolicy
{
    public function viewAny(User $user): bool { return $user->can('companies.viewAny') || $user->hasRole('admin'); }
    public function view(User $user, Company $company): bool { return $user->can('companies.view') || $user->hasRole('admin'); }
    public function create(User $user): bool { return $user->can('companies.create') || $user->hasRole('admin'); }
    public function update(User $user, Company $company): bool { return $user->can('companies.update') || $user->hasRole('admin'); }
    public function delete(User $user, Company $company): bool { return $user->can('companies.delete') || $user->hasRole('admin'); }

    // egyedi: felhasználók hozzárendelése
    public function attachUsers(User $user, Company $company): bool { return $user->can('companies.attachUsers') || $user->hasRole('admin'); }
}
