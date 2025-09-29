<?php

namespace App\Filament\Resources\TimeEntryResource\Widgets;

use Filament\Widgets\Widget;

class TimeEntriesMonthCalendar extends Widget
{
    protected static string $view = 'filament.resources.time-entry.widgets.fullcalendar';
    protected int|string|array $columnSpan = 'full';
}
