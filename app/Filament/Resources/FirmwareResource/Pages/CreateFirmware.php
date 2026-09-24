<?php

namespace App\Filament\Resources\FirmwareResource\Pages;

use App\Filament\Resources\FirmwareResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFirmware extends CreateRecord
{
    protected static string $resource = FirmwareResource::class;

    // Nincs afterCreate() meta-számítás -- a Firmware::booted()::saved()
    // model-hook ezt már elvégzi minden save()-nél (create ÉS edit), a
    // helyes ('local') diskről. Ez a page-szintű duplikátum a 'public'
    // diskre feltételezve (a globális \Storage:: facade default disk-jén)
    // nézte a fájlt -- a FileUpload disk('local')-ra váltása után ez a
    // duplikátum csendben semmit nem talált volna (disk-mismatch), ezért
    // törölve, nem javítva: egyetlen kanonikus hely elég erre.
}
