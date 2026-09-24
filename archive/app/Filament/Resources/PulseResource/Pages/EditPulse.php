<?php

namespace App\Filament\Resources\PulseResource\Pages;

use App\Filament\Resources\PulseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPulse extends EditRecord
{
    protected static string $resource = PulseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
