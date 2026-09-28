<?php

use App\Models\Company;
use App\Models\User;

function calendarPageAdmin(): User
{
    config(['app.env' => 'local']);

    $company = Company::create(['name' => 'Naptár Oldal Kft.']);
    \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
    $admin->assignRole('admin');

    return $admin;
}

it('keeps the calendar widget off the list page, but renders it on its own dedicated page', function () {
    $admin = calendarPageAdmin();

    // A widget Livewire-komponensének neve (a wire:snapshot-ban) a lazy-load helyőrzőben is
    // jelen van, akkor is, ha a naptár tényleges tartalma (x-data="timeEntriesCalendar(...)")
    // csak a lazy-load AJAX-hívás után renderelődik -- ezért ezt vizsgáljuk, nem a nyers
    // Alpine-jelölést.
    $calendarWidgetMarker = 'time-entries-month-calendar';

    $listResponse = $this->actingAs($admin)->get('/admin/time-entries');
    $listResponse->assertOk();
    $listResponse->assertDontSee($calendarWidgetMarker);
    $listResponse->assertSee('Naptár nézet');

    $calendarResponse = $this->actingAs($admin)->get('/admin/time-entries/calendar');
    $calendarResponse->assertOk();
    $calendarResponse->assertSee($calendarWidgetMarker);
    $calendarResponse->assertSee('Táblázat nézet');
});
