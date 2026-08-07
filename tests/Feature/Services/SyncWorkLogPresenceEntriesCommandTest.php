<?php

use App\Imports\WorkLogsImport;
use App\Models\Company;
use App\Models\Employee;
use App\Models\TimeEntry;
use App\Models\WorkLog;
use Illuminate\Support\Facades\Artisan;

it('reports a dry-run summary without writing anything', function () {
    $company = Company::create(['name' => 'Sync Cmd Kft.']);
    $employee = Employee::create(['name' => 'Sync Cmd Teszt', 'company_id' => $company->id]);

    WorkLog::create([
        'nev' => 'Sync Cmd Teszt', 'munkakor' => 'Op', 'helyiseg' => 'Uzem',
        'kezdes' => '2026-06-01 08:00:00', 'vege' => '2026-06-01 16:00:00',
        'employee_id' => $employee->id,
    ]);

    Artisan::call('work-logs:sync-presence', ['--dry' => true]);
    $output = Artisan::output();

    expect(TimeEntry::where('employee_id', $employee->id)->count())->toBe(0);
    expect($output)->toContain('próbafuttatás');
});

it('creates missing presence entries for all employee-linked WorkLog rows, and skips already-synced ones', function () {
    $company = Company::create(['name' => 'Sync Cmd Kft. 2']);
    $employee = Employee::create(['name' => 'Sync Cmd Teszt 2', 'company_id' => $company->id]);

    $unsyncedLog = WorkLog::create([
        'nev' => 'Sync Cmd Teszt 2', 'munkakor' => 'Op', 'helyiseg' => 'Uzem',
        'kezdes' => '2026-06-02 08:00:00', 'vege' => '2026-06-02 16:00:00',
        'employee_id' => $employee->id,
    ]);

    $alreadySyncedLog = WorkLog::create([
        'nev' => 'Sync Cmd Teszt 2', 'munkakor' => 'Op', 'helyiseg' => 'Uzem',
        'kezdes' => '2026-06-03 08:00:00', 'vege' => '2026-06-03 16:00:00',
        'employee_id' => $employee->id,
    ]);
    (new WorkLogsImport)->createPresenceEntry([
        'kezdes' => '2026-06-03 08:00:00', 'vege' => '2026-06-03 16:00:00',
        'helyiseg' => 'Uzem', 'employee_id' => $employee->id,
    ]);

    expect(TimeEntry::where('employee_id', $employee->id)->count())->toBe(1);

    $exitCode = Artisan::call('work-logs:sync-presence');

    expect($exitCode)->toBe(0);
    expect(TimeEntry::where('employee_id', $employee->id)->count())->toBe(2);
    expect(TimeEntry::where('employee_id', $employee->id)->where('start_date', '2026-06-02')->exists())->toBeTrue();

    // Idempotens: újra lefuttatva nem duplikál.
    Artisan::call('work-logs:sync-presence');
    expect(TimeEntry::where('employee_id', $employee->id)->count())->toBe(2);
});

it('ignores WorkLog rows without an employee_id', function () {
    WorkLog::create([
        'nev' => 'Nincs Párosítva', 'munkakor' => 'Op', 'helyiseg' => 'Uzem',
        'kezdes' => '2026-06-04 08:00:00', 'vege' => '2026-06-04 16:00:00',
        'employee_id' => null,
    ]);

    $exitCode = Artisan::call('work-logs:sync-presence');

    expect($exitCode)->toBe(0);
    expect(TimeEntry::count())->toBe(0);
});
