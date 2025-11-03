<?php

namespace App\Policies;

use App\Models\TimeEntry;
use App\Models\User;

class TimeEntryPolicy
{
    // ---- Segédek -----------------------------------------------------------

    /** Backed Enum / Unit Enum / plain string → string status */
    protected function statusVal(TimeEntry $entry): string
    {
        $s = $entry->status ?? null;
        if ($s instanceof \BackedEnum) return (string) $s->value;
        if ($s instanceof \UnitEnum)   return (string) $s->name;
        return (string) $s;
    }

    /** User és bejegyzés ugyanahhoz a céghez tartozik? */
    protected function inSameCompany(User $user, TimeEntry $entry): bool
    {
        return $user->company_id !== null
            && (int) $user->company_id === (int) $entry->company_id;
    }

    /** User és bejegyzés ugyanahhoz a cégcsoporthoz tartozik? (ha van ilyen jogosultság) */
    protected function inSameGroup(User $user, TimeEntry $entry): bool
    {
        $ucg = optional($user->company)->company_group_id;
        $ecg = optional($entry->company)->company_group_id;
        return $ucg !== null && $ucg === $ecg;
    }

    /** Bármelyik szerepkör? (Spatie) */
    protected function hasAnyRole(User $user, array $roles): bool
    {
        return method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole($roles)
            : false;
    }

    /** Bármelyik jogosultság? (Spatie) */
    protected function canAny(User $user, array $abilities): bool
    {
        foreach ($abilities as $a) {
            if ($user->can($a)) return true;
        }
        return false;
    }

    /** Van-e cégcsoport-szintű kezelési joga és ugyanazon csoportban van-e a rekord? */
    protected function groupScopeAllowed(User $user, TimeEntry $entry): bool
    {
        return $this->canAny($user, ['manage group time entries']) && $this->inSameGroup($user, $entry);
    }

    /** Van-e cégszintű kezelési joga és ugyanazon cégben van-e a rekord? */
    protected function companyScopeAllowed(User $user, TimeEntry $entry): bool
    {
        return $this->inSameCompany($user, $entry) && $this->canAny($user, [
            'view time entries', 'create time entries', 'edit time entries', 'delete time entries', 'approve time entries',
        ]);
    }

    /** A rekord a sajátja? (user → employee → time_entry) */
    protected function isOwn(User $user, TimeEntry $entry): bool
    {
        return optional($entry->employee)->user_id === $user->id;
    }

    // ---- Alap szerepkörök rövidítések -------------------------------------

    protected function isAdmin(User $user): bool
    {
        return method_exists($user, 'hasRole') && $user->hasRole('admin');
    }

    protected function isHRorManager(User $user): bool
    {
        return $this->hasAnyRole($user, ['hr', 'manager']);
    }

    protected function isSupervisor(User $user): bool
    {
        return $this->hasAnyRole($user, ['supervisor', 'lead']);
    }

    // ---- Műveletek ---------------------------------------------------------

    public function viewAny(User $user): bool
    {
        // Listanézet joga szélesebb körnek:
        // admin, HR/manager/supervisor, vagy akinek bármely releváns engedélye megvan
        return $this->isAdmin($user)
            || $this->isHRorManager($user)
            || $this->isSupervisor($user)
            || $this->canAny($user, [
                'access user panel',
                'view time entries',
                'create time entries',
                'edit time entries',
                'delete time entries',
                'approve time entries',
                'manage group time entries',
            ]);
    }

    public function view(User $user, TimeEntry $entry): bool
    {
        // admin mindig
        if ($this->isAdmin($user)) return true;

        // cégcsoport-jog + azonos csoport
        if ($this->groupScopeAllowed($user, $entry)) return true;

        // cégjog + azonos cég
        if ($this->companyScopeAllowed($user, $entry)) return true;

        // saját rekordját mindig láthatja
        if ($this->isOwn($user, $entry)) return true;

        return false;
    }

    public function create(User $user): bool
    {
        // admin, HR/manager, vagy explicit „create”
        return $this->isAdmin($user)
            || $this->isHRorManager($user)
            || $user->can('create time entries')
            || $user->can('access user panel');
    }

    public function update(User $user, TimeEntry $entry): bool
    {
        $status = $this->statusVal($entry);
        $type   = $entry->type instanceof \BackedEnum ? $entry->type->value : (string) $entry->type;

        // admin bármit
        if ($this->isAdmin($user)) return true;

        if ($type === 'presence') {
            if ($this->inSameCompany($user, $entry)) {
                // HR/manager/supervisor, vagy saját rekord
                if ($this->isHRorManager($user) || $this->isSupervisor($user) || $this->isOwn($user, $entry)) {
                    return true;
                }
            }
           $this->groupScopeAllowed($user, $entry);
        }

        // cégcsoport szint (pl. központi HR) – pending és approved szerkesztéséhez külön engedélyt is adhatunk
        if ($this->groupScopeAllowed($user, $entry)) {
            // ha van 'edit approved time entries', akkor approved-ot is szerkeszthet
            if ($status === 'approved') {
                return $user->can('edit approved time entries') || $user->can('edit time entries');
            }
            return $user->can('edit time entries') || $this->isHRorManager($user);
        }

        // cégszint HR/manager vagy „edit time entries” – pending mindig, approved csak külön engedéllyel
        if ($this->companyScopeAllowed($user, $entry) || $this->isHRorManager($user)) {
            if ($status === 'approved') {
                return $user->can('edit approved time entries') || $user->can('edit time entries');
            }
            return true;
        }

        // saját rekord: csak pending
        if ($this->isOwn($user, $entry)) {
            return $status === 'pending';
        }

        // supervisor: saját cégben engedjük pending-et (ha supervisor szerep van)
        if ($this->isSupervisor($user) && $this->inSameCompany($user, $entry)) {
            return $status === 'pending';
        }

        return false;
    }

    public function delete(User $user, TimeEntry $entry): bool
    {
        $status = $this->statusVal($entry);

        // admin bármit
        if ($this->isAdmin($user)) return true;

        // cégcsoport / cég szint – pending törölhető; approved törléshez külön „delete approved …” engedély
        if ($this->groupScopeAllowed($user, $entry) || $this->companyScopeAllowed($user, $entry) || $this->isHRorManager($user)) {
            if ($status === 'approved') {
                return $user->can('delete approved time entries') || $user->can('delete time entries');
            }
            return $user->can('delete time entries') || true;
        }

        // saját rekord: csak pending
        if ($this->isOwn($user, $entry)) {
            return $status === 'pending';
        }

        // supervisor: saját cégben pending törölhető
        if ($this->isSupervisor($user) && $this->inSameCompany($user, $entry)) {
            return $status === 'pending';
        }

        return false;
    }

    public function approve(User $user, TimeEntry $entry): bool
    {
        // csak azonos cég/csoport + megfelelő jog + pending
        if (! ($this->inSameCompany($user, $entry) || $this->groupScopeAllowed($user, $entry))) {
            return false;
        }

        return ($this->isAdmin($user)
                || $user->can('approve time entries')
                || $this->isHRorManager($user))
            && $this->statusVal($entry) === 'pending';
    }
}
