<?php

namespace App\Filament\User\Pages;

use Filament\Pages\Page;

class UserDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Vezérlőpult';
    protected static ?string $title = 'Felhasználói vezérlőpult';
    protected static ?string $slug = 'dashboard';

    protected static string $view = 'filament.user.pages.dashboard';
}
