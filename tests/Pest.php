<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Egy már enroll-olt (api_key_hash-sel rendelkező) Device létrehozása
 * teszthez -- visszaadja a modellt és a NYERS (nem hashelt) API-kulcsot,
 * amit az X-API-KEY fejlécben kell elküldeni.
 *
 * @return array{0: \App\Models\Device, 1: string}
 */
function createEnrolledDevice(array $overrides = []): array
{
    $company = \App\Models\Company::create(['name' => 'Teszt Kft.']);
    $user = \App\Models\User::factory()->create(['company_id' => $company->id]);
    $apiKey = 'test-key-'.\Illuminate\Support\Str::random(24);

    $device = \App\Models\Device::create(array_merge([
        'user_id' => $user->id,
        'name' => 'Panel 1',
        'mac_address' => 'AA:BB:CC:DD:EE:FF',
        'api_key_hash' => \Illuminate\Support\Facades\Hash::make($apiKey),
        'platform' => 'esp32',
    ], $overrides));

    return [$device, $apiKey];
}

/**
 * Egy valós ESP32 firmware /api/device/push payloadja, az Energy-mintát
 * követő DevicePushApiTest defaultjaival, felülírható kulcsokkal.
 */
function pushPayload(array $overrides = []): array
{
    return array_merge([
        'device_id' => 'ESP32_AABBCCDDEEFF',
        'timestamp' => now()->toIso8601String(),
        'heartbeat' => true,
        'uptime_seconds' => 120,
        'ota_enabled' => false,
        'firmware_version' => '1.0.0',
        'platform' => 'esp32',
        'reset_reason' => 'poweron',
        'wifi' => ['ssid' => 'Factory', 'rssi' => -55, 'ip' => '10.0.0.5'],
        'pulses_total' => ['d1' => 10, 'd2' => 0, 'd3' => 0, 'd4' => 0],
    ], $overrides);
}
