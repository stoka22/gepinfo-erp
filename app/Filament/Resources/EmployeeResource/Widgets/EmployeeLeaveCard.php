<?php

namespace App\Filament\Resources\EmployeeResource\Widgets;

use App\Models\Employee;
use App\Models\VacationBalance;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;


class EmployeeLeaveCard extends StatsOverviewWidget
{
    // ⬇️ NEM statikus!
    protected ?string $heading = 'Keret / Felhasznált / Kivehető';

    public ?Employee $record = null;
    protected int|string|array $columnSpan = 1;

    protected static bool $isLazy = true;

   

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $year = now()->year;
        $eid  = $this->record?->id ?? 0;

        $tightCentered = [
            'class' => 'p-2 h-24 flex flex-col items-center justify-center text-center gap-y-1
                        [&_.fi-stat-value]:leading-none [&_.fi-stat-value]:whitespace-nowrap',
        ];

        $vb = VacationBalance::query()
            ->where('employee_id', $eid)
            ->where('year', $year)
            ->first();

        $entitled = (float) ($vb?->entitled_days ?? 0);
        $usedDays = (float) ($vb?->used_days ?? 0);
        $available = (float) ($vb?->remaining_days ?? 0);

        return [
            Stat::make("Keret ({$year})", number_format($entitled, 1))->extraAttributes($tightCentered),
            Stat::make('Felhasznált', number_format($usedDays, 1))->extraAttributes($tightCentered),
            Stat::make('Kivehető', number_format($available, 1))->extraAttributes($tightCentered),
        ];
    }
}
