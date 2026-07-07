<?php

/**
 * =========================================================================
 * AdminPanelProvider — Konfiguration des zentralen Filament-Admin-Panels
 * =========================================================================
 *
 * Zweck:
 *   Definiert das Haupt-Panel von ChronoVault unter /admin. Hier werden
 *   Branding, Farbschema, Navigation, Middleware und die automatische
 *   Erkennung von Resources/Pages/Widgets konfiguriert.
 *
 * Verantwortlichkeiten:
 *   - Panel-Identität (ID, Pfad, Branding "ChronoVault")
 *   - Dark-Mode-first-Design (Premium-Luxus-SaaS, siehe docs/DECISIONS.md)
 *   - Auto-Discovery aller Filament-Klassen unter app/Filament/*
 *   - Auth-Middleware-Stack des Panels
 *
 * Abhängigkeiten:
 *   - filament/filament (Panel-Builder)
 *   - Registriert in bootstrap/providers.php
 *
 * Nutzung:
 *   Wird vom Framework automatisch geladen. Neue Resources/Pages/Widgets
 *   in app/Filament/ werden ohne weitere Registrierung erkannt.
 *
 * Mögliche Erweiterungen:
 *   - Weitere Panels (z. B. Kunden-Portal) als eigene PanelProvider
 *   - Eigenes Theme-CSS via ->viteTheme() für Premium-Feinschliff
 *   - Tenant-Awareness (Modul 1: stancl/tenancy-Integration)
 *
 * WARUM ein einzelnes Panel?
 *   ChronoVault startet Filament-first mit EINEM Admin-Panel. Rollen
 *   (Admin, Händler, Sammler, Auktionator) werden über Policies und
 *   spatie/laravel-permission getrennt — nicht über separate Panels.
 *   Das hält Navigation, Theming und Wartung an einer Stelle (KISS).
 *   Ein separates Kundenportal kann später als zweites Panel entstehen.
 * =========================================================================
 */

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

class AdminPanelProvider extends PanelProvider
{
    /**
     * Konfiguriert das Admin-Panel.
     *
     * Design-Entscheidungen:
     * - Amber als Primärfarbe: warmer Goldton, passend zur Luxusuhren-Domäne.
     * - Dark Mode als Standard (defaultThemeMode), Light Mode bleibt wählbar.
     * - SPA-Modus für flüssige Navigation ohne Full-Page-Reloads
     *   (Premium-Gefühl wie Linear/Notion).
     * - Login/Passwort-Reset/Profil kommen von Filament selbst —
     *   deshalb kein Laravel Breeze (ADR-004).
     */
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('ChronoVault')
            ->favicon(asset('favicon.ico'))
            ->defaultThemeMode(ThemeMode::Dark)
            ->colors([
                // Gold/Amber als Markenfarbe der Luxusuhren-Plattform
                'primary' => Color::Amber,
                'gray' => Color::Zinc,
            ])
            ->spa()
            ->sidebarCollapsibleOnDesktop()
            ->login()
            ->passwordReset()
            ->profile()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
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
