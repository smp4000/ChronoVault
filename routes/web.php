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

use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\SellerRegistrationController;
use Illuminate\Support\Facades\Route;

foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        // Startseite der Plattform = der Marktplatz (eBay-Prinzip:
        // Angebote aller Verkäufer, privat und gewerblich).
        // BEWUSST OHNE Routen-Namen: die Schleife registriert die Routen
        // je Central-Domain mehrfach — doppelte Namen brechen
        // php artisan route:cache. Views verlinken relativ (url()).
        Route::get('/', [MarketplaceController::class, 'index']);

        // Zentrale Angebotsseite (v. a. Privatverkäufer, eBay-Prinzip):
        // Anfrage und Preisvorschlag laufen auf der Plattform und werden
        // in den Mandanten des Verkäufers durchgereicht.
        Route::get('/angebot/{listing}', [MarketplaceController::class, 'show']);
        Route::post('/angebot/{listing}/anfrage', [MarketplaceController::class, 'inquire'])
            ->middleware('throttle:5,1');
        Route::post('/angebot/{listing}/preisvorschlag', [MarketplaceController::class, 'propose'])
            ->middleware('throttle:5,1');

        // Selbst-Registrierung „Jetzt verkaufen" (privat/gewerblich).
        // Enges Throttle: jede Registrierung provisioniert eine Datenbank.
        Route::get('/verkaufen', [SellerRegistrationController::class, 'create']);
        Route::post('/verkaufen', [SellerRegistrationController::class, 'store'])
            ->middleware('throttle:5,60');

        // Rechtsseiten der PLATTFORM (DSGVO-Audit 2026-07-22): Der
        // Marktplatz ist selbst Diensteanbieter — Impressum und
        // Datenschutzerklärung sind Pflicht, unabhängig von den
        // Rechtsseiten der einzelnen Verkäufer-Shops.
        Route::get('/impressum', [MarketplaceController::class, 'legal'])
            ->defaults('page', 'imprint');
        Route::get('/datenschutz', [MarketplaceController::class, 'legal'])
            ->defaults('page', 'privacy');
    });
}
