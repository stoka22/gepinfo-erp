<?php

namespace App\Filament\Resources\TimeEntryResource\Pages;

use App\Filament\Resources\TimeEntryResource;
use App\Filament\Resources\TimeEntryResource\Widgets\TimeEntriesMonthCalendar as CalendarWidget;
use Filament\Actions;
use Filament\Resources\Pages\Page;

class TimeEntriesCalendar extends Page
{
    protected static string $resource = TimeEntryResource::class;

    protected static string $view = 'filament.resources.time-entry.pages.calendar';

    protected static ?string $title = 'Jelenlét-naptár';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('table')
                ->label('Táblázat nézet')
                ->icon('heroicon-o-table-cells')
                ->url(fn () => static::getResource()::getUrl('index')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [CalendarWidget::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
