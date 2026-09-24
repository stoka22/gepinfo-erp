<?php

use App\Models\Company;
use App\Models\Machine;
use App\Models\User;

// Regressziós teszt: a machines.index/create/edit nézetek eddig a
// resources/views/livewire/machines/ alatt voltak, de a MachineController
// a resources/views/machines/ alól várta őket -- a "View not found" hibát
// semmilyen teszt nem fedte, csendben 500-zal elszállt volna minden
// /machines látogatásnál. A megosztott <x-app-layout> emellett üres
// <main>-t renderelt (layouts/app.blade.php @yield('content')-et használt
// {{ $slot }} helyett), ami minden ezt használó oldalt (profil, gépek)
// érintett -- lásd bootstrap/app.php (VoltServiceProvider regisztrálva) és
// layouts/app.blade.php.

it('renders the machines list page', function () {
    $company = Company::create(['name' => 'Teszt Kft.']);
    $user = User::factory()->create(['company_id' => $company->id]);
    Machine::create(['company_id' => $company->id, 'name' => 'CNC 1', 'code' => 'CNC-1']);

    $response = $this->actingAs($user)->get(route('machines.index'));

    $response->assertOk();
    $response->assertSeeText('CNC 1');
});

it('renders the machine create form', function () {
    $company = Company::create(['name' => 'Teszt Kft.']);
    $user = User::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)->get(route('machines.create'))->assertOk();
});

it('renders the machine edit form', function () {
    $company = Company::create(['name' => 'Teszt Kft.']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $machine = Machine::create(['company_id' => $company->id, 'name' => 'CNC 2', 'code' => 'CNC-2']);

    $response = $this->actingAs($user)->get(route('machines.edit', $machine));

    $response->assertOk();
    $response->assertSee('CNC-2'); // az input value attribútumában, nem szöveges tartalomként
});

it('creates a machine via the store endpoint', function () {
    $company = Company::create(['name' => 'Teszt Kft.']);
    $user = User::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)->post(route('machines.store'), [
        'code' => 'CNC-3',
        'name' => 'Új gép',
        'active' => 1,
    ])->assertRedirect(route('machines.index'));

    expect(Machine::where('code', 'CNC-3')->exists())->toBeTrue();
});
