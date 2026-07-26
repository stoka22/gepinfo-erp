<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\OvertimeBalance;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Carbon;

it('force-closes a presence entry open for more than 12 hours and flags it for review', function () {
    Carbon::setTestNow('2026-01-05 21:00:00');

    $company = Company::create(['name' => 'Test Kft.']);
    $employee = Employee::create(['name' => 'Elfelejtett', 'company_id' => $company->id]);
    $user = User::factory()->create(['company_id' => $company->id]);

    $entry = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $company->id,
        'type' => 'presence',
        'status' => 'checked_in',
        'start_date' => '2026-01-05',
        'start_time' => '08:00:00', // 13 órája nyitva
        'requested_by' => $user->id,
        'approved_by' => $user->id,
    ]);

    $this->artisan('attendance:auto-checkout')->assertExitCode(0);

    $entry->refresh();
    expect($entry->status)->toBe(\App\Enums\TimeEntryStatus::CheckedOut);
    expect($entry->needs_review)->toBeTrue();
    expect($entry->overtime_settled_at)->toBeNull();
    expect($entry->end_date->toDateString())->toBe('2026-01-05');
    expect($entry->end_time->format('H:i:s'))->toBe('20:00:00');

    // A keretet nem érintette, amíg felülvizsgálatra vár.
    expect(OvertimeBalance::where('employee_id', $employee->id)->exists())->toBeFalse();

    Carbon::setTestNow();
});

it('does not touch a presence entry that has been open for less than 12 hours', function () {
    Carbon::setTestNow('2026-01-05 14:00:00');

    $company = Company::create(['name' => 'Test Kft.']);
    $employee = Employee::create(['name' => 'Frissen bejelentkezett', 'company_id' => $company->id]);
    $user = User::factory()->create(['company_id' => $company->id]);

    $entry = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $company->id,
        'type' => 'presence',
        'status' => 'checked_in',
        'start_date' => '2026-01-05',
        'start_time' => '08:00:00', // 6 órája nyitva
        'requested_by' => $user->id,
        'approved_by' => $user->id,
    ]);

    $this->artisan('attendance:auto-checkout')->assertExitCode(0);

    $entry->refresh();
    expect($entry->status)->toBe(\App\Enums\TimeEntryStatus::CheckedIn);
    expect($entry->end_time)->toBeNull();

    Carbon::setTestNow();
});

it('does not touch already-closed presence entries', function () {
    Carbon::setTestNow('2026-01-06 10:00:00');

    $company = Company::create(['name' => 'Test Kft.']);
    $employee = Employee::create(['name' => 'Már kijelentkezett', 'company_id' => $company->id]);
    $user = User::factory()->create(['company_id' => $company->id]);

    $entry = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $company->id,
        'type' => 'presence',
        'status' => 'checked_in',
        'start_date' => '2026-01-05',
        'start_time' => '08:00:00',
        'requested_by' => $user->id,
        'approved_by' => $user->id,
    ]);
    $entry->update(['end_date' => '2026-01-05', 'end_time' => '16:00:00', 'status' => 'checked_out']);
    $settledDelta = $entry->fresh()->overtime_delta_minutes;

    $this->artisan('attendance:auto-checkout')->assertExitCode(0);

    $entry->refresh();
    expect($entry->needs_review)->toBeFalse();
    expect($entry->overtime_delta_minutes)->toBe($settledDelta);

    Carbon::setTestNow();
});
