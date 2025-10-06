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

    protected static bool $isLazy = true;      // lusta betöltés
    protected static bool $deferLoading = true;

    // “Munkának” számító típusok (ha nincs ilyen, akkor minden nem-szabadság)
    private const WORK_TYPES     = ['work', 'munkavégzés', 'regular'];
    private const VACATION_TYPES = ['vacation', 'leave', 'szabadsag', 'sick'];

    private const BASELINE_HOURS = 168; // norma

    protected function getData(): array
    {
        $labels = ['Jan','Feb','Már','Ápr','Máj','Jún','Júl','Aug','Szep','Okt','Nov','Dec'];
        $data   = array_fill(0, 12, 0.0);

        if (Schema::hasTable('time_entries') && $this->record?->id) {
            $q = DB::table('time_entries')
                ->where('employee_id', $this->record->id)
                ->whereYear('start_date', now()->year);

            $hasAnyWorkType = DB::table('time_entries')
                ->where('employee_id', $this->record->id)
                ->whereYear('start_date', now()->year)
                ->whereIn('type', self::WORK_TYPES)
                ->exists();

            $hasAnyWorkType
                ? $q->whereIn('type', self::WORK_TYPES)
                : $q->whereNotIn('type', self::VACATION_TYPES);

            $rows = $q->selectRaw('MONTH(start_date) as m, SUM(hours) as h')
                ->groupBy('m')
                ->pluck('h', 'm');

            foreach ($rows as $m => $h) {
                $data[(int) $m - 1] = (float) $h;
            }
        }

        // Színküszöbök
        $colors = [];
        foreach ($data as $v) {
            if ($v < 120) {
                $colors[] = 'rgba(239,68,68,0.85)';   // red-500
            } elseif ($v < 160) {
                $colors[] = 'rgba(249,115,22,0.85)';  // orange-500
            } else {
                $colors[] = 'rgba(34,197,94,0.85)';   // green-500
            }
        }

        // 168 órás baseline (zöld szaggatott vonal)
        $baseline = array_fill(0, count($labels), self::BASELINE_HOURS);

        return [
            'datasets' => [
                [
                    'label' => 'Óra',
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderColor' => $colors,
                    'borderWidth' => 1,
                    'maxBarThickness' => 22,
                    'type' => 'bar',
                    'order' => 1,
                ],
                [
                    'type' => 'line',
                    'label' => 'Norma (168 óra)',
                    'data' => $baseline,
                    'borderColor' => 'rgb(34,197,94)',
                    'borderWidth' => 2,
                    'tension' => 0,
                    'pointRadius' => 0,
                    'borderDash' => [6, 6],
                    'fill' => false,
                    'order' => 99, // vonal a hasábok felett
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => true],
                'tooltip' => ['enabled' => true],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'suggestedMax' => 200, // kis fejteret hagyunk a 168 fölé
                ],
                'x' => [
                    'grid' => ['display' => false],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        // vegyes típus: alap bar, baseline dataset adja a line-t
        return 'bar';
    }
}
