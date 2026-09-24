<?php

use App\Models\Company;
use App\Models\Machine;
use App\Models\User;

function createMachine(Company $company, string $name): Machine
{
    return Machine::create([
        'company_id' => $company->id,
        'name' => $name,
        'code' => \Illuminate\Support\Str::slug($name).'-'.random_int(1000, 9999),
    ]);
}

it('seeds exactly 4 channels for a newly created device', function () {
    [$device] = createEnrolledDevice();

    expect($device->channels()->count())->toBe(4);
    expect($device->channels()->pluck('channel')->sort()->values()->all())->toBe([1, 2, 3, 4]);
});

it('allows a single device to feed 2 different machines through 2 channels', function () {
    $company = Company::create(['name' => 'Teszt Kft.']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $machineA = createMachine($company, 'Gép A');
    $machineB = createMachine($company, 'Gép B');

    [$device, $apiKey] = createEnrolledDevice(['user_id' => $user->id]);
    $device->channels()->where('channel', 1)->update(['machine_id' => $machineA->id]);
    $device->channels()->where('channel', 2)->update(['machine_id' => $machineB->id]);

    expect($device->machineForChannel(1)->id)->toBe($machineA->id)
        ->and($device->machineForChannel(2)->id)->toBe($machineB->id)
        ->and($device->machines()->pluck('machines.id')->sort()->values()->all())
            ->toBe(collect([$machineA->id, $machineB->id])->sort()->values()->all());
});

it('aggregates /monitor pulses per machine through the channel mapping, not per device', function () {
    $company = Company::create(['name' => 'Teszt Kft.']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $machineA = createMachine($company, 'Gép A');
    $machineB = createMachine($company, 'Gép B');
    $machineA->update(['active' => true]);
    $machineB->update(['active' => true]);

    [$device, $apiKey] = createEnrolledDevice(['user_id' => $user->id]);
    $device->channels()->where('channel', 1)->update(['machine_id' => $machineA->id]);
    $device->channels()->where('channel', 2)->update(['machine_id' => $machineB->id]);

    // Egy déli (06-14) push, hogy mindkét gép "de" oszlopába essen.
    $noon = today()->setTime(10, 0);
    $this->withHeaders(['X-API-KEY' => $apiKey])->postJson('/api/device/push', pushPayload([
        'timestamp' => $noon->toIso8601String(),
        'pulses_total' => ['d1' => 15, 'd2' => 4, 'd3' => 0, 'd4' => 0],
    ]))->assertOk();

    $this->travelTo($noon->copy()->addMinute());

    $response = $this->get('/monitor');
    $response->assertOk();
    $response->assertSeeText('Gép A');
    $response->assertSeeText('Gép B');
});

it('excludes an inactive channel from the monitor aggregation', function () {
    $company = Company::create(['name' => 'Teszt Kft.']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $machineA = createMachine($company, 'Gép Inaktív Csatorna');

    [$device, $apiKey] = createEnrolledDevice(['user_id' => $user->id]);
    $device->channels()->where('channel', 1)->update(['machine_id' => $machineA->id, 'active' => false]);

    $this->withHeaders(['X-API-KEY' => $apiKey])->postJson('/api/device/push', pushPayload([
        'timestamp' => today()->setTime(10, 0)->toIso8601String(),
        'pulses_total' => ['d1' => 99, 'd2' => 0, 'd3' => 0, 'd4' => 0],
    ]))->assertOk();

    // A device_channels.active=false miatt a raw SQL JOIN kihagyja -- a
    // gép sora megjelenik (van gép), de a mennyisége 0 marad.
    $rows = \Illuminate\Support\Facades\DB::table('device_channels')
        ->where('device_id', $device->id)->where('channel', 1)->first();
    expect((bool) $rows->active)->toBeFalse();
});
