<?php

use Illuminate\Support\Facades\Auth;
use App\Models\Employee;

// app/Observers/EmployeeObserver.php
class EmployeeObserver
{
    public function creating(Employee $employee): void
    {
        if (Auth::user()->id() && ! $employee->created_by_user_id) {
            $employee->created_by_user_id = Auth::user()->id();
        }
    }
}
// AppServiceProvider::boot()
Employee::observe(EmployeeObserver::class);
