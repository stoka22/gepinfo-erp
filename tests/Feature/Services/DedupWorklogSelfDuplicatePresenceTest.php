<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\OvertimeBalance;
use App\Models\TimeEntry;

it('deletes the later worklog-import duplicate (close end time, drifted start) and reverses its delta', function () {
    $company = Company::create(['name' => 'Self Dedup Kft.']);
    $employee = Employee::create(['name' => 'Self Dedup Teszt', 'company_id' => $company->id]);

    $keep = TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out', 'needs_review' => true,
        'start_date' => '2026-01-05', 'start_time' => '14:30:00', 'raw_start_time' => '14:11:34',
        'end_date' => '2026-01-05', 'end_time' => '22:59:55',
        'entry_method' => 'worklog-import', 'overtime_delta_minutes' => 557,
    ]);
    $delete = TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out', 'needs_review' => true,
        'start_date' => '2026-01-05', 'start_time' => '14:00:00', 'raw_start_time' => '13:52:42',
        'end_date' => '2026-01-05', 'end_time' => '22:59:52',
        'entry_method' => 'worklog-import', 'overtime_delta_minutes' => 0,
    ]);
    OvertimeBalance::create(['employee_id' => $employee->id, 'company_id' => $company->id, 'balance_minutes' => 557]);

    Artisan::call('attendance:dedup-worklog-self-duplicate');

    expect(TimeEntry::find($keep->id))->not->toBeNull();
    expect(TimeEntry::find($delete->id))->toBeNull();
    expect(OvertimeBalance::where('employee_id', $employee->id)->value('balance_minutes'))->toBe(557);
});

it('leaves genuinely different same-day shifts (far apart end times) untouched', function () {
    $company = Company::create(['name' => 'Self Dedup Kft. 2']);
    $employee = Employee::create(['name' => 'Self Dedup Teszt 2', 'company_id' => $company->id]);

    TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out', 'needs_review' => true,
        'start_date' => '2026-01-05', 'start_time' => '06:00:00',
        'end_date' => '2026-01-05', 'end_time' => '12:00:00',
        'entry_method' => 'worklog-import', 'overtime_delta_minutes' => 0,
    ]);
    TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out', 'needs_review' => true,
        'start_date' => '2026-01-05', 'start_time' => '13:00:00',
        'end_date' => '2026-01-05', 'end_time' => '18:00:00',
        'entry_method' => 'worklog-import', 'overtime_delta_minutes' => 0,
    ]);

    Artisan::call('attendance:dedup-worklog-self-duplicate');

    expect(TimeEntry::where('employee_id', $employee->id)->where('type', 'presence')->count())->toBe(2);
});

it('reports but does not delete anything with --dry', function () {
    $company = Company::create(['name' => 'Self Dedup Kft. 3']);
    $employee = Employee::create(['name' => 'Self Dedup Teszt 3', 'company_id' => $company->id]);

    TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out', 'needs_review' => true,
        'start_date' => '2026-01-05', 'start_time' => '14:30:00',
        'end_date' => '2026-01-05', 'end_time' => '22:59:55',
        'entry_method' => 'worklog-import', 'overtime_delta_minutes' => 557,
    ]);
    TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out', 'needs_review' => true,
        'start_date' => '2026-01-05', 'start_time' => '14:00:00',
        'end_date' => '2026-01-05', 'end_time' => '22:59:52',
        'entry_method' => 'worklog-import', 'overtime_delta_minutes' => 0,
    ]);

    Artisan::call('attendance:dedup-worklog-self-duplicate', ['--dry' => true]);

    expect(TimeEntry::where('employee_id', $employee->id)->where('type', 'presence')->count())->toBe(2);
});
