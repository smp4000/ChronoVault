<?php

use App\Actions\Tenancy\CreateTenantAction;
use App\Actions\Tenancy\DeleteTenantAction;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * =========================================================================
 * Pest.php — Zentrale Pest-Testkonfiguration
 * =========================================================================
 *
 * Zweck:
 *   Bindet die Laravel-TestCase-Basisklasse an alle Feature-Tests und
 *   stellt projektweite Test-Helper bereit.
 *
 * Verantwortlichkeiten:
 *   - Feature-Tests erhalten die Laravel-Anwendung (Tests\TestCase)
 *   - Unit-Tests bleiben bewusst framework-frei (schnell, isoliert)
 *
 * Nutzung:
 *   php artisan test        (führt alle Pest-Tests aus)
 *   php artisan test --filter=<Name>
 *
 * Mögliche Erweiterungen:
 *   - RefreshDatabase global für Feature-Tests aktivieren, sobald
 *     Domänenmodule mit DB-Zugriff getestet werden (Modul 1+)
 *   - Eigene Expectations (expect()->extend(...)) für Domänenobjekte
 * =========================================================================
 */
pest()->extend(TestCase::class)
    // Zentrale Test-DB (sqlite :memory:) vor jedem Test frisch migrieren.
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Helper: Mandant über den offiziellen Weg (Action) provisionieren.
 * Liegt hier (statt in einer Testdatei), damit ALLE Feature-Tests ihn
 * nutzen können — PHP-Funktionen dürfen nur einmal definiert werden.
 */
function provisionTenant(string $name = 'Test Uhrenhandel GmbH', ?string $slug = null): Tenant
{
    return app(CreateTenantAction::class)->execute(
        name: $name,
        ownerName: 'Test Owner',
        ownerEmail: 'owner@example.test',
        ownerPassword: 'SecurePassword!123',
        slug: $slug,
    );
}

/**
 * Helper: Tenant inkl. DB-Datei wieder entfernen (Tests hinterlassen
 * sonst sqlite-Dateien unter database/).
 */
function destroyTenant(Tenant $tenant): void
{
    app(DeleteTenantAction::class)->execute($tenant);
}
