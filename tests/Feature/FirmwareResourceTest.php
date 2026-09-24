<?php

use App\Models\Firmware;
use Illuminate\Support\Facades\Storage;

it('computes and stores the correct md5 on save, on the private local disk', function () {
    Storage::fake('local');

    $binary = random_bytes(1024);
    $path = Storage::disk('local')->put('firmware/1.0.0.bin', $binary) ? 'firmware/1.0.0.bin' : null;

    $firmware = Firmware::create([
        'version' => '1.0.0',
        'platform' => 'esp32',
        'file_path' => $path,
    ]);

    $firmware->refresh();
    expect($firmware->md5)->toBe(md5($binary));
});

it('requires valid device auth to download and sets the x-MD5 header', function () {
    Storage::fake('local');
    $binary = random_bytes(512);
    Storage::disk('local')->put('firmware/1.0.0.bin', $binary);

    $firmware = Firmware::create([
        'version' => '1.0.0',
        'platform' => 'esp32',
        'file_path' => 'firmware/1.0.0.bin',
    ]);
    $firmware->refresh();

    [$device, $apiKey] = createEnrolledDevice();

    $unauthorized = $this->get('/api/device/firmware/1.0.0/download?device_id=ESP32_AABBCCDDEEFF');
    $unauthorized->assertStatus(401);

    $ok = $this->withHeaders(['X-API-KEY' => $apiKey])
        ->get('/api/device/firmware/1.0.0/download?device_id=ESP32_AABBCCDDEEFF');

    $ok->assertOk();
    expect($ok->headers->get('x-MD5'))->toBe($firmware->md5);
});

it('404s a download for a version that does not match the requesting device platform', function () {
    Storage::fake('local');
    Storage::disk('local')->put('firmware/1.0.0.bin', random_bytes(64));

    Firmware::create(['version' => '1.0.0', 'platform' => 'esp8266', 'file_path' => 'firmware/1.0.0.bin']);

    [, $apiKey] = createEnrolledDevice(['platform' => 'esp32']);

    $response = $this->withHeaders(['X-API-KEY' => $apiKey])
        ->get('/api/device/firmware/1.0.0/download?device_id=ESP32_AABBCCDDEEFF');

    $response->assertStatus(404);
});
