<?php

use App\Filament\Resources\DeviceResource\Pages\CreateDevice;
use App\Filament\Resources\DeviceResource\Pages\EditDevice;
use App\Filament\Resources\DeviceResource\Pages\ListDevices;
use App\Filament\Resources\FirmwareResource\Pages\CreateFirmware;
use App\Filament\Resources\FirmwareResource\Pages\ListFirmwares;
use App\Filament\Resources\PendingDeviceResource\Pages\ListPendingDevices;
use App\Filament\Widgets\DevicesStatusTable;
use App\Filament\Widgets\MachinesHealthTable;
use App\Models\Company;
use App\Models\Device;
use App\Models\Machine;
use App\Models\User;
use Livewire\Livewire;

// Regressziós teszt: a DeviceResource::form()-ban egy Section komponensen
// ->helperText()-et hívtunk (ami csak Field-eken létezik, Section-ön
// ->description() kell) -- ez élesben BadMethodCallException-nel 500-at
// adott a szerkesztő oldal megnyitásakor, mert korábban semmilyen teszt
// nem renderelte ezt (vagy a többi itt tesztelt) admin oldalt.

function actingAsAdmin(): User
{
    $company = Company::create(['name' => 'Admin Teszt Kft.']);
    \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create(['company_id' => $company->id, 'role' => 'admin']);
    $admin->assignRole('admin');
    test()->actingAs($admin);

    return $admin;
}

it('renders the device list page', function () {
    actingAsAdmin();
    Livewire::test(ListDevices::class)->assertOk();
});

it('renders the device create form', function () {
    actingAsAdmin();
    Livewire::test(CreateDevice::class)->assertOk();
});

it('renders the device edit form for an already-approved device', function () {
    $admin = actingAsAdmin();
    $device = Device::create(['user_id' => $admin->id, 'name' => 'Panel', 'mac_address' => 'AA:BB:CC:DD:EE:01']);

    Livewire::test(EditDevice::class, ['record' => $device->getRouteKey()])->assertOk();
});

it('renders the pending devices list page', function () {
    actingAsAdmin();
    Livewire::test(ListPendingDevices::class)->assertOk();
});

it('renders the firmware list and create pages', function () {
    actingAsAdmin();
    Livewire::test(ListFirmwares::class)->assertOk();
    Livewire::test(CreateFirmware::class)->assertOk();
});

it('renders the DevicesStatusTable widget without error', function () {
    $admin = actingAsAdmin();
    $device = Device::create(['user_id' => $admin->id, 'name' => 'Widget Panel', 'mac_address' => 'AA:BB:CC:DD:EE:02']);
    \App\Models\Pulse::create([
        'device_id' => $device->id, 'sample_time' => now(),
        'd1_delta' => 3, 'd1_total' => 3, 'd2_delta' => 0, 'd2_total' => 0,
        'd3_delta' => 0, 'd3_total' => 0, 'd4_delta' => 0, 'd4_total' => 0,
    ]);

    Livewire::test(DevicesStatusTable::class)->assertOk()->assertSeeText('3');
});

it('saves wifi networks from the edit form, encrypted, and keeps an unchanged password on blank input', function () {
    $admin = actingAsAdmin();
    $device = Device::create(['user_id' => $admin->id, 'name' => 'Wifi Panel', 'mac_address' => 'AA:BB:CC:DD:EE:04']);
    $device->update(['meta' => ['wifi_networks' => [
        ['ssid' => 'Old-Net', 'password' => \Illuminate\Support\Facades\Crypt::encryptString('old-pass')],
    ]]]);

    Livewire::test(\App\Filament\Resources\DeviceResource\Pages\EditDevice::class, ['record' => $device->getRouteKey()])
        ->fillForm([
            'wifi_networks_input' => [
                ['ssid' => 'Old-Net', 'password' => ''], // blank -> keep old-pass
                ['ssid' => 'New-Net', 'password' => 'new-pass'],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $networks = collect($device->refresh()->meta['wifi_networks']);
    expect($networks)->toHaveCount(2);
    expect(\Illuminate\Support\Facades\Crypt::decryptString($networks->firstWhere('ssid', 'Old-Net')['password']))->toBe('old-pass');
    expect(\Illuminate\Support\Facades\Crypt::decryptString($networks->firstWhere('ssid', 'New-Net')['password']))->toBe('new-pass');
});

it('renders the MachinesHealthTable widget through the device_channels join without error', function () {
    $admin = actingAsAdmin();
    $machine = Machine::create(['company_id' => $admin->company_id, 'name' => 'Widget Gép', 'code' => 'WG-1']);
    $device = Device::create(['user_id' => $admin->id, 'name' => 'Widget Panel 2', 'mac_address' => 'AA:BB:CC:DD:EE:03']);
    $device->channels()->where('channel', 1)->update(['machine_id' => $machine->id]);
    \App\Models\Pulse::create([
        'device_id' => $device->id, 'sample_time' => now(),
        'd1_delta' => 7, 'd1_total' => 7, 'd2_delta' => 0, 'd2_total' => 0,
        'd3_delta' => 0, 'd3_total' => 0, 'd4_delta' => 0, 'd4_total' => 0,
    ]);

    Livewire::test(MachinesHealthTable::class)->assertOk()->assertSeeText('Widget Gép')->assertSeeText('7');
});
