<?php

use App\Models\Firmware;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// Sima (nem Livewire) multipart form POST -- lásd FirmwareUploadController
// doc-kommentjét: a Filament FileUpload /livewire/upload-file végpontját
// élesben egy WAF-szabály blokkolja bináris tartalomra, ezért a firmware
// feltöltés ezen a sima route-on/kontrolleren megy, amit ez a teszt fed le.

it('creates a firmware record and stores the file via a plain form POST', function () {
    Storage::fake('local');
    actingAsAdmin();

    $file = UploadedFile::fake()->create('esp32-1.2.3.bin', 1024, 'application/octet-stream');

    $response = $this->post(route('admin.firmware.upload'), [
        'platform' => 'esp32',
        'hardware_code' => 'ESP32-WROOM-32E',
        'version' => '1.2.3',
        'build' => 4,
        'notes' => 'Teszt build',
        'firmware' => $file,
    ]);

    $response->assertRedirect(\App\Filament\Resources\FirmwareResource::getUrl('index'));

    $firmware = Firmware::where('version', '1.2.3')->first();
    expect($firmware)->not->toBeNull()
        ->and($firmware->platform)->toBe('esp32')
        ->and($firmware->hardware_code)->toBe('ESP32-WROOM-32E')
        ->and($firmware->build)->toBe(4)
        ->and($firmware->file_path)->not->toBeNull();

    Storage::disk('local')->assertExists($firmware->file_path);
    expect($firmware->md5)->not->toBeNull();
});

it('rejects the upload without a valid admin session', function () {
    $file = UploadedFile::fake()->create('esp32-1.0.0.bin', 100, 'application/octet-stream');

    $response = $this->post(route('admin.firmware.upload'), [
        'platform' => 'esp32',
        'version' => '1.0.0',
        'firmware' => $file,
    ]);

    $response->assertRedirect(); // guest -> login redirect, not a 200/created
    expect(Firmware::where('version', '1.0.0')->exists())->toBeFalse();
});
