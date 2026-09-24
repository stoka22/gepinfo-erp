<?php

namespace App\Filament\Resources\FirmwareResource\Pages;

use App\Filament\Resources\FirmwareResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFirmware extends EditRecord
{
    protected static string $resource = FirmwareResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    // Nincs afterSave() meta-számítás -- a Firmware::booted()::saved()
    // model-hook ezt már elvégzi minden save()-nél, a helyes ('local')
    // diskről (ld. CreateFirmware.php megjegyzése ugyanerről).
}
