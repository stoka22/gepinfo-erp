<?php

namespace App\Filament\Resources\EmployeeResource\Widgets;

use App\Models\Employee;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmployeeOvertimeCard extends StatsOverviewWidget
{
    // nem statikus!
    protected ?string $heading = 'Túlóra (óra)';

    public ?Employee $record = null;
    protected int|string|array $columnSpan = 1;

    protected static bool $isLazy = true;

    

    // milyen type érték számít túlórának
    private const OVERTIME_TYPES = ['overtime', 'tulora'];

    protected function getColumns(): int
    {
        // 2 doboz: éves + havi (mobilon 1, md-től 2 oszlop)
        return 2 ;
    }

    protected function getStats(): array
    {
        $eid = $this->record?->id ?? 0;
        $y   = now()->year;

        $tightCentered = [
            // kisebb padding + egységes magasság + teljes középre igazítás
            'class' => 'p-2 h-24 flex flex-col items-center justify-center text-center gap-y-1
                        [&_.fi-stat-value]:leading-none [&_.fi-stat-value]:whitespace-nowrap',
        ];

        $yearly  = 0.0;
        $monthly = 0.0;

        if (Schema::hasTable('overtimes')) {
            $yearly = (float) DB::table('overtimes')
                ->where('employee_id', $eid)
                ->whereYear('date', $y)
                ->sum('hours');

            $monthly = (float) DB::table('overtimes')
                ->where('employee_id', $eid)
                ->whereBetween('date', [
                    now()->startOfMonth()->toDateString(),
                    now()->endOfMonth()->toDateString(),
                ])
                ->sum('hours');
        } elseif (Schema::hasTable('time_entries')) {
            // time_entries-ben a túlóra a type mező szerint van jelölve
            $yearly = (float) DB::table('time_entries')
                ->where('employee_id', $eid)
                ->whereYear('start_date', $y)
                ->whereIn('type', self::OVERTIME_TYPES)
                ->sum('hours');

            $monthly = (float) DB::table('time_entries')
                ->where('employee_id', $eid)
                ->whereBetween('start_date', [
                    now()->startOfMonth()->toDateString(),
                    now()->endOfMonth()->toDateString(),
                ])
                ->whereIn('type', self::OVERTIME_TYPES)
                ->sum('hours');
        }

        return [
            Stat::make("Összes éves ({$y})", number_format($yearly, 1))->extraAttributes($tightCentered),
            Stat::make('Aktuális havi', number_format($monthly, 1))->extraAttributes($tightCentered),
        ];
    }
}
