<?php

use App\Filament\Resources\TimeEntryResource\Pages\ListTimeEntries;
use App\Models\Company;
use App\Models\Employee;
use App\Models\TimeEntry;
use App\Models\User;
use Livewire\Livewire;

function exactSearchAdmin(): User
{
    $company = Company::create(['name' => 'Pontos Keresés Kft.']);

    return User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
}

it('hides a needs_review=false presence entry by default, but finds it via the exact employee+date search regardless of type', function () {
    $admin = exactSearchAdmin();
    $employee = Employee::create(['name' => 'Rossz Bejegyzés Teszt', 'company_id' => $admin->company_id]);

    // Hibás, de rendben lezárt (needs_review=false) jelenlét-bejegyzés -- ilyen egy
    // gyári/import hibából adódó, nem auto-kiléptetett rossz időpont. Éles regresszió:
    // Nagy Noémi Pálma 2026-07-07-i sora, ami a "Megjelenő típusok" szűrő alapértelmezett
    // (Jelenlét-kizáró) beállítása miatt megtalálhatatlan volt a listában.
    $badEntry = TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $admin->company_id,
        'type' => 'presence', 'status' => 'checked_out',
        'start_date' => '2026-07-07', 'start_time' => '14:48:00',
        'end_date' => '2026-07-07', 'end_time' => '14:48:00',
        'needs_review' => false,
    ]);

    Livewire::actingAs($admin)
        ->test(ListTimeEntries::class)
        ->assertCanNotSeeTableRecords([$badEntry]);

    Livewire::actingAs($admin)
        ->test(ListTimeEntries::class)
        ->filterTable('types_visible', [
            'exact_employee_id' => $employee->id,
            'exact_date' => '2026-07-07',
        ])
        ->assertCanSeeTableRecords([$badEntry]);
});

it('leaves the exact-search filter inactive (default behaviour) when only one of employee/date is filled', function () {
    $admin = exactSearchAdmin();
    $employee = Employee::create(['name' => 'Fél Keresés Teszt', 'company_id' => $admin->company_id]);

    $badEntry = TimeEntry::forceCreate([
        'employee_id' => $employee->id, 'company_id' => $admin->company_id,
        'type' => 'presence', 'status' => 'checked_out',
        'start_date' => '2026-07-07', 'start_time' => '14:48:00',
        'end_date' => '2026-07-07', 'end_time' => '14:48:00',
        'needs_review' => false,
    ]);

    Livewire::actingAs($admin)
        ->test(ListTimeEntries::class)
        ->filterTable('types_visible', ['exact_employee_id' => $employee->id])
        ->assertCanNotSeeTableRecords([$badEntry]);
});
