<?php

namespace App\Filament\Resources\DeviceFileResource\Pages;

use App\Filament\Resources\DeviceFileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDeviceFiles extends ListRecords
{
    protected static string $resource = DeviceFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
