<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\OvertimeBalance;
use App\Models\TimeEntry;

// A fixture-ök forceCreate + needs_review:true kombinációval kerülnek be, hogy a
// TimeEntryObserver ne futtassa le rájuk a valódi elszámolást (ami felülírná a
// szándékosan beállított overtime_delta_minutes tesztértéket, és automatikusan
// létrehozna egy OvertimeBalance sort is) — kizárólag a DEDUP PARANCS törlés/
// egyenleg-korrekció logikáját teszteljük, nem az observert.

it('reports but does not delete anything with --dry', function () {
    $company = Company::create(['name' => 'Gépi Dedup Parancs Kft.']);
    $employee = Employee::create(['name' => 'Gépi Dedup Parancs Teszt', 'company_id' => $company->id]);

    TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out', 'needs_review' => true,
        'start_date' => '2026-01-05', 'start_time' => '07:52:00',
        'end_date' => '2026-01-05', 'end_time' => '16:30:00',
        'entry_method' => 'gépi', 'overtime_delta_minutes' => 0,
    ]);
    TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out', 'needs_review' => true,
        'start_date' => '2026-01-05', 'start_time' => '08:00:00', 'raw_start_time' => '07:52:00',
        'end_date' => '2026-01-05', 'end_time' => '16:30:00',
        'entry_method' => 'worklog-import', 'overtime_delta_minutes' => 90,
    ]);
    OvertimeBalance::create(['employee_id' => $employee->id, 'company_id' => $company->id, 'balance_minutes' => 90]);

    Artisan::call('attendance:dedup-gepi-vs-worklog', ['--dry' => true]);

    expect(TimeEntry::where('employee_id', $employee->id)->where('type', 'presence')->count())->toBe(2);
    expect(OvertimeBalance::where('employee_id', $employee->id)->value('balance_minutes'))->toBe(90);
});

it('deletes the worklog-import duplicate of a gépi entry and reverses its delta from the balance', function () {
    $company = Company::create(['name' => 'Gépi Dedup Parancs Kft. 2']);
    $employee = Employee::create(['name' => 'Gépi Dedup Parancs Teszt 2', 'company_id' => $company->id]);

    $gepi = TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out', 'needs_review' => true,
        'start_date' => '2026-01-05', 'start_time' => '07:52:00',
        'end_date' => '2026-01-05', 'end_time' => '16:30:00',
        'entry_method' => 'gépi', 'overtime_delta_minutes' => 0,
    ]);
    $worklog = TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out', 'needs_review' => true,
        'start_date' => '2026-01-05', 'start_time' => '08:00:00', 'raw_start_time' => '07:52:00',
        'end_date' => '2026-01-05', 'end_time' => '16:30:00',
        'entry_method' => 'worklog-import', 'overtime_delta_minutes' => 90,
    ]);
    OvertimeBalance::create(['employee_id' => $employee->id, 'company_id' => $company->id, 'balance_minutes' => 90]);

    Artisan::call('attendance:dedup-gepi-vs-worklog');

    expect(TimeEntry::find($worklog->id))->toBeNull();
    expect(TimeEntry::find($gepi->id))->not->toBeNull();
    expect(OvertimeBalance::where('employee_id', $employee->id)->value('balance_minutes'))->toBe(0);
});

it('leaves unrelated presence entries (different end_time or no gépi counterpart) untouched', function () {
    $company = Company::create(['name' => 'Gépi Dedup Parancs Kft. 3']);
    $employee = Employee::create(['name' => 'Gépi Dedup Parancs Teszt 3', 'company_id' => $company->id]);

    // Valódi split shift: két különböző kilépési idővel – NEM duplikátum.
    TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out', 'needs_review' => true,
        'start_date' => '2026-01-05', 'start_time' => '07:52:00',
        'end_date' => '2026-01-05', 'end_time' => '12:00:00',
        'entry_method' => 'gépi', 'overtime_delta_minutes' => 0,
    ]);
    TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $company->id,
        'type' => 'presence', 'status' => 'checked_out', 'needs_review' => true,
        'start_date' => '2026-01-05', 'start_time' => '13:00:00',
        'end_date' => '2026-01-05', 'end_time' => '16:30:00',
        'entry_method' => 'worklog-import', 'overtime_delta_minutes' => 40,
    ]);

    Artisan::call('attendance:dedup-gepi-vs-worklog');

    expect(TimeEntry::where('employee_id', $employee->id)->where('type', 'presence')->count())->toBe(2);
});
