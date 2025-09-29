<?php

namespace App\Filament\Resources\TimeEntryResource\Widgets;

use Filament\Widgets\Widget;

class TimeEntriesCalendarWidget extends Widget
{
    // Ez a blade nézet, amit korábban készítettünk
    protected static string $view = 'filament.resources.time-entry.widgets.fullcalendar';

    protected int|string|array $columnSpan = 'full';
}
