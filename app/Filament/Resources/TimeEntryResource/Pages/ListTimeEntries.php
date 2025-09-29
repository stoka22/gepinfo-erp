<?php

namespace App\Filament\Resources\TimeEntryResource\Pages;

use App\Filament\Resources\TimeEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\TimeEntryResource\Widgets\TimeEntriesCalendarWidget;

class ListTimeEntries extends ListRecords
{
    protected static string $resource = TimeEntryResource::class;

    // 👉 Itt jelenik meg a fejléc jobb oldalán a "Create" gomb
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Új time entry'),          // opcionális felirat
            // ->authorize(true),                 // ha tesztként mindenképp mutatnád
        ];
    }

    // 👉 A naptárt továbbra is a header widgetek között tesszük ki
    protected function getHeaderWidgets(): array
    {
        return [
            TimeEntriesCalendarWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
{
    // a naptár (header widget) teljes szélességen jelenjen meg a táblázat felett
    return 1;
}
}
