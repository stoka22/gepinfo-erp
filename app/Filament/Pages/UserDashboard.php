<?php

namespace App\Filament\Pages;

use App\Filament\Resources\EmployeeResource\Widgets\EmployeeLeaveCard;
use App\Filament\Resources\EmployeeResource\Widgets\EmployeeOvertimeCard;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Panel;
use Illuminate\Support\Facades\Auth;

class UserDashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $slug = 'dashboard'; // hogy a route neve ...pages.dashboard legyen
    protected static string $view = 'filament.pages.user-dashboard';

    public static function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'user'; // csak a user panelen
    }

    public function getEmployee(): ?\App\Models\Employee
    {
        return Auth::user()?->employee;
    }

    /** A fejléc-widgetek (Keret/Felhasznált/Kivehető, Túlóra) ebből kapják a saját dolgozói rekordot. */
    public function getWidgetData(): array
    {
        return [
            'record' => $this->getEmployee(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        if (! $this->getEmployee()) {
            return [];
        }

        return [
            EmployeeLeaveCard::class,
            EmployeeOvertimeCard::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | string | array
    {
        return [
            'default' => 1,
            'md' => 2,
        ];
    }
}
