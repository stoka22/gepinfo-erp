<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class SchedulerBoard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $title = 'Gyártástervező';
    protected static string $view = 'filament.pages.scheduler-board';
    protected static ?string $navigationGroup = 'Termelés';
}
