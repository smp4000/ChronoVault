<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Produktion läuft hinter Cloudflare + lokalem nginx-Proxy —
        // ohne Proxy-Vertrauen würde Laravel http-URLs erzeugen und
        // signierte Links (Gewinner-Datenseite) wären ungültig.
        //
        // SICHERHEIT (Audit 2026-07-22): Nicht mehr pauschal '*' vertrauen.
        // Bei '*' könnte ein Client, der den Origin direkt erreicht,
        // X-Forwarded-For fälschen und damit IP-basierte Rate-Limits
        // umgehen sowie falsche IPs in Gebots-/Log-Daten schreiben.
        // TRUSTED_PROXIES: Komma-Liste (lokaler Proxy + Cloudflare-Ranges,
        // siehe docs/DEPLOYMENT.md). Fallback: nur Loopback/private Netze.
        $middleware->trustProxies(at: array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16')),
        ));

        // Sicherheits-Header auf JEDER Antwort (Shop, Panels, Fehlerseiten).
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
