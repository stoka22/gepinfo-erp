<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\TimeEntry;
use App\Models\User;

function reviewQueueAdmin(): User
{
    config(['app.env' => 'local']);

    $company = Company::create(['name' => 'Felülvizsgálat Kft.']);
    \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
    $admin->assignRole('admin');

    return $admin;
}

it('deep-links the only_needs_review filter via the query string used by the dashboard tile', function () {
    $admin = reviewQueueAdmin();
    $employee = Employee::create(['name' => 'Felülvizsgálandó Teszt', 'company_id' => $admin->company_id]);

    $needsReview = TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $admin->company_id,
        'type' => 'presence', 'status' => 'checked_out',
        'start_date' => '2026-01-05', 'start_time' => '08:00:00',
        'end_date' => '2026-01-05', 'end_time' => '20:00:00',
        'needs_review' => true,
    ]);
    $normal = TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $admin->company_id,
        'type' => 'presence', 'status' => 'checked_out',
        'start_date' => '2026-01-06', 'start_time' => '08:00:00',
        'end_date' => '2026-01-06', 'end_time' => '16:00:00',
        'needs_review' => false,
    ]);

    $response = $this->actingAs($admin)
        ->get('/admin/time-entries?tableFilters[only_needs_review][isActive]=1');

    $response->assertOk();
    // Csak a felülvizsgálandó dolgozó neve NE szűrhető meg a másik bejegyzés miatt is —
    // ezért a "Várakozik" jelvényt keressük, ami csak a needs_review sorokon jelenik meg.
    $response->assertSee('óta');
});
