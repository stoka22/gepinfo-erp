<?php

use App\Filament\Resources\TimeEntryResource\Pages\EditTimeEntry;
use App\Models\Company;
use App\Models\Employee;
use App\Models\TimeEntry;
use App\Models\User;

function editTimeEntryAdmin(): User
{
    $company = Company::create(['name' => 'Edit Kft.']);
    \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
    $admin->assignRole('admin');

    return $admin;
}

it('lets the admin type an exact (non-5-minute) check-in time on the edit form', function () {
    $admin = editTimeEntryAdmin();
    $employee = Employee::create(['name' => 'Percre Pontos', 'company_id' => $admin->company_id]);

    $entry = TimeEntry::create([
        'employee_id' => $employee->id,
        'company_id' => $admin->company_id,
        'type' => 'presence',
        'status' => 'checked_out',
        'start_date' => '2026-01-05',
        'start_time' => '08:00:00',
        'end_date' => '2026-01-05',
        'end_time' => '16:00:00',
    ]);

    Livewire\Livewire::actingAs($admin)
        ->test(EditTimeEntry::class, ['record' => $entry->getRouteKey()])
        ->fillForm([
            'start_time' => '08:07', // nem 5 perces többszörös -- korábban a minutesStep(5) ezt levágta volna
            'end_time' => '16:13',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $entry->refresh();
    expect($entry->start_time->format('H:i'))->toBe('08:07');
    expect($entry->end_time->format('H:i'))->toBe('16:13');
});

it('recalculates hours and overtime when correcting an existing presence entry through the edit form -- even one with an invalid inherited status', function () {
    $admin = editTimeEntryAdmin();
    $employee = Employee::create(['name' => 'Javítandó Rekord', 'company_id' => $admin->company_id]);

    // Ugyanaz az állapot, mint a valós #128-as rekordé: teljes be-/kilépési adat, de a
    // "status" mező érvénytelen ('pending') egy jelenlét-bejegyzésnél -- emiatt a
    // TimeEntryObserver::settlePresence() eddig sosem futott le rá.
    $entry = TimeEntry::forceCreate([
        'employee_id' => $employee->id,
        'company_id' => $admin->company_id,
        'type' => 'presence',
        'status' => 'pending',
        'start_date' => '2026-01-05',
        'start_time' => '05:33:42',
        'end_date' => '2026-01-05',
        'end_time' => '14:30:10',
        'hours' => 8.00,
    ]);

    expect($entry->overtime_delta_minutes)->toBeNull();

    Livewire\Livewire::actingAs($admin)
        ->test(EditTimeEntry::class, ['record' => $entry->getRouteKey()])
        ->fillForm([
            'start_time' => '05:30',
            'end_time' => '14:35',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $entry->refresh();
    expect($entry->status->value)->toBe('checked_out');
    // Műszakkezdés 05:30-ra kerekítve (már egész félórás) -14:35 = 9:05 = 545 perc = 9.08 óra.
    expect((float) $entry->hours)->toBe(9.08);
    expect($entry->overtime_delta_minutes)->not->toBeNull();
    expect($entry->overtime_delta_minutes)->toBe(35); // 545-510=35 perc, a türelmi időn (10 perc) túl
});
