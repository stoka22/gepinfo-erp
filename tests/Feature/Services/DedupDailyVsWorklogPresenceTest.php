<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\OvertimeBalance;
use App\Models\TimeEntry;

// A fixture-ök forceCreate + needs_review:true kombinációval kerülnek be, hogy a
// TimeEntryObserver ne futtassa le rájuk a valódi elszámolást -- kizárólag a DEDUP
// PARANCS törlés/egyenleg-korrekció logikáját teszteljük, nem az observert. (Korábban
// ehhez a parancshoz egyáltalán nem volt teszt.)

it('reports but does not delete anything with --dry', function () {
    $company = Company::create(['name' => 'Napi Dedup Parancs Kft.']);
    $employee = Employee::create(['name' => 'Napi Dedup Parancs Teszt', 'company_id' => $company->id]);

    TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out', 'needs_review' => true,
        'start_date' => '2026-01-05', 'start_time' => '08:00:00',
        'end_date' => '2026-01-05', 'end_time' => '16:30:00',
        'entry_method' => 'daily-import', 'overtime_delta_minutes' => 0,
    ]);
    TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out', 'needs_review' => true,
        'start_date' => '2026-01-05', 'start_time' => '08:00:00',
        'end_date' => '2026-01-05', 'end_time' => '16:30:00',
        'entry_method' => 'worklog-import', 'overtime_delta_minutes' => 90,
    ]);
    OvertimeBalance::create(['employee_id' => $employee->id, 'company_id' => $company->id, 'balance_minutes' => 90]);

    Artisan::call('attendance:dedup-daily-vs-worklog', ['--dry' => true]);

    expect(TimeEntry::where('employee_id', $employee->id)->where('type', 'presence')->count())->toBe(2);
    expect(OvertimeBalance::where('employee_id', $employee->id)->value('balance_minutes'))->toBe(90);
});

it('deletes the worklog-import duplicate of a daily-import entry and recomputes the balance from the remaining entries', function () {
    $company = Company::create(['name' => 'Napi Dedup Parancs Kft. 2']);
    $employee = Employee::create(['name' => 'Napi Dedup Parancs Teszt 2', 'company_id' => $company->id]);

    $daily = TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out', 'needs_review' => true,
        'start_date' => '2026-01-05', 'start_time' => '08:00:00',
        'end_date' => '2026-01-05', 'end_time' => '16:30:00',
        'entry_method' => 'daily-import', 'overtime_delta_minutes' => 0,
    ]);
    $worklog = TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out', 'needs_review' => true,
        'start_date' => '2026-01-05', 'start_time' => '08:00:00',
        'end_date' => '2026-01-05', 'end_time' => '16:30:00',
        'entry_method' => 'worklog-import', 'overtime_delta_minutes' => 90,
    ]);
    OvertimeBalance::create(['employee_id' => $employee->id, 'company_id' => $company->id, 'balance_minutes' => 90]);

    Artisan::call('attendance:dedup-daily-vs-worklog');

    expect(TimeEntry::find($worklog->id))->toBeNull();
    expect(TimeEntry::find($daily->id))->not->toBeNull();
    // recomputeBalance(): a megmaradó "daily" sor delta-ja (0) az egyedüli forrás -->
    // az egyenleg a valódi maradék összegre áll, NEM egy relatív decrement()-tel.
    expect(OvertimeBalance::where('employee_id', $employee->id)->value('balance_minutes'))->toBe(0);
});

it('leaves unrelated presence entries (different start_time or no daily-import counterpart) untouched', function () {
    $company = Company::create(['name' => 'Napi Dedup Parancs Kft. 3']);
    $employee = Employee::create(['name' => 'Napi Dedup Parancs Teszt 3', 'company_id' => $company->id]);

    TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out', 'needs_review' => true,
        'start_date' => '2026-01-05', 'start_time' => '08:00:00',
        'end_date' => '2026-01-05', 'end_time' => '12:00:00',
        'entry_method' => 'daily-import', 'overtime_delta_minutes' => 0,
    ]);
    TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out', 'needs_review' => true,
        'start_date' => '2026-01-05', 'start_time' => '13:00:00',
        'end_date' => '2026-01-05', 'end_time' => '16:30:00',
        'entry_method' => 'worklog-import', 'overtime_delta_minutes' => 40,
    ]);

    Artisan::call('attendance:dedup-daily-vs-worklog');

    expect(TimeEntry::where('employee_id', $employee->id)->where('type', 'presence')->count())->toBe(2);
});
