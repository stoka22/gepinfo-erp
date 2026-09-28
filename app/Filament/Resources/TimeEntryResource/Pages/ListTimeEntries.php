<?php

namespace App\Filament\Resources\TimeEntryResource\Pages;

use App\Filament\Resources\TimeEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTimeEntries extends ListRecords
{
    protected static string $resource = TimeEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('calendar')
                ->label('Naptár nézet')
                ->icon('heroicon-o-calendar')
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('calendar')),
            Actions\CreateAction::make()->label('Új bejegyzés'),
        ];
    }
}
