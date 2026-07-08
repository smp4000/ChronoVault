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
 *   - "/"            → Öffentlicher Shop (Schaufenster des Händlers)
 *   - "/uhren/{id}"  → Detailseite einer veröffentlichten Uhr
 *
 * WARUM der Shop auf "/" liegt:
 *   Die Tenant-Domain ist die öffentliche Adresse des Händlers — das
 *   Schaufenster gehört an die Wurzel. Das interne Panel bleibt unter
 *   /app erreichbar (Bookmark-kompatibel, eigener Login).
 *
 * Mögliche Erweiterungen:
 *   - Anfrage-Formular (POST), Webhook-Endpunkte im Tenant-Kontext
 * =========================================================================
 */

declare(strict_types=1);

use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/', [ShopController::class, 'index'])->name('shop.index');
    Route::get('/uhren/{watch}', [ShopController::class, 'show'])->name('shop.show');
});
