<?php

namespace App\Filament\Resources\EmployeeResource\Widgets;

use App\Models\Employee;
use App\Models\VacationBalance;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmployeeLeaveCard extends StatsOverviewWidget
{
    // ⬇️ NEM statikus!
    protected ?string $heading = 'Keret / Felhasznált / Kivehető';

    public ?Employee $record = null;
    protected int|string|array $columnSpan = 1;

    protected static bool $isLazy = true;

    private const VACATION_TYPES = ['vacation','leave','szabadsag'];

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $year = now()->year;
        $eid  = $this->record?->id ?? 0;

        $entitled = 0.0;
        $usedDays = 0.0;

        $tightCentered = [
            // kisebb padding + egységes magasság + teljes középre igazítás
            'class' => 'p-2 h-24 flex flex-col items-center justify-center text-center gap-y-1
                        [&_.fi-stat-value]:leading-none [&_.fi-stat-value]:whitespace-nowrap',
        ];

        if (Schema::hasTable('vacation_balances')) {
            $vb = VacationBalance::query()
                ->where('employee_id', $eid)
                ->where('year', $year)
                ->first();

            if ($vb) {
                $entitled += (float) ($vb->base_days ?? 0);
                $entitled += (float) ($vb->age_extra_days ?? 0);
                $entitled += (float) ($vb->carried_over_days ?? 0);
                $entitled += (float) ($vb->manual_adjustment_days ?? 0);
            }
        }

        if (Schema::hasTable('vacation_allowances')) {
            $entitled += (float) DB::table('vacation_allowances')
                ->where('employee_id', $eid)
                ->where('year', $year)
                ->sum('days');
        }

        if (Schema::hasTable('vacation_usages')) {
            $usedDays = (float) DB::table('vacation_usages')
                ->where('employee_id', $eid)
                ->whereYear('date', $year)
                ->sum('days');
        } elseif (Schema::hasTable('time_entries')) {
            $hours = (float) DB::table('time_entries')
                ->where('employee_id', $eid)
                ->whereYear('start_date', $year)
                ->whereIn('type', self::VACATION_TYPES)
                ->sum('hours');
            $usedDays = $hours > 0 ? round($hours / 8, 2) : 0.0;
        }

        $available = max(0, round($entitled - $usedDays, 2));

        return [
            Stat::make("Keret ({$year})", number_format($entitled, 1)) ->extraAttributes($tightCentered),
            Stat::make('Felhasznált', number_format($usedDays, 1))->extraAttributes($tightCentered),
            Stat::make('Kivehető', number_format($available, 1))->extraAttributes($tightCentered),
        ];
    }
}
