<?php

use App\Filament\Resources\TimeEntryResource\Pages\CreateTimeEntry;
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

it('sets end_date automatically and clears needs_review when correcting a checkout on a presence entry with a missing end_date -- reproduces record #19815', function () {
    $admin = editTimeEntryAdmin();
    $employee = Employee::create(['name' => 'Hiányos Kilépés', 'company_id' => $admin->company_id]);

    // Ugyanaz az állapot, mint a valós #19815-ös rekordé: van kezdés, de a kilépés
    // (dátum ÉS idő) hiányzik, és a bejegyzés felülvizsgálatra vár (pl. auto-kiléptetés
    // vagy hiányos import miatt). Az "end_date" mező jelenlétnél rejtett az űrlapon, ezért
    // korábban SOSEM lehetett beállítani -- a javítás (end_time megadása) örökre
    // hatástalan maradt, a felülvizsgálandó jelzés sosem tűnt el.
    $entry = TimeEntry::forceCreate([
        'employee_id' => $employee->id,
        'company_id' => $admin->company_id,
        'type' => 'presence',
        'status' => 'checked_out',
        'start_date' => '2026-08-03',
        'start_time' => '06:00:00',
        'end_date' => null,
        'end_time' => null,
        'needs_review' => true,
    ]);

    Livewire\Livewire::actingAs($admin)
        ->test(EditTimeEntry::class, ['record' => $entry->getRouteKey()])
        ->fillForm([
            'end_time' => '14:30', // az end_date mező jelenlétnél nincs is az űrlapon, nem lehet kitölteni
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $entry->refresh();
    expect($entry->end_date->toDateString())->toBe('2026-08-03'); // automatikusan a kezdés napjára állítva
    expect($entry->end_time->format('H:i'))->toBe('14:30');
    expect($entry->needs_review)->toBeFalse(); // a javítás magával hozza a jóváhagyást
    expect($entry->is_modified)->toBeTrue();
    expect($entry->hours)->not->toBeNull(); // a munkaidő újraszámolódott
    expect($entry->overtime_delta_minutes)->not->toBeNull(); // a túlóra is elszámolódott
});

it('clears the imported raw_start_time when an admin corrects the check-in time, so the correction actually becomes visible', function () {
    $admin = editTimeEntryAdmin();
    $employee = Employee::create(['name' => 'Nyers Idő Javítás', 'company_id' => $admin->company_id]);

    // Import-eredetű bejegyzés: a raw_start_time a nyers (kerekítés nélküli) importált
    // időt tárolja, és a lista "Kezdet" oszlopa, a jelenléti ív és a túlóra-számítás is
    // MINDIG ezt részesíti előnyben a start_time-mal szemben (`raw_start_time ?? start_time`).
    // Ha az admin egy hibásan importált időt javít a start_time mezőn keresztül, de a
    // raw_start_time változatlan marad, a javítás sehol sem látszik/számít be -- a
    // felhasználó a lista alapján úgy látja, mintha a mentés sosem történt volna meg.
    $entry = TimeEntry::forceCreate([
        'employee_id' => $employee->id,
        'company_id' => $admin->company_id,
        'type' => 'presence',
        'status' => 'checked_out',
        'start_date' => '2026-08-03',
        'start_time' => '05:37:16',
        'raw_start_time' => '05:37:16',
        'end_date' => '2026-08-03',
        'end_time' => '14:24:45',
        'hours' => 8.00,
    ]);

    Livewire\Livewire::actingAs($admin)
        ->test(EditTimeEntry::class, ['record' => $entry->getRouteKey()])
        ->fillForm([
            'start_time' => '07:00', // téves import-időpont javítása
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $entry->refresh();
    expect($entry->start_time->format('H:i'))->toBe('07:00');
    expect($entry->raw_start_time)->toBeNull();
});

it('keeps the imported raw_start_time untouched when the check-in time itself is not changed', function () {
    $admin = editTimeEntryAdmin();
    $employee = Employee::create(['name' => 'Érintetlen Nyers Idő', 'company_id' => $admin->company_id]);

    $entry = TimeEntry::forceCreate([
        'employee_id' => $employee->id,
        'company_id' => $admin->company_id,
        'type' => 'presence',
        'status' => 'checked_out',
        'start_date' => '2026-08-03',
        'start_time' => '05:37:16',
        'raw_start_time' => '05:37:16',
        'end_date' => '2026-08-03',
        'end_time' => '14:24:45',
        'hours' => 8.00,
    ]);

    Livewire\Livewire::actingAs($admin)
        ->test(EditTimeEntry::class, ['record' => $entry->getRouteKey()])
        ->fillForm([
            'note' => 'csak egy megjegyzés, a kezdés időt nem érintjük',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $entry->refresh();
    expect($entry->raw_start_time->format('H:i'))->toBe('05:37');
});

it('sets end_date automatically when creating a new presence entry with a checkout time', function () {
    $admin = editTimeEntryAdmin();
    $employee = Employee::create(['name' => 'Új Bejegyzés', 'company_id' => $admin->company_id]);

    Livewire\Livewire::actingAs($admin)
        ->test(CreateTimeEntry::class)
        ->fillForm([
            'employee_id' => $employee->id,
            'type' => 'presence',
            'start_date' => '2026-08-03',
            'start_time' => '06:00',
            'end_time' => '14:30', // az end_date mező jelenlétnél nincs is az űrlapon
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $entry = TimeEntry::where('employee_id', $employee->id)->first();
    expect($entry->end_date->toDateString())->toBe('2026-08-03');
});
