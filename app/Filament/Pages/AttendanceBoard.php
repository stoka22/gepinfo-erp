<?php
// app/Filament/Pages/AttendanceBoard.php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Pages\Concerns\HasSubNavigation;
use App\Filament\Widgets\ShiftPresenceTable;
use App\Filament\Widgets\AbsenceTodayTable;

class AttendanceBoard extends Page
{
    protected static ?string $navigationIcon   = 'heroicon-o-user';
    protected static ?string $navigationGroup  = 'Dolgozók';
    protected static ?string $navigationLabel  = 'Jelenléti tábla';
    protected static ?string $title            = 'Jelenléti tábla';

    protected static string $view = 'filament.pages.attendance-board'; // üres blade, a widgetek dolgoznak

    public function getHeaderWidgets(): array
    {
        return [
            ShiftPresenceTable::class,
            AbsenceTodayTable::class,
        ];
    }
}
