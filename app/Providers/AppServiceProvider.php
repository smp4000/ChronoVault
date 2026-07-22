<?php

namespace App\Providers;

use App\Notifications\ResetPasswordNotification;
use Filament\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope ist ein Dev-Werkzeug (require-dev) — nur lokal laden,
        // sonst fehlt die Klasse beim composer install --no-dev.
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

        // Passwort-Reset-Mail im ChronoVault-Design: Filament löst seine
        // Notification über den Container auf (RequestPasswordReset-Page)
        // — hier wird die eigene deutsche, gestaltete Variante geliefert.
        $this->app->bind(
            ResetPassword::class,
            ResetPasswordNotification::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Hinter Cloudflare terminiert TLS vor dem Server — Laravel soll
        // in Produktion IMMER https-URLs erzeugen (Assets, signierte Links).
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Projektweite Passwort-Mindestregel (Audit 2026-07-22): Alle
        // Stellen, die Password::default()/defaults() nutzen (Benutzer-
        // verwaltung, Tenant-Provisioning, Verkäufer-Registrierung),
        // erben damit automatisch diese Policy. Vorher galt nur "min. 8".
        Password::defaults(fn (): Password => Password::min(12)
            ->mixedCase()
            ->numbers());
    }
}
