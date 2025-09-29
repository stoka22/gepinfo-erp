<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Panel;

class UserDashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    //protected static ?string $title = 'Kezdőlap';
    protected static ?string $slug = 'dashboard'; // hogy a route neve ...pages.dashboard legyen

    public static function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'user'; // csak a user panelen
    }
}
