<?php

namespace App\Filament\Resources\TimeEntryResource\Pages;

use App\Filament\Resources\TimeEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
// EZ KELL:
use App\Filament\Resources\TimeEntryResource\Widgets\TimeEntriesMonthCalendar;

class ListTimeEntries extends ListRecords
{
    protected static string $resource = TimeEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Új bejegyzés'),
        ];
    }

    // A helyes widgetet tedd ki a fejlécbe
    protected function getHeaderWidgets(): array
    {
        return [TimeEntriesMonthCalendar::class];
        // (vagy FQCN nélkül import nélkül:)
        // return [\App\Filament\Resources\TimeEntryResource\Widgets\TimeEntriesMonthCalendar::class];
    }

    // Teljes szélesség a táblázat felett
    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
