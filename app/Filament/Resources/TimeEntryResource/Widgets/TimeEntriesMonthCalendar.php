<?php

namespace App\Filament\Resources\TimeEntryResource\Widgets;

use Filament\Widgets\Widget;

class TimeEntriesMonthCalendar extends Widget
{
    // A Blade nézet, amiben a FullCalendar és az Alpine kód van
    protected static string $view = 'filament.resources.time-entry.widgets.fullcalendar';

    // Teljes szélesség a táblázat felett
    protected int|string|array $columnSpan = 'full';
}
