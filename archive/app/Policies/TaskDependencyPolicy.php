<?php
declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\TaskDependency;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskDependencyPolicy
{
    use HandlesAuthorization;

    public function viewAny(?User $user): bool { return true; }
    public function view(?User $user, TaskDependency $model): bool { return true; }
    public function create(?User $user): bool { return true; }
    public function update(?User $user, TaskDependency $model): bool { return true; }
    public function delete(?User $user, TaskDependency $model): bool { return true; }
}
