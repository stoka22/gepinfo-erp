<?php

namespace App\Filament\Resources\PulseResource\Pages;

use App\Filament\Resources\PulseResource;
use Filament\Resources\Pages\ListRecords;

class ListPulses extends ListRecords
{
    protected static string $resource = PulseResource::class;

    // Nincs "Új impulzus" gomb -- a pulses sorokat kizárólag az eszköz-API
    // (DevicePushController) hozza létre, admin kézzel nem visz fel adatot.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
