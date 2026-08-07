<?php

namespace App\Filament\Resources\EmployeeResource\Widgets;

use App\Enums\TimeEntryType;
use App\Models\Employee;
use App\Services\Calendar\WorkdayResolver;
use App\Services\Overtime\OvertimeBalanceService;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmployeeMonthlyHoursChart extends ChartWidget
{
    protected static ?string $heading = 'Jelenlét (utolsó 12 hónap)';
    protected static ?string $maxHeight = '260px';

    public ?Employee $record = null;
    protected int|string|array $columnSpan = 1;

    protected static bool $isLazy = true;      // lusta betöltés
    protected static bool $deferLoading = true;

    // Megjelenített kategóriák a time_entries.type oszlopból (halmozott oszlopok, ebben a sorrendben alulról).
    private const CATEGORIES = [
        'presence'             => ['label' => 'Ledolgozott',        'color' => '#3b82f6'], // blue-500
        'vacation'              => ['label' => 'Szabadság',          'color' => '#f59e0b'], // amber-500
        'sick_leave'            => ['label' => 'Táppénz',            'color' => '#a855f7'], // purple-500
        'unauthorized_absence'  => ['label' => 'Igazolatlan',        'color' => '#ef4444'], // red-500
    ];

    protected function getData(): array
    {
        // Gördülő 12 hónapos ablak (nem naptári év!) – így évváltáskor sem üresedik ki a
        // diagram, amíg az új év adatai fel nem gyűlnek (ld. a korábbi, whereYear(now()->year)
        // alapú verzió hibáját: januártól kezdve hónapokig üres volt minden dolgozónál, akinek
        // a friss adatai még az előző évben keletkeztek).
        $months = collect(range(11, 0))->map(fn (int $i) => now()->subMonthsNoOverflow($i)->startOfMonth());

        $labels = $months->map(fn ($m) => mb_convert_case($m->translatedFormat('M'), MB_CASE_TITLE)." '".$m->format('y'))->all();

        $series = [];
        foreach (self::CATEGORIES as $type => $meta) {
            $series[$type] = array_fill(0, 12, 0.0);
        }

        if (Schema::hasTable('time_entries') && $this->record?->id) {
            $since = $months->first()->toDateString();
            $until = $months->last()->copy()->endOfMonth()->toDateString();

            $rows = DB::table('time_entries')
                ->where('employee_id', $this->record->id)
                ->whereIn('type', array_keys(self::CATEGORIES))
                ->whereBetween('start_date', [$since, $until])
                ->selectRaw("DATE_FORMAT(start_date, '%Y-%m') as ym, type, SUM(hours) as h")
                ->groupBy('ym', 'type')
                ->get();

            $monthIndex = $months->mapWithKeys(fn ($m, $i) => [$m->format('Y-m') => $i]);

            foreach ($rows as $row) {
                if (! isset($monthIndex[$row->ym])) {
                    continue;
                }
                $series[$row->type][$monthIndex[$row->ym]] = (float) $row->h;
            }
        }

        // Pontos havi norma a dolgozó tényleges műszakrendje/naptára alapján (ünnepek,
        // áthelyezett munkanapok, egyéni műszakminta), nem egy fix "168 óra" – az korábban
        // minden hónapban (főleg ünnepekkel terhelt hónapokban) félrevezető volt.
        $baseline = $this->record
            ? $this->monthlyNorms($months)
            : array_fill(0, 12, 0.0);

        $datasets = [];
        foreach (self::CATEGORIES as $type => $meta) {
            $datasets[] = [
                'type' => 'bar',
                'label' => $meta['label'],
                'data' => $series[$type],
                'backgroundColor' => $meta['color'],
                'borderWidth' => 0,
                'stack' => 'attendance',
                'maxBarThickness' => 22,
                'order' => 1,
            ];
        }

        $datasets[] = [
            'type' => 'line',
            'label' => 'Norma',
            'data' => $baseline,
            'borderColor' => 'rgb(34,197,94)', // green-500
            'borderWidth' => 2,
            'tension' => 0,
            'pointRadius' => 0,
            'borderDash' => [6, 6],
            'fill' => false,
            'order' => 99, // vonal a hasábok felett
        ];

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    /** A dolgozó munkanapjai alapján számolt elvárt óraszám havonta (a saját napi normája szerint). */
    private function monthlyNorms($months): array
    {
        $resolver = app(WorkdayResolver::class);
        $dailyNormHours = app(OvertimeBalanceService::class)->standardMinutesFor($this->record) / 60;

        return $months->map(function ($month) use ($resolver, $dailyNormHours) {
            $cursor = CarbonImmutable::parse($month->toDateString());
            $end = $cursor->endOfMonth();
            $workdays = 0;
            while ($cursor->lte($end)) {
                if ($resolver->isWorkingDayForEmployee($this->record, $cursor)) {
                    $workdays++;
                }
                $cursor = $cursor->addDay();
            }

            return round($workdays * $dailyNormHours, 1);
        })->all();
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => true, 'labels' => ['boxWidth' => 12]],
                'tooltip' => ['enabled' => true, 'mode' => 'index', 'intersect' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'stacked' => true,
                ],
                'x' => [
                    'stacked' => true,
                    'grid' => ['display' => false],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        // vegyes típus: halmozott bar-ok + a normát mutató line felül
        return 'bar';
    }
}
