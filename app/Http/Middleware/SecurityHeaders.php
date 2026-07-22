<?php

/**
 * =========================================================================
 * SecurityHeaders — Sicherheits-HTTP-Header für ALLE Antworten
 * =========================================================================
 *
 * Zweck:
 *   Setzt die Standard-Sicherheitsheader am Origin (nicht nur an der
 *   Cloudflare-Edge), damit sie auch bei Direktzugriff und in jeder
 *   Umgebung garantiert sind. Befund des Security-Audits 2026-07-22:
 *   zuvor existierten KEINE Security-Header.
 *
 * Header und WARUM:
 *   - X-Frame-Options DENY .......... Clickjacking-Schutz für Panels & Shop
 *     (niemand bettet ChronoVault legitim in ein iframe ein)
 *   - X-Content-Type-Options ........ verhindert MIME-Sniffing (Uploads!)
 *   - Referrer-Policy ............... keine vollständigen URLs an fremde
 *     Seiten (signierte Links enthalten Signaturen in der Query!)
 *   - Permissions-Policy ............ deaktiviert Kamera/Mikro/Geo — die
 *     Anwendung braucht nichts davon (Foto-Upload nutzt <input type=file>)
 *   - Strict-Transport-Security ..... nur in Produktion (hinter TLS);
 *     lokal würde HSTS http://localhost dauerhaft brechen
 *
 * BEWUSST (noch) KEINE Content-Security-Policy:
 *   Livewire/Filament/Alpine benötigen unsafe-eval/unsafe-inline bzw.
 *   Nonce-Handling — eine halbherzige CSP wiegt in falscher Sicherheit.
 *   Saubere Nonce-basierte CSP ist als TODO in docs/SECURITY.md erfasst.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // HSTS nur in Produktion: 1 Jahr, inkl. Subdomains (Tenant-Shops!).
        // "preload" bewusst NICHT gesetzt — das wäre ohne Rückweg.
        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
