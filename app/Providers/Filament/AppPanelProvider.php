<?php

/**
 * =========================================================================
 * AppPanelProvider — Filament-Panel der Mandanten (Tenant-Anwendung)
 * =========================================================================
 *
 * Zweck:
 *   Das Arbeits-Panel für Händler, Juweliere und Auktionshäuser. Läuft
 *   ausschließlich auf Mandanten-Domains ({slug}.localhost lokal) und
 *   arbeitet komplett auf der jeweiligen Tenant-Datenbank.
 *
 * Verantwortlichkeiten:
 *   - Tenancy-Initialisierung: InitializeTenancyByDomain identifiziert
 *     den Mandanten anhand des Hosts und wechselt DB-Verbindung,
 *     Filesystem und Queue-Kontext (stancl-Bootstrappers).
 *   - PreventAccessFromCentralDomains blockiert dieses Panel auf den
 *     zentralen Domains (localhost/127.0.0.1) — dort existiert nur /admin.
 *   - Auth gegen die users-Tabelle der TENANT-Datenbank (die Default-
 *     Connection ist beim Login bereits gewechselt).
 *
 * WARUM die Tenancy-Middleware GANZ VORNE im Stack:
 *   Session, CSRF und Auth müssen bereits im Tenant-Kontext laufen
 *   (Sessions liegen in der Tenant-DB!). Der TenancyServiceProvider
 *   erzwingt die Priorität zusätzlich global.
 *
 * Mögliche Erweiterungen:
 *   - Tenant-eigenes Branding (Logo/Farben aus Tenant-Settings)
 *   - E-Mail-Verifizierung für neue Mitarbeiter
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Enums\ThemeMode;
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
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('app')
            ->path('app')
            ->brandName('ChronoVault')
            ->favicon(asset('favicon.ico'))
            ->defaultThemeMode(ThemeMode::Dark)
            ->colors([
                'primary' => Color::Blue,
                'gray' => Color::Zinc,
            ])
            ->spa()
            ->sidebarCollapsibleOnDesktop()
            ->login()
            ->passwordReset()
            ->profile()
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\Filament\App\Resources')
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\Filament\App\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/App/Widgets'), for: 'App\Filament\App\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                // Tenancy MUSS vor Session/CSRF initialisiert werden —
                // Sessions liegen in der Tenant-Datenbank.
                InitializeTenancyByDomain::class,
                PreventAccessFromCentralDomains::class,

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
