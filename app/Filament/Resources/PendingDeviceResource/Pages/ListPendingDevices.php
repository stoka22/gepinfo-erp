<?php

namespace App\Filament\Resources\PendingDeviceResource\Pages;

use App\Filament\Resources\PendingDeviceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPendingDevices extends ListRecords
{
    protected static string $resource = PendingDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
