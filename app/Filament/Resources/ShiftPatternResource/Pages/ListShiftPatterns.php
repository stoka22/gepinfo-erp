<?php

namespace App\Filament\Resources\ShiftPatternResource\Pages;

use App\Filament\Resources\ShiftPatternResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions; // <-- EZ KELL

class ListShiftPatterns extends ListRecords
{
    protected static string $resource = ShiftPatternResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Új műszak minta'),
        ];
    }
}
