<?php
// app/Policies/PartnerPolicy.php
namespace App\Policies;

use App\Models\Partner;
use App\Models\User;

class PartnerPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // szűrés az Eloquent query-ben történik
    }

    public function view(User $user, Partner $partner): bool
    {
        if ($user->isAdmin()) return true;
        return $user->company
            && $partner->companies()->where('companies.id', $user->company_id)->exists();
    }

    public function create(User $user): bool
    {
        return (bool)$user->company_id || $user->isAdmin();
    }

    public function update(User $user, Partner $partner): bool
    {
        if ($user->isAdmin()) return true;
        // csak ha a saját cége partneréről van szó
        return $user->company
            && $partner->companies()->where('companies.id', $user->company_id)->exists();
    }

    public function delete(User $user, Partner $partner): bool
    {
        return $this->update($user, $partner);
    }
}
