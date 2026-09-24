<?php

use App\Models\Company;
use App\Models\Device;
use App\Models\PendingDevice;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

function enrollAuthHeader(): array
{
    return ['Authorization' => 'Basic '.base64_encode('enroll-user:enroll-pass')];
}

beforeEach(function () {
    config([
        'services.gepinfo_device.enrollment_user' => 'enroll-user',
        'services.gepinfo_device.enrollment_password' => 'enroll-pass',
    ]);
});

function createApprovedDevice(string $mac = 'AA:BB:CC:DD:EE:FF'): Device
{
    $company = Company::create(['name' => 'Teszt Kft.']);
    $user = User::factory()->create(['company_id' => $company->id]);

    return Device::create([
        'user_id' => $user->id,
        'name' => 'Panel 1',
        'mac_address' => $mac,
    ]);
}

it('issues an api_key and ota_pass for an already-approved device', function () {
    $device = createApprovedDevice();

    $response = $this->postJson('/api/device/enroll', ['device_id' => 'ESP32_AABBCCDDEEFF'], enrollAuthHeader());

    $response->assertOk()->assertJsonStructure(['ok', 'device_id', 'api_key', 'ota_pass']);
    expect($response->json())->toHaveCount(4);

    $device->refresh();
    expect($device->api_key_hash)->not->toBeNull()
        ->and(Hash::check($response->json('api_key'), $device->api_key_hash))->toBeTrue()
        ->and($device->platform)->toBe('esp32');
});

it('queues an unapproved device instead of auto-creating it', function () {
    $response = $this->postJson('/api/device/enroll', ['device_id' => 'ESP32_112233445566'], enrollAuthHeader());

    $response->assertStatus(202);
    expect(PendingDevice::where('mac_address', '11:22:33:44:55:66')->exists())->toBeTrue()
        ->and(Device::count())->toBe(0);
});

it('rejects wrong basic auth credentials and creates nothing', function () {
    $response = $this->postJson('/api/device/enroll', ['device_id' => 'ESP32_AABBCCDDEEFF'], [
        'Authorization' => 'Basic '.base64_encode('wrong:wrong'),
    ]);

    $response->assertStatus(401);
    expect(Device::count())->toBe(0)->and(PendingDevice::count())->toBe(0);
});

it('rejects a malformed device_id', function () {
    createApprovedDevice();

    $response = $this->postJson('/api/device/enroll', ['device_id' => 'not-a-valid-id'], enrollAuthHeader());

    $response->assertStatus(422);
});

it('rejects re-enrollment of an already-enrolled device and keeps the original key', function () {
    $device = createApprovedDevice();

    $first = $this->postJson('/api/device/enroll', ['device_id' => 'ESP32_AABBCCDDEEFF'], enrollAuthHeader());
    $first->assertOk();
    $originalHash = $device->refresh()->api_key_hash;

    $second = $this->postJson('/api/device/enroll', ['device_id' => 'ESP32_AABBCCDDEEFF'], enrollAuthHeader());
    $second->assertStatus(409);

    expect($device->refresh()->api_key_hash)->toBe($originalHash);
});

it('supports esp8266 devices with their own prefix', function () {
    $device = createApprovedDevice();

    $response = $this->postJson('/api/device/enroll', ['device_id' => 'ESP8266_AABBCCDDEEFF'], enrollAuthHeader());

    $response->assertOk();
    expect($device->refresh()->platform)->toBe('esp8266');
});

it('never logs the issued api_key or ota_pass', function () {
    createApprovedDevice();
    Log::spy();

    $response = $this->postJson('/api/device/enroll', ['device_id' => 'ESP32_AABBCCDDEEFF'], enrollAuthHeader());
    $apiKey = $response->json('api_key');
    $otaPass = $response->json('ota_pass');

    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('error');
    Log::shouldNotHaveReceived('debug');
    expect($apiKey)->not->toBeEmpty()->and($otaPass)->not->toBeEmpty();
});
