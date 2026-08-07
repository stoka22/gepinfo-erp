<?php

use App\Filament\Resources\WorkLogResource\Pages\ListWorkLogs;
use App\Imports\WorkLogsImport;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkLog;
use Livewire\Livewire;

function workLogSyncAdmin(): User
{
    $company = Company::create(['name' => 'Sync Kft.']);

    return User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
}

it('reports synced rows as synced and unsynced rows as not synced', function () {
    $admin = workLogSyncAdmin();
    $employee = Employee::create(['name' => 'Szinkron Teszt', 'company_id' => $admin->company_id]);

    $syncedLog = WorkLog::create([
        'nev' => 'Szinkron Teszt', 'munkakor' => 'Op', 'helyiseg' => 'Uzem',
        'kezdes' => '2026-03-01 08:00:00', 'vege' => '2026-03-01 16:00:00',
        'employee_id' => $employee->id,
    ]);
    (new WorkLogsImport)->createPresenceEntry([
        'kezdes' => '2026-03-01 08:00:00', 'vege' => '2026-03-01 16:00:00',
        'helyiseg' => 'Uzem', 'employee_id' => $employee->id,
    ]);

    $unsyncedLog = WorkLog::create([
        'nev' => 'Szinkron Teszt', 'munkakor' => 'Op', 'helyiseg' => 'Uzem',
        'kezdes' => '2026-03-02 08:00:00', 'vege' => '2026-03-02 16:00:00',
        'employee_id' => $employee->id,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListWorkLogs::class)
        ->assertTableColumnStateSet('synced', true, $syncedLog)
        ->assertTableColumnStateSet('synced', false, $unsyncedLog);
});

it('filters the list to only unsynced rows', function () {
    $admin = workLogSyncAdmin();
    $employee = Employee::create(['name' => 'Szűrő Teszt', 'company_id' => $admin->company_id]);

    $syncedLog = WorkLog::create([
        'nev' => 'Szűrő Teszt', 'munkakor' => 'Op', 'helyiseg' => 'Uzem',
        'kezdes' => '2026-03-05 08:00:00', 'vege' => '2026-03-05 16:00:00',
        'employee_id' => $employee->id,
    ]);
    (new WorkLogsImport)->createPresenceEntry([
        'kezdes' => '2026-03-05 08:00:00', 'vege' => '2026-03-05 16:00:00',
        'helyiseg' => 'Uzem', 'employee_id' => $employee->id,
    ]);

    $unsyncedLog = WorkLog::create([
        'nev' => 'Szűrő Teszt', 'munkakor' => 'Op', 'helyiseg' => 'Uzem',
        'kezdes' => '2026-03-06 08:00:00', 'vege' => '2026-03-06 16:00:00',
        'employee_id' => $employee->id,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListWorkLogs::class)
        ->filterTable('synced', false)
        ->assertCanSeeTableRecords([$unsyncedLog])
        ->assertCanNotSeeTableRecords([$syncedLog]);
});

it('filters the list by the Kezdés date range', function () {
    $admin = workLogSyncAdmin();
    $employee = Employee::create(['name' => 'Dátum Teszt', 'company_id' => $admin->company_id]);

    $inRange = WorkLog::create([
        'nev' => 'Dátum Teszt', 'munkakor' => 'Op', 'helyiseg' => 'Uzem',
        'kezdes' => '2026-04-15 08:00:00', 'vege' => '2026-04-15 16:00:00',
        'employee_id' => $employee->id,
    ]);
    $outOfRange = WorkLog::create([
        'nev' => 'Dátum Teszt', 'munkakor' => 'Op', 'helyiseg' => 'Uzem',
        'kezdes' => '2026-05-15 08:00:00', 'vege' => '2026-05-15 16:00:00',
        'employee_id' => $employee->id,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListWorkLogs::class)
        ->filterTable('kezdes_range', ['from' => '2026-04-01', 'until' => '2026-04-30'])
        ->assertCanSeeTableRecords([$inRange])
        ->assertCanNotSeeTableRecords([$outOfRange]);
});
