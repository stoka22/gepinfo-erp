<?php

use App\Filament\Resources\WorkLogResource\Pages\ListWorkLogs;
use App\Models\Company;
use App\Models\Employee;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkLog;
use Livewire\Livewire;

it('creates the missing presence TimeEntry when linking a previously-unmatched WorkLog row to an employee', function () {
    $company = Company::create(['name' => 'Link Kft.']);
    $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
    $employee = Employee::create(['name' => 'Utólag Párosított', 'company_id' => $company->id]);

    // Ez a helyzet áll elő, amikor egy import futása során a név nem talált automatikus
    // egyezést: a WorkLog sor létrejön, de employee_id nélkül, ezért a bridging (TimeEntry
    // létrehozása) akkor kimaradt.
    $log = WorkLog::create([
        'nev' => 'Ismeretlen Import Sor',
        'munkakor' => 'Op',
        'helyiseg' => 'Uzem 1',
        'kezdes' => '2026-02-10 08:00:00',
        'vege' => '2026-02-10 16:00:00',
        'ido' => '8:00',
        'employee_id' => null,
    ]);

    expect(TimeEntry::where('employee_id', $employee->id)->count())->toBe(0);

    $this->actingAs($admin);

    Livewire::test(ListWorkLogs::class)
        ->callTableBulkAction('link_selected', [$log], data: ['employee_id' => $employee->id]);

    expect($log->fresh()->employee_id)->toBe($employee->id);

    $entry = TimeEntry::where('employee_id', $employee->id)->first();
    expect($entry)->not->toBeNull();
    expect($entry->type->value)->toBe('presence');
    expect($entry->start_date->toDateString())->toBe('2026-02-10');
    expect($entry->end_time->format('H:i:s'))->toBe('16:00:00');
});

it('is idempotent when linking the same row twice (does not duplicate the TimeEntry)', function () {
    $company = Company::create(['name' => 'Link Kft. 2']);
    $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
    $employee = Employee::create(['name' => 'Duplikáció Teszt', 'company_id' => $company->id]);

    $log = WorkLog::create([
        'nev' => 'Duplikátum Sor',
        'munkakor' => 'Op',
        'helyiseg' => 'Uzem 1',
        'kezdes' => '2026-02-11 08:00:00',
        'vege' => '2026-02-11 16:00:00',
        'employee_id' => null,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListWorkLogs::class)
        ->callTableBulkAction('link_selected', [$log], data: ['employee_id' => $employee->id]);
    Livewire::test(ListWorkLogs::class)
        ->callTableBulkAction('link_selected', [$log->fresh()], data: ['employee_id' => $employee->id]);

    expect(TimeEntry::where('employee_id', $employee->id)->count())->toBe(1);
});
