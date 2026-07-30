<?php

use App\Models\Company;
use App\Models\Device;
use App\Models\User;

it('renders the device fleet health page for an admin with mixed online/offline devices', function () {
    config(['app.env' => 'local']);

    $company = Company::create(['name' => 'Teszt Kft.']);
    \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
    $admin->assignRole('admin');

    Device::create([
        'user_id' => $admin->id,
        'name' => 'Terminál 1',
        'mac_address' => 'AA:BB:CC:00:00:01',
        'location' => 'Gyártócsarnok',
        'last_seen_at' => now(),
        'fw_version' => '1.2.0',
        'rssi' => -40,
    ]);
    Device::create([
        'user_id' => $admin->id,
        'name' => 'Terminál 2',
        'mac_address' => 'AA:BB:CC:00:00:02',
        'location' => 'Raktár',
        'last_seen_at' => now()->subDays(10),
        'fw_version' => '1.1.0',
        'rssi' => -90,
    ]);

    $response = $this->actingAs($admin)->get('/admin/device-fleet-health');

    $response->assertOk();
    $response->assertSee('Offline eszközök');
    $response->assertSee('Firmware-verziók megoszlása');
    $response->assertSee('Terminál 2');
});

it('renders cleanly with zero devices', function () {
    config(['app.env' => 'local']);

    $company = Company::create(['name' => 'Teszt Kft.']);
    \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/admin/device-fleet-health')
        ->assertOk()
        ->assertSee('Minden eszköz online.');
});
