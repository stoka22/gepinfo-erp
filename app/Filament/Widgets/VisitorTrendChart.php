<?php

namespace App\Filament\Widgets;

use App\Models\PageVisit;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VisitorTrendChart extends ChartWidget
{
    protected static ?string $heading = 'Látogatások az elmúlt 14 napban';

    protected static ?int $sort = -9;

    public static function canView(): bool
    {
        return (bool) Auth::user()?->hasRole('admin');
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = collect(range(13, 0))->map(fn ($i) => today()->subDays($i));

        $counts = PageVisit::query()
            ->where('created_at', '>=', today()->subDays(13))
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('count(*) as total'))
            ->groupBy('day')
            ->pluck('total', 'day');

        return [
            'datasets' => [[
                'label' => 'Látogatások',
                'data' => $days->map(fn ($day) => (int) ($counts[$day->toDateString()] ?? 0))->all(),
                'borderColor' => '#2563eb',
                'backgroundColor' => 'rgba(37, 99, 235, 0.15)',
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => $days->map(fn ($day) => $day->format('m.d.'))->all(),
        ];
    }
}
