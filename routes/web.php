<?php

/**
 * =========================================================================
 * routes/web.php — HTTP-Routen der ZENTRALEN Domains
 * =========================================================================
 *
 * Zweck:
 *   Routen, die nur auf den zentralen Domains (localhost, später
 *   chronovault.app) existieren — Landingpage, Registrierung etc.
 *   Das zentrale Admin-Panel (/admin) registriert Filament selbst.
 *
 * WICHTIG — WARUM die Domain-Bindung:
 *   routes/tenant.php registriert ebenfalls eine "/"-Route. Ohne
 *   Domain-Bindung würde die zuletzt registrierte Route gewinnen und
 *   die jeweils andere überschreiben (Laravel indexiert Routen nach
 *   Methode+URI). Die explizite Bindung an die central_domains ist das
 *   von stancl/tenancy dokumentierte Muster: domaingebundene Routen
 *   matchen zuerst, die ungebundene Tenant-Route fängt alle
 *   Mandanten-Domains.
 * =========================================================================
 */

use Illuminate\Support\Facades\Route;

foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::get('/', function () {
            return view('welcome');
        });
    });
}
