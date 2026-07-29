<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\CapacityAnalysis;
use App\Services\Scheduling\CapacityAnalysisService;
use Filament\Widgets\Widget;

/**
 * Kompakt "kapacitás gyorsnézet" a vezérlőpulton: a legjobban kihasznált 1-3 gép,
 * link a teljes elemzésre — a részletes tábla (gépek/csúszásveszélyes rendelések/
 * gyártási sor) a CapacityAnalysis oldalon marad, itt nem duplikáljuk.
 */
class CapacityQuickView extends Widget
{
    protected static string $view = 'filament.widgets.capacity-quick-view';

    protected static ?int $sort = -8;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        // A kapacitás-elemzés recepthez kötött; ha egy tételhez nincs recept felvéve,
        // ne dőljön el emiatt a teljes vezérlőpult, csak maradjon üres ez a widget.
        try {
            $topMachines = app(CapacityAnalysisService::class)
                ->machineUtilization()
                ->take(3)
                ->values();
        } catch (\Throwable) {
            $topMachines = collect();
        }

        return [
            'topMachines' => $topMachines,
            'analysisUrl' => CapacityAnalysis::getUrl(),
        ];
    }
}
