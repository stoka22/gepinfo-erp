<?php

use App\Enums\TimeEntryStatus;
use App\Enums\TimeEntryType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\TimeEntry;

it('flags a completely empty workday with a pending, needs-review vacation placeholder', function () {
    $company = Company::create(['name' => 'Hiányzó Nap Kft.']);
    $employee = Employee::create(['name' => 'Hiányzó Nap Teszt', 'company_id' => $company->id, 'hired_at' => '2025-01-01']);

    // 2026-01-05 (hétfő) -- egyetlen bejegyzés sincs erre a napra.
    Artisan::call('attendance:flag-missing-days', ['--from' => '2026-01-05', '--to' => '2026-01-05']);

    $flagged = TimeEntry::where('employee_id', $employee->id)->where('start_date', '2026-01-05')->first();
    expect($flagged)->not->toBeNull();
    expect($flagged->type)->toBe(TimeEntryType::Vacation);
    expect($flagged->status)->toBe(TimeEntryStatus::Pending);
    expect($flagged->needs_review)->toBeTrue();
});

it('does not flag a day that already has any entry (presence, vacation, sick leave)', function () {
    $company = Company::create(['name' => 'Van Bejegyzés Kft.']);
    $employee = Employee::create(['name' => 'Van Bejegyzés Teszt', 'company_id' => $company->id, 'hired_at' => '2025-01-01']);

    TimeEntry::create([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => TimeEntryType::SickLeave->value, 'status' => TimeEntryStatus::Approved->value,
        'start_date' => '2026-01-05', 'end_date' => '2026-01-05',
    ]);

    Artisan::call('attendance:flag-missing-days', ['--from' => '2026-01-05', '--to' => '2026-01-05']);

    expect(TimeEntry::where('employee_id', $employee->id)->where('start_date', '2026-01-05')->count())->toBe(1);
});

it('does not flag weekends or days before the employee was hired', function () {
    $company = Company::create(['name' => 'Hétvége Kft.']);
    $employee = Employee::create(['name' => 'Hétvége Teszt', 'company_id' => $company->id, 'hired_at' => '2026-01-10']);

    // 2026-01-03 (szombat) -- hétvége, és a felvétel előtti nap is.
    Artisan::call('attendance:flag-missing-days', ['--from' => '2026-01-03', '--to' => '2026-01-09']);

    expect(TimeEntry::where('employee_id', $employee->id)->count())->toBe(0);
});

it('never flags today or future days', function () {
    $company = Company::create(['name' => 'Jövő Kft.']);
    $employee = Employee::create(['name' => 'Jövő Teszt', 'company_id' => $company->id, 'hired_at' => '2020-01-01']);

    $today = now()->toDateString();
    Artisan::call('attendance:flag-missing-days', ['--from' => $today, '--to' => $today]);

    expect(TimeEntry::where('employee_id', $employee->id)->count())->toBe(0);
});

it('writes nothing in --dry mode', function () {
    $company = Company::create(['name' => 'Dry Kft.']);
    $employee = Employee::create(['name' => 'Dry Teszt', 'company_id' => $company->id, 'hired_at' => '2025-01-01']);

    Artisan::call('attendance:flag-missing-days', ['--from' => '2026-01-05', '--to' => '2026-01-05', '--dry' => true]);

    expect(TimeEntry::where('employee_id', $employee->id)->count())->toBe(0);
});

it('is idempotent: running twice over the same range does not create duplicate placeholders', function () {
    $company = Company::create(['name' => 'Ismétlés Kft.']);
    $employee = Employee::create(['name' => 'Ismétlés Teszt', 'company_id' => $company->id, 'hired_at' => '2025-01-01']);

    Artisan::call('attendance:flag-missing-days', ['--from' => '2026-01-05', '--to' => '2026-01-05']);
    Artisan::call('attendance:flag-missing-days', ['--from' => '2026-01-05', '--to' => '2026-01-05']);

    expect(TimeEntry::where('employee_id', $employee->id)->where('start_date', '2026-01-05')->count())->toBe(1);
});
