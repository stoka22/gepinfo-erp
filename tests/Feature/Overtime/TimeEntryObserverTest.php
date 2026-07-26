<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\OvertimeBalance;
use App\Models\TimeEntry;
use App\Models\User;

function overtimeEmployee(): Employee
{
    $company = Company::create(['name' => 'Test Kft.']);

    return Employee::create(['name' => 'Dolgozó', 'company_id' => $company->id]);
}

function overtimeUser(int $companyId): User
{
    return User::factory()->create(['company_id' => $companyId]);
}

it('settles the balance when a presence entry is checked out', function () {
    $employee = overtimeEmployee();
    $user = overtimeUser($employee->company_id);

    $entry = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $employee->company_id,
        'type' => 'presence',
        'status' => 'checked_in',
        'start_date' => '2026-01-05',
        'start_time' => '08:00:00',
        'requested_by' => $user->id,
        'approved_by' => $user->id,
    ]);

    $entry->update([
        'end_date' => '2026-01-05',
        'end_time' => '19:00:00', // 11 óra -> +150 perc túlóra
        'status' => 'checked_out',
    ]);

    $entry->refresh();
    expect((float) $entry->hours)->toBe(11.0);
    expect($entry->overtime_delta_minutes)->toBe(150);
    expect($entry->overtime_settled_at)->not->toBeNull();

    $balance = OvertimeBalance::where('employee_id', $employee->id)->first();
    expect($balance->balance_minutes)->toBe(150);
});

it('reduces the balance below the standard workday and lets it go negative', function () {
    $employee = overtimeEmployee();
    $user = overtimeUser($employee->company_id);

    $entry = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $employee->company_id,
        'type' => 'presence',
        'status' => 'checked_in',
        'start_date' => '2026-01-05',
        'start_time' => '08:00:00',
        'requested_by' => $user->id,
        'approved_by' => $user->id,
    ]);

    $entry->update([
        'end_date' => '2026-01-05',
        'end_time' => '13:00:00', // 5 óra -> -210 perc
        'status' => 'checked_out',
    ]);

    $balance = OvertimeBalance::where('employee_id', $employee->id)->first();
    expect($balance->balance_minutes)->toBe(-210);
});

it('does not settle an entry flagged for review', function () {
    $employee = overtimeEmployee();
    $user = overtimeUser($employee->company_id);

    $entry = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $employee->company_id,
        'type' => 'presence',
        'status' => 'checked_in',
        'start_date' => '2026-01-05',
        'start_time' => '08:00:00',
        'requested_by' => $user->id,
        'approved_by' => $user->id,
    ]);

    $entry->update([
        'end_date' => '2026-01-05',
        'end_time' => '20:00:00',
        'status' => 'checked_out',
        'needs_review' => true,
    ]);

    $entry->refresh();
    expect($entry->overtime_settled_at)->toBeNull();
    expect(OvertimeBalance::where('employee_id', $employee->id)->exists())->toBeFalse();

    // Admin jóváhagyja: needs_review lekapcsolása -> ekkor kell elszámolni
    $entry->update(['needs_review' => false]);
    $entry->refresh();

    expect($entry->overtime_settled_at)->not->toBeNull();
    expect($entry->overtime_delta_minutes)->toBe(12 * 60 - 510);

    $balance = OvertimeBalance::where('employee_id', $employee->id)->first();
    expect($balance->balance_minutes)->toBe(12 * 60 - 510);
});

it('reverses the old delta and applies the new one when a settled entry is corrected', function () {
    $employee = overtimeEmployee();
    $user = overtimeUser($employee->company_id);

    $entry = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $employee->company_id,
        'type' => 'presence',
        'status' => 'checked_in',
        'start_date' => '2026-01-05',
        'start_time' => '08:00:00',
        'requested_by' => $user->id,
        'approved_by' => $user->id,
    ]);

    $entry->update([
        'end_date' => '2026-01-05',
        'end_time' => '19:00:00', // 11 óra, delta=+150
        'status' => 'checked_out',
    ]);

    $balance = OvertimeBalance::where('employee_id', $employee->id)->first();
    expect($balance->balance_minutes)->toBe(150);

    // Admin korrekció: valójában 16:00-kor ment el (8 óra, delta=-30)
    $entry->update(['end_time' => '16:00:00']);
    $entry->refresh();
    $balance->refresh();

    expect($entry->overtime_delta_minutes)->toBe(-30);
    expect($balance->balance_minutes)->toBe(-30);
});

it('ignores non-presence entries entirely', function () {
    $employee = overtimeEmployee();
    $user = overtimeUser($employee->company_id);

    TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $employee->company_id,
        'type' => 'vacation',
        'status' => 'approved',
        'start_date' => '2026-01-05',
        'end_date' => '2026-01-06',
        'requested_by' => $user->id,
        'approved_by' => $user->id,
    ]);

    expect(OvertimeBalance::where('employee_id', $employee->id)->exists())->toBeFalse();
});
