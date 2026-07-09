<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
    }
}
