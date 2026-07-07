<?php

/**
 * =========================================================================
 * routes/tenant.php — HTTP-Routen im Mandanten-Kontext
 * =========================================================================
 *
 * Zweck:
 *   Alle Routen, die auf Mandanten-Domains ({slug}.localhost) laufen.
 *   Die Filament-Panel-Routen des Tenant-Panels registriert der
 *   AppPanelProvider selbst (inkl. Tenancy-Middleware) — hier liegt nur,
 *   was außerhalb des Panels existiert.
 *
 * Aktuell:
 *   - "/" → Redirect auf das Tenant-Panel (/app). Ein Mandant hat keine
 *     eigene Marketing-Startseite; die Anwendung IST das Panel.
 *
 * Mögliche Erweiterungen:
 *   - Öffentliche Schaufenster-Seiten pro Händler (Modul Marktplatz)
 *   - Webhook-Endpunkte im Tenant-Kontext
 * =========================================================================
 */

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/', fn () => redirect('/app'));
});
