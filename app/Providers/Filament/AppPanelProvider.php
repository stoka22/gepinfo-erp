<?php

// app/Providers/Filament/AppPanelProvider.php
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Panel;
use Filament\PanelProvider;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->assets([
                // FullCalendar helyi assetek (amiket már kimásoltál public/vendor/fullcalendar alá)
                Css::make(asset('vendor/fullcalendar/main.min.css')),
                Js::make(asset('vendor/fullcalendar/index.global.min.js'))->defer(),

                // A SAJÁT Alpine komponensed
                Js::make(asset('js/time-entries-calendar.js'))->defer(),
            ]);
    }
}
