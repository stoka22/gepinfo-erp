<?php

namespace App\Policies;

use App\Models\TimeEntry;
use App\Models\User;

class TimeEntryPolicy
{
    /**
     * Helper: user és bejegyzés ugyanahhoz a céghez tartozik?
     */
    protected function inSameCompany(User $user, TimeEntry $entry): bool
    {
        return (int) $user->company_id === (int) $entry->company_id && $user->company_id !== null;
    }

    public function viewAny(User $user): bool
    {
        // Lista nézet joga – tényleges rekordok úgyis cégre szűrve jönnek
        return $user->hasRole('admin') || $user->can('access user panel') || $user->can('approve time entries');
    }

    public function view(User $user, TimeEntry $entry): bool
    {
        if (!$this->inSameCompany($user, $entry)) return false;

        // admin/jóváhagyó láthat cég-szinten, vagy a saját dolgozó rekordja
        return $user->hasRole('admin')
            || $user->can('approve time entries')
            || optional($entry->employee)->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        // Létrehozás engedélye; a cég-konzisztenciát a form + CreatePage intézi
        return $user->hasRole('admin') || $user->can('access user panel');
    }

    public function update(User $user, TimeEntry $entry): bool
    {
        if (!$this->inSameCompany($user, $entry)) return false;

        // admin bármit; user a sajátját és csak pending állapotban
        if ($user->hasRole('admin')) return true;

        return optional($entry->employee)->user_id === $user->id
            && $entry->status->value === 'pending';
    }

    public function delete(User $user, TimeEntry $entry): bool
    {
        if (!$this->inSameCompany($user, $entry)) return false;

        // admin bármit; user a sajátját és csak pending állapotban
        if ($user->hasRole('admin')) return true;

        return optional($entry->employee)->user_id === $user->id
            && $entry->status->value === 'pending';
    }

    public function approve(User $user, TimeEntry $entry): bool
    {
        if (!$this->inSameCompany($user, $entry)) return false;

        // jóváhagyás: admin vagy külön permission, és csak pending
        return ($user->hasRole('admin') || $user->can('approve time entries'))
            && $entry->status->value === 'pending';
    }
}
