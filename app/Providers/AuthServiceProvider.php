<?php
// app/Providers/AuthServiceProvider.php

namespace App\Providers;

use \App\Models\Company;
use App\Models\Employee;
use App\Models\TimeEntry;
use App\Models\TaskDependency;
use App\Policies\EmployeePolicy;
use  \App\Policies\CompanyPolicy;
use App\Policies\TimeEntryPolicy;
use Illuminate\Support\Facades\Gate;
use App\Policies\TaskDependencyPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Employee::class => EmployeePolicy::class,
        TaskDependency::class => TaskDependencyPolicy::class,
        Company::class => CompanyPolicy::class,
        TimeEntry::class => TimeEntryPolicy::class,
    ];

    public function boot(): void

    {
        $this->registerPolicies();
        // (opcionális) admin globális felülbírálás
        Gate::before(function ($user, $ability) {
            return (method_exists($user, 'isAdmin') ? $user->isAdmin() : (($user->role ?? null) === 'admin'))
                ? true : null;
        });
    }
}
