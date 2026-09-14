<?php

namespace App\Filament\Widgets;

use App\Models\PageVisit;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class VisitorStatsOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Honlap látogatottság';

    protected static ?int $sort = -10;

    public static function canView(): bool
    {
        return (bool) Auth::user()?->hasRole('admin');
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $today = PageVisit::whereDate('created_at', today())->count();
        $last7 = PageVisit::where('created_at', '>=', now()->subDays(7))->count();
        $total = PageVisit::count();

        $uniqueToday = PageVisit::whereDate('created_at', today())->distinct('ip_hash')->count('ip_hash');

        $pageLabels = [
            'home' => 'Főoldal',
            'szolgaltatasok.index' => 'Szolgáltatások',
            'szolgaltatasok.show' => 'Szolgáltatás aloldalak',
            'kapcsolat' => 'Kapcsolat',
        ];

        $perPage = PageVisit::where('created_at', '>=', now()->subDays(7))
            ->whereIn('route_name', array_keys($pageLabels))
            ->selectRaw('route_name, count(*) as total')
            ->groupBy('route_name')
            ->pluck('total', 'route_name');

        $stats = [
            Stat::make('Látogatás ma', number_format($today, 0, ',', ' '))
                ->description($uniqueToday . ' egyedi látogató (becslés)')
                ->color('success'),
            Stat::make('Elmúlt 7 nap', number_format($last7, 0, ',', ' ')),
            Stat::make('Összes látogatás', number_format($total, 0, ',', ' ')),
        ];

        foreach ($pageLabels as $route => $label) {
            $stats[] = Stat::make($label, number_format((int) ($perPage[$route] ?? 0), 0, ',', ' '))
                ->description('elmúlt 7 nap');
        }

        return $stats;
    }
}
