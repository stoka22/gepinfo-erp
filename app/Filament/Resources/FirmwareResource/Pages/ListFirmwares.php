<?php

namespace App\Filament\Resources\FirmwareResource\Pages;

use App\Filament\Resources\FirmwareResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListFirmwares extends ListRecords
{
    protected static string $resource = FirmwareResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(), // <<< ettől jelenik meg a gomb
        ];
    }
}
