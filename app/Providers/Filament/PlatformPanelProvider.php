<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Platform superadmin panel — central context only.
 *
 * Accessible at /platform on platform-ccrs.digittal.mobi (or localhost in dev/test).
 * Authenticates via email/password using the PlatformAdmin model + 'superadmin' guard.
 * Does NOT initialise tenant context — all queries run against the central database.
 * Does NOT include FilamentShield — PlatformAdmin has no Spatie roles.
 */
class PlatformPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('platform')
            ->path('platform')
            ->login()
            ->brandName('DPP Platform')
            ->authGuard('superadmin')
            ->colors([
                'primary' => Color::Violet,
                'danger' => Color::Red,
                'gray' => Color::Zinc,
                'info' => Color::Sky,
                'success' => Color::Green,
                'warning' => Color::Amber,
            ])
            ->darkMode(true)
            ->discoverResources(
                in: app_path('Filament/Platform/Resources'),
                for: 'App\\Filament\\Platform\\Resources',
            )
            ->discoverPages(
                in: app_path('Filament/Platform/Pages'),
                for: 'App\\Filament\\Platform\\Pages',
            )
            ->pages([])
            ->discoverWidgets(
                in: app_path('Filament/Platform/Widgets'),
                for: 'App\\Filament\\Platform\\Widgets',
            )
            ->widgets([])
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
                Authenticate::class,
            ]);
    }
}
