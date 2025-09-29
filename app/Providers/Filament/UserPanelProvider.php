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

class UserPanelProvider extends PanelProvider
{
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('user')
            ->path('app')
            ->brandName('Gepinfo')
            ->login()
            ->sidebarCollapsibleOnDesktop()

            // csak a Vezérlőpult marad a főoldalnak
            ->pages([
                Pages\Dashboard::class,
            ])
            ->homeUrl(fn () => route('filament.user.pages.dashboard'))

            // csoportok: alapból zárva
            ->navigationGroups([
                NavigationGroup::make('Készlet')
                    ->icon('heroicon-o-archive-box')
                    ->collapsible()
                    ->collapsed(),

                NavigationGroup::make('Dolgozók')
                    ->icon('heroicon-o-user-group')
                    ->collapsible()
                    ->collapsed(),

                NavigationGroup::make('Törzsadatok')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->collapsible()
                    ->collapsed(),
                
                NavigationGroup::make('Eszközök')          // ← Törzsadatok ALÁ kerül
                    ->icon('heroicon-o-wrench')
                    ->collapsible()
                    ->collapsed(),
            ])

            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')

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
            ])
            ->authMiddleware([
                FilamentAuthenticate::class,
            ])

            // Opcionális: egyszerre csak 1 nyitott csoport
            ->renderHook('panels::body.end', fn () => view('filament.only-one-open-group'));
    }
}
