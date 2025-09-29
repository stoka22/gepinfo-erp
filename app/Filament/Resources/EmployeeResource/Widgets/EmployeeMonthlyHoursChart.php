<?php

namespace App\Filament\Resources\EmployeeResource\Widgets;

use App\Models\Employee;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmployeeMonthlyHoursChart extends ChartWidget
{
    protected static ?string $heading = 'Ledolgozott órák (havi bontás)';
    protected static ?string $maxHeight = '260px';

    public ?Employee $record = null;
    protected int|string|array $columnSpan = 1;

    // mi számít tényleges ledolgozott időnek a type mezőben
    private const WORK_TYPES = ['work','munkavégzés','regular'];        // ha nincs ilyen, minden NEM szabadságot összesítünk
    private const VACATION_TYPES = ['vacation','leave','szabadsag','sick'];

    protected function getData(): array
    {
        $labels = ['Jan','Feb','Már','Ápr','Máj','Jún','Júl','Aug','Szep','Okt','Nov','Dec'];
        $data   = array_fill(0, 12, 0.0);

        if (Schema::hasTable('time_entries')) {
            $q = DB::table('time_entries')
                ->where('employee_id', $this->record?->id)
                ->whereYear('start_date', now()->year);

            // ha vannak explicit WORK_TYPES bejegyzések, azokra szűrjünk,
            // különben zárjuk ki a vacation/sick típusokat és vegyük a többieket
            $hasAnyWorkType = DB::table('time_entries')
                ->where('employee_id', $this->record?->id)
                ->whereYear('start_date', now()->year)
                ->whereIn('type', self::WORK_TYPES)
                ->exists();

            if ($hasAnyWorkType) {
                $q->whereIn('type', self::WORK_TYPES);
            } else {
                $q->whereNotIn('type', self::VACATION_TYPES);
            }

            $rows = $q->selectRaw('MONTH(start_date) as m, SUM(hours) as h')
                ->groupBy('m')
                ->pluck('h', 'm');

            foreach ($rows as $m => $h) {
                $data[(int)$m - 1] = (float) $h;
            }
        }

        return [
            'datasets' => [[
                'label' => 'Óra',
                'data'  => $data,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
