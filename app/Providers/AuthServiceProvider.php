<?php
// app/Providers/AuthServiceProvider.php

namespace App\Providers;

use App\Models\Employee;
use \App\Models\Company;
use  \App\Policies\CompanyPolicy;
use App\Policies\EmployeePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\TaskDependency;
use App\Policies\TaskDependencyPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Employee::class => EmployeePolicy::class,
        TaskDependency::class => TaskDependencyPolicy::class,
        Company::class => CompanyPolicy::class,
    ];

    public function boot(): void
    {
        // (opcionális) admin globális felülbírálás
        Gate::before(function ($user, $ability) {
            return (method_exists($user, 'isAdmin') ? $user->isAdmin() : (($user->role ?? null) === 'admin'))
                ? true : null;
        });
    }
}
