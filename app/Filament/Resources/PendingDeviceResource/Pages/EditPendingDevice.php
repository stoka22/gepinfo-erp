<?php

namespace App\Filament\Resources\PendingDeviceResource\Pages;

use App\Filament\Resources\PendingDeviceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPendingDevice extends EditRecord
{
    protected static string $resource = PendingDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
