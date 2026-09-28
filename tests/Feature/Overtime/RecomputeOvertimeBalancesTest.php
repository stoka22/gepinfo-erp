<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\OvertimeBalance;
use App\Models\TimeEntry;

it('leaves the database untouched in --dry-run mode but reports the expected change', function () {
    $company = Company::create(['name' => 'Recompute Dry Kft.']);
    $employee = Employee::create(['name' => 'Recompute Dry Teszt', 'company_id' => $company->id]);

    $entry = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $company->id,
        'type' => 'presence',
        'status' => 'checked_in',
        'start_date' => '2026-01-05',
        'start_time' => '08:00:00',
    ]);
    $entry->update(['end_date' => '2026-01-05', 'end_time' => '19:00:00', 'status' => 'checked_out']); // +150

    $balance = OvertimeBalance::where('employee_id', $employee->id)->first();
    $balance->update(['balance_minutes' => 999999]); // szimulált korábbi elcsúszás

    $this->artisan('overtime:recompute-balances --dry-run')
        ->expectsOutputToContain('Recompute Dry Teszt')
        ->assertExitCode(0);

    $balance->refresh();
    expect($balance->balance_minutes)->toBe(999999); // dry-run: NEM írt az adatbázisba
});

it('corrects a corrupted balance to the true sum of the entries when run for real', function () {
    $company = Company::create(['name' => 'Recompute Éles Kft.']);
    $employee = Employee::create(['name' => 'Recompute Éles Teszt', 'company_id' => $company->id]);

    $entry = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $company->id,
        'type' => 'presence',
        'status' => 'checked_in',
        'start_date' => '2026-01-05',
        'start_time' => '08:00:00',
    ]);
    $entry->update(['end_date' => '2026-01-05', 'end_time' => '19:00:00', 'status' => 'checked_out']); // +150

    $balance = OvertimeBalance::where('employee_id', $employee->id)->first();
    $balance->update(['balance_minutes' => 999999]); // szimulált korábbi elcsúszás

    $this->artisan('overtime:recompute-balances')
        ->assertExitCode(0);

    $balance->refresh();
    expect($balance->balance_minutes)->toBe(150); // helyreállítva a tényleges összegre

    // A kézi korrekciót (manual_adjustment_minutes) a parancs nem érinti.
    expect($balance->manual_adjustment_minutes)->toBe(0);
});

it('does not touch an already-correct balance', function () {
    $company = Company::create(['name' => 'Recompute Noop Kft.']);
    $employee = Employee::create(['name' => 'Recompute Noop Teszt', 'company_id' => $company->id]);

    $entry = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $company->id,
        'type' => 'presence',
        'status' => 'checked_in',
        'start_date' => '2026-01-05',
        'start_time' => '08:00:00',
    ]);
    $entry->update(['end_date' => '2026-01-05', 'end_time' => '19:00:00', 'status' => 'checked_out']); // +150

    $this->artisan('overtime:recompute-balances')
        ->doesntExpectOutputToContain('Recompute Noop Teszt')
        ->assertExitCode(0);
});
