<?php

namespace App\Filament\Resources\TimeEntryResource\Widgets;

use App\Filament\Resources\TimeEntryResource;
use App\Models\Company;
use Filament\Widgets\Widget;

class TimeEntriesMonthCalendar extends Widget
{
    // A Blade nézet, amiben a FullCalendar és az Alpine kód van
    protected static string $view = 'filament.resources.time-entry.widgets.fullcalendar';

    // Teljes szélesség a táblázat felett
    protected int|string|array $columnSpan = 'full';

    public function getCompanyOptions(): array
    {
        logger()->info('calendar company options debug', [
            'accessible_ids' => \App\Filament\Resources\TimeEntryResource::accessibleCompanyIds(),
        ]);
        return Company::query()
            ->whereIn('id', TimeEntryResource::accessibleCompanyIds())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }
}
