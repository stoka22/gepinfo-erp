<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

class BladeFilamentBridgeProvider extends ServiceProvider
{
    public function boot(): void
    {
        // A Filament Support komponensek Blade namespace-ének regisztrálása
        Blade::componentNamespace('Filament\\Support\\Components', 'filament');
    }
}
