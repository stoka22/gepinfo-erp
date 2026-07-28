<?php

namespace App\Filament\Pages;

use App\Services\Scheduling\CapacityAnalysisService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class CapacityAnalysis extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Termelés';
    protected static ?string $navigationLabel = 'Kapacitás / szűkkeresztmetszet';
    protected static ?string $title           = 'Kapacitás- és szűkkeresztmetszet-elemzés';
    protected static string  $view            = 'filament.pages.capacity-analysis';

    public array $machineUtilization = [];
    public array $atRiskItems = [];
    public array $productionQueue = [];
    public array $missingRecipeItems = [];

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) (Auth::user()?->hasRole('admin') || Auth::user()?->can('manage workflows'));
    }

    public function mount(): void
    {
        $service = app(CapacityAnalysisService::class);
        $this->machineUtilization = $service->machineUtilization()->all();
        $this->atRiskItems = $service->atRiskOrderItems()->all();
        $this->productionQueue = $service->productionQueue()->all();
        $this->missingRecipeItems = $service->itemsMissingRecipe()->all();
    }
}
