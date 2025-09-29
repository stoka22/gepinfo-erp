<?php

namespace App\Providers;

//use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;


use App\Models\Employee;
use App\Policies\EmployeePolicy;

use App\Models\Firmware;
use App\Policies\FirmwarePolicy;

use App\Models\DeviceFile;
use App\Policies\DeviceFilePolicy;

use App\Models\TimeEntry;
use App\Policies\TimeEntryPolicy;

use App\Models\Partner;
use App\Policies\PartnerPolicy;

class AppServiceProvider extends ServiceProvider
{
    
        
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Support\FeatureGate::class, fn () => new \App\Support\FeatureGate());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
                
        // Policy-k regisztrálása
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(Firmware::class, FirmwarePolicy::class);
        Gate::policy(DeviceFile::class, DeviceFilePolicy::class);
        Gate::policy(TimeEntry::class, TimeEntryPolicy::class);
        Gate::policy(Partner::class, PartnerPolicy::class);
    }

    

}
