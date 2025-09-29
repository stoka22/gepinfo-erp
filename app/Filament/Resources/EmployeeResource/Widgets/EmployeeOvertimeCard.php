<?php

namespace App\Filament\Resources\EmployeeResource\Widgets;

use App\Models\Employee;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmployeeOvertimeCard extends Widget
{
    protected static string $view = 'filament.employee.overtime-card';
    public ?Employee $record = null;
    protected int|string|array $columnSpan = 1;

    // milyen type érték számít túlórának
    private const OVERTIME_TYPES = ['overtime','tulora'];

    public function getViewData(): array
    {
        $eid = $this->record?->id ?? 0;
        $y   = now()->year;

        $yearly = 0.0;
        $monthly = 0.0;

        if (Schema::hasTable('overtimes')) {
            $yearly = (float) DB::table('overtimes')
                ->where('employee_id', $eid)->whereYear('date', $y)->sum('hours');

            $monthly = (float) DB::table('overtimes')
                ->where('employee_id', $eid)
                ->whereBetween('date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->sum('hours');
        } elseif (Schema::hasTable('time_entries')) {
            // time_entries-ben a tooóra a type mezőben van megjelölve
            $yearly = (float) DB::table('time_entries')
                ->where('employee_id', $eid)
                ->whereYear('start_date', $y)
                ->whereIn('type', self::OVERTIME_TYPES)
                ->sum('hours');

            $monthly = (float) DB::table('time_entries')
                ->where('employee_id', $eid)
                ->whereBetween('start_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->whereIn('type', self::OVERTIME_TYPES)
                ->sum('hours');
        }

        return [
            'year'    => $y,
            'yearly'  => number_format($yearly, 1),
            'monthly' => number_format($monthly, 1),
        ];
    }
}
