<?php
// app/Filament/Resources/EmployeeResource/Widgets/EmployeeVacationStats.php

namespace App\Filament\Resources\EmployeeResource\Widgets;

use App\Models\VacationBalance;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

class EmployeeVacationStats extends BaseWidget
{
    public ?Model $record = null; // az Edit oldaltól kapjuk

    protected function getCards(): array
    {
        $year = now()->year;
        $vb = VacationBalance::where('employee_id', $this->record->id)->where('year', $year)->first();

        $entitled  = number_format($vb?->entitled_days ?? 0, 1);
        $used      = number_format($vb?->used_days ?? 0, 1);
        $remaining = number_format($vb?->remaining_days ?? 0, 1);

        return [
            Stat::make("Keret {$year}", $entitled)->description('Jogosult összesen'),
            Stat::make('Felhasznált', $used),
            Stat::make('Hátralévő', $remaining)
                ->color(($vb && $vb->remaining_days <= 3) ? 'danger' : 'success'),
        ];
    }
}
