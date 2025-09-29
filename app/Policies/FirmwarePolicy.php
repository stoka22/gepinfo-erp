<?php

namespace App\Policies;

use App\Models\Firmware;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FirmwarePolicy
{
    public function before(User $user, $ability)
    {
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }
    }

    public function viewAny(User $user): bool { return true; }

    public function view(User $user, Firmware $fw): bool
    {
        return $fw->device_id === null
            || ($fw->device && $fw->device->user_id === $user->id);
    }

    public function create(User $user): bool { return true; }

    public function update(User $user, Firmware $fw): bool
    {
        return $this->view($user, $fw);
    }

    public function delete(User $user, Firmware $fw): bool
    {
        return $this->update($user, $fw);
    }
}