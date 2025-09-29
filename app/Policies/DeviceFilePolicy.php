<?php

namespace App\Policies;

use App\Models\DeviceFile;
use App\Models\User;

class DeviceFilePolicy
{
    public function before(User $user, $ability)
    {
        // ha van isAdmin() nálad:
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DeviceFile $file): bool
    {
        return $file->device && $file->device->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true; // vagy jogosultság szerint szűkítsd
    }

    public function update(User $user, DeviceFile $file): bool
    {
        return $this->view($user, $file);
    }

    public function delete(User $user, DeviceFile $file): bool
    {
        return $this->view($user, $file);
    }
}
