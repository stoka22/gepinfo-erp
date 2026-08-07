<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use Filament\Pages;
use Filament\Navigation\NavigationGroup;
use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Support\Assets\Js;
use Filament\Support\Assets\Css;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')                 // <- fontos: ez adja a route név előtagot
            ->path('admin')               // /admin útvonal
            ->brandName('Gepinfo Admin')
            ->login()
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')

            // A fő funkciók (Rendelések: itt tároljuk a gyártási igényeket; Termelés) legyenek
            // elöl és alapból nyitva; a ritkábban használt csoportok (Dolgozók, Eszközök,
            // Törzsadatok) összecsukva, hogy átláthatóbb legyen a kezdő nézet.
            ->navigationGroups([
                NavigationGroup::make('Hibalisták')
                    ->icon('heroicon-o-exclamation-triangle'),
                NavigationGroup::make('Rendelések')
                    ->icon('heroicon-o-receipt-percent'),
                NavigationGroup::make('Termelés')
                    ->icon('heroicon-o-cog-6-tooth'),
                NavigationGroup::make('Kimutatások')
                    ->icon('heroicon-o-chart-bar'),
                NavigationGroup::make('Készlet')
                    ->icon('heroicon-o-cube')
                    ->collapsed(),
                NavigationGroup::make('Dolgozók')
                    ->icon('heroicon-o-user-group')
                    ->collapsed(),
                NavigationGroup::make('Eszközök')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->collapsed(),
                NavigationGroup::make('Importálás')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->collapsed(),
                NavigationGroup::make('Törzsadatok')
                    ->icon('heroicon-o-archive-box')
                    ->collapsed(),
            ])

            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->pages([
                \Filament\Pages\Dashboard::class,
                \App\Filament\Pages\CapabilityMatrix::class,
            ])
            ->homeUrl(fn() => route('filament.admin.pages.dashboard'))
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                \App\Http\Middleware\EnsureUserIsAdmin::class,
            ])
            ->authMiddleware([
                FilamentAuthenticate::class,
            ])
            ->assets([
                Css::make('fc-css', asset('vendor/fullcalendar/main.min.css')),
                Js::make('fc-js',   asset('vendor/fullcalendar/index.global.min.js')),

                // Saját Alpine komponens
                Js::make('time-entries-calendar', asset('js/time-entries-calendar.js')),
            ]);
    }
}
