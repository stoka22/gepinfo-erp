<?php

namespace App\Filament\Resources\FirmwareResource\Pages;

use App\Filament\Resources\FirmwareResource;
use App\Models\Device;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Collection;

/**
 * Egyedi, nem a Filament form/FileUpload-komponensét használó nézet --
 * lásd App\Http\Controllers\Admin\FirmwareUploadController doc-kommentjét:
 * a Filament FileUpload /livewire/upload-file végpontját élesben
 * konzisztensen blokkolja egy WAF-szabály bináris (.bin) tartalomra, még
 * mielőtt PHP-ig eljutna. Ez az oldal ezért egy sima, hagyományos
 * <form method="POST" enctype="multipart/form-data"> -ot renderel, ami a
 * FirmwareUploadController::store()-ra submitol NORMÁL (nem Livewire/XHR)
 * böngésző-POST-tal -- pontosan úgy, ahogy az Energy projekt
 * FirmwareReleaseController-je is megoldja, ami éles környezetben
 * bizonyítottan működik.
 */
class CreateFirmware extends CreateRecord
{
    protected static string $resource = FirmwareResource::class;

    protected static string $view = 'filament.resources.firmware-resource.pages.create-firmware';

    public function getDevices(): Collection
    {
        return Device::orderBy('name')->get(['id', 'name']);
    }
}
