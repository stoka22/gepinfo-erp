<?php

use App\Models\Command;
use App\Models\Firmware;
use App\Models\Pulse;

// createEnrolledDevice()/pushPayload() -- lásd tests/Pest.php (megosztott
// helperek, mert a FirmwareResourceTest is használja őket).

it('requires a valid api key', function () {
    [$device] = createEnrolledDevice();

    $response = $this->withHeaders(['X-API-KEY' => 'wrong-key'])
        ->postJson('/api/device/push', pushPayload());

    $response->assertStatus(401);
});

it('rejects an unknown device_id', function () {
    $response = $this->withHeaders(['X-API-KEY' => 'anything'])
        ->postJson('/api/device/push', pushPayload(['device_id' => 'ESP32_FFFFFFFFFFFF']));

    $response->assertStatus(401);
});

it('stores the first sample with zero delta and updates device telemetry', function () {
    [$device, $apiKey] = createEnrolledDevice();

    $response = $this->withHeaders(['X-API-KEY' => $apiKey])->postJson('/api/device/push', pushPayload());

    $response->assertOk()->assertJson(['ok' => true, 'reboot' => false, 'restart' => false]);

    $device->refresh();
    expect($device->fw_version)->toBe('1.0.0')
        ->and($device->meta['live']['ssid'])->toBe('Factory')
        ->and($device->last_seen_at)->not->toBeNull();

    $pulse = Pulse::where('device_id', $device->id)->first();
    expect($pulse->d1_total)->toBe(10)->and($pulse->d1_delta)->toBe(10);
});

it('computes the delta against the previously stored total', function () {
    [$device, $apiKey] = createEnrolledDevice();
    $client = $this->withHeaders(['X-API-KEY' => $apiKey]);

    $client->postJson('/api/device/push', pushPayload([
        'timestamp' => now()->subMinutes(2)->toIso8601String(),
        'pulses_total' => ['d1' => 10, 'd2' => 0, 'd3' => 0, 'd4' => 0],
    ]))->assertOk();

    $client->postJson('/api/device/push', pushPayload([
        'timestamp' => now()->toIso8601String(),
        'pulses_total' => ['d1' => 17, 'd2' => 0, 'd3' => 0, 'd4' => 0],
    ]))->assertOk();

    $latest = Pulse::where('device_id', $device->id)->orderByDesc('sample_time')->first();
    expect($latest->d1_total)->toBe(17)->and($latest->d1_delta)->toBe(7);
});

it('never produces a negative delta when the counter resets after a reboot', function () {
    [$device, $apiKey] = createEnrolledDevice();
    $client = $this->withHeaders(['X-API-KEY' => $apiKey]);

    $client->postJson('/api/device/push', pushPayload([
        'timestamp' => now()->subMinutes(2)->toIso8601String(),
        'pulses_total' => ['d1' => 500, 'd2' => 0, 'd3' => 0, 'd4' => 0],
    ]))->assertOk();

    // Reboot: the firmware's in-RAM running total resets to a small number.
    $client->postJson('/api/device/push', pushPayload([
        'timestamp' => now()->toIso8601String(),
        'reset_reason' => 'poweron',
        'pulses_total' => ['d1' => 3, 'd2' => 0, 'd3' => 0, 'd4' => 0],
    ]))->assertOk();

    $latest = Pulse::where('device_id', $device->id)->orderByDesc('sample_time')->first();
    expect($latest->d1_total)->toBe(3)->and($latest->d1_delta)->toBe(0);
});

it('sorts wifi_scan results by rssi and stores them in device meta', function () {
    [$device, $apiKey] = createEnrolledDevice();

    $this->withHeaders(['X-API-KEY' => $apiKey])->postJson('/api/device/push', pushPayload([
        'wifi_scan' => [
            ['ssid' => 'Weak', 'rssi' => -80],
            ['ssid' => 'Strong', 'rssi' => -40],
        ],
    ]))->assertOk();

    $scan = $device->refresh()->meta['live']['wifi_scan'];
    expect($scan[0]['ssid'])->toBe('Strong')->and($scan[1]['ssid'])->toBe('Weak');
});

it('does not reject unknown extra top-level fields', function () {
    [, $apiKey] = createEnrolledDevice();

    $response = $this->withHeaders(['X-API-KEY' => $apiKey])
        ->postJson('/api/device/push', pushPayload(['some_future_field' => 'whatever']));

    $response->assertOk();
});

it('delivers a pending command once via the commands array and marks it delivered', function () {
    [$device, $apiKey] = createEnrolledDevice();
    Command::create(['device_id' => $device->id, 'cmd' => 'reboot', 'status' => 'pending']);

    $client = $this->withHeaders(['X-API-KEY' => $apiKey]);

    $first = $client->postJson('/api/device/push', pushPayload());
    $first->assertOk()->assertJson(['commands' => [['cmd' => 'reboot']]]);
    expect(Command::first()->status)->toBe('delivered');

    $second = $client->postJson('/api/device/push', pushPayload());
    $second->assertOk()->assertJson(['commands' => []]);
});

