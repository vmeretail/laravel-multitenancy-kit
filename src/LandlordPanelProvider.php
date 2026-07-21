<?php

declare(strict_types=1);

namespace VmeRetail\MultitenancyKit;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

final class LandlordPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panelId = config('multitenancy-kit.landlord_panel_id', 'landlord');
        $panelPath = config('multitenancy-kit.landlord_panel_path', '/');
        $centralDomain = config('multitenancy-kit.central_domain', 'localhost');

        return $panel
            ->darkMode(false)
            ->id($panelId)
            ->path(mb_ltrim($panelPath, '/'))
            ->domain($centralDomain)
            ->login()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->authGuard('web')
            ->authPasswordBroker('users')
            ->discoverResources(
                in: __DIR__.'/Filament/Resources',
                for: 'VmeRetail\\MultitenancyKit\\Filament\\Resources',
            )
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                AccountWidget::class,
            ])
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