it('stores backlog entries at their own timestamp, distinct from the live sample', function () {
    [$device, $apiKey] = createEnrolledDevice();

    $this->withHeaders(['X-API-KEY' => $apiKey])->postJson('/api/device/push', pushPayload([
        'timestamp' => now()->toIso8601String(),
        'pulses_total' => ['d1' => 20, 'd2' => 0, 'd3' => 0, 'd4' => 0],
        'backlog' => [
            ['timestamp' => now()->subMinutes(5)->toIso8601String(), 'pulses_total' => ['d1' => 5, 'd2' => 0, 'd3' => 0, 'd4' => 0]],
            ['timestamp' => now()->subMinutes(3)->toIso8601String(), 'pulses_total' => ['d1' => 12, 'd2' => 0, 'd3' => 0, 'd4' => 0]],
        ],
    ]))->assertOk();

    expect(Pulse::count())->toBe(3);
    $rows = Pulse::orderBy('sample_time')->get();
    expect($rows[0]->d1_total)->toBe(5)
        ->and($rows[1]->d1_total)->toBe(12)->and($rows[1]->d1_delta)->toBe(7)
        ->and($rows[2]->d1_total)->toBe(20)->and($rows[2]->d1_delta)->toBe(8);
});

it('does not let a malformed backlog prevent the live sample from being stored', function () {
    [, $apiKey] = createEnrolledDevice();

    $response = $this->withHeaders(['X-API-KEY' => $apiKey])->postJson('/api/device/push', pushPayload([
        'backlog' => 'not-an-array',
    ]));

    $response->assertOk();
    expect(Pulse::count())->toBe(1);
});

it('caps an oversized backlog at 5 entries', function () {
    [, $apiKey] = createEnrolledDevice();

    $backlog = collect(range(1, 9))->map(fn ($i) => [
        'timestamp' => now()->subMinutes(20 - $i)->toIso8601String(),
        'pulses_total' => ['d1' => $i, 'd2' => 0, 'd3' => 0, 'd4' => 0],
    ])->all();

    $this->withHeaders(['X-API-KEY' => $apiKey])->postJson('/api/device/push', pushPayload([
        'backlog' => $backlog,
    ]))->assertOk();

    // 5 backlog entries + 1 live sample
    expect(Pulse::count())->toBe(6);
});

it('offers a firmware update only when the device has a matching-platform target that differs', function () {
    [$device, $apiKey] = createEnrolledDevice();
    Firmware::create([
        'version' => '1.1.0', 'platform' => 'esp32', 'file_path' => 'firmware/1.1.0.bin',
        'file_size' => 10, 'mime_type' => 'application/octet-stream', 'sha256' => 'x', 'md5' => 'y',
    ]);
    $device->update(['meta' => array_merge($device->meta ?? [], ['firmware_target_version' => '1.1.0'])]);

    $response = $this->withHeaders(['X-API-KEY' => $apiKey])->postJson('/api/device/push', pushPayload(['firmware_version' => '1.0.0']));

    $response->assertOk()->assertJsonPath('firmware.version', '1.1.0');
});

it('never offers a firmware target of a different platform', function () {
    [$device, $apiKey] = createEnrolledDevice(['platform' => 'esp32']);
    Firmware::create([
        'version' => '1.1.0', 'platform' => 'esp8266', 'file_path' => 'firmware/1.1.0.bin',
        'file_size' => 10, 'mime_type' => 'application/octet-stream', 'sha256' => 'x', 'md5' => 'y',
    ]);
    $device->update(['meta' => array_merge($device->meta ?? [], ['firmware_target_version' => '1.1.0'])]);

    $response = $this->withHeaders(['X-API-KEY' => $apiKey])->postJson('/api/device/push', pushPayload(['firmware_version' => '1.0.0']));

    $response->assertOk()->assertJsonMissingPath('firmware');
});

it('omits firmware when the device already reports the target version', function () {
    [$device, $apiKey] = createEnrolledDevice();
    Firmware::create([
        'version' => '1.0.0', 'platform' => 'esp32', 'file_path' => 'firmware/1.0.0.bin',
        'file_size' => 10, 'mime_type' => 'application/octet-stream', 'sha256' => 'x', 'md5' => 'y',
    ]);
    $device->update(['meta' => array_merge($device->meta ?? [], ['firmware_target_version' => '1.0.0'])]);

    $response = $this->withHeaders(['X-API-KEY' => $apiKey])->postJson('/api/device/push', pushPayload(['firmware_version' => '1.0.0']));

    $response->assertOk()->assertJsonMissingPath('firmware');
});
