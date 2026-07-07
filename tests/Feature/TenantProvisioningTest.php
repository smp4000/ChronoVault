<?php

/**
 * =========================================================================
 * TenantProvisioningTest — End-to-End-Tests des Mandanten-Lebenszyklus
 * =========================================================================
 *
 * Testumgebung:
 *   Zentrale DB: sqlite :memory: (phpunit.xml). Tenant-Datenbanken werden
 *   als echte sqlite-DATEIEN unter database/ angelegt (stancl
 *   SQLiteDatabaseManager) — deshalb räumt jeder Test seine Tenants über
 *   die DeleteTenantAction wieder ab.
 *
 * Abgedeckt:
 *   - Volles Provisioning (DB + Migrationen + Rollen-Seed + Domain + Owner)
 *   - Slug-Generierung inkl. Kollisionsauflösung (TenantObserver)
 *   - Zweistufiges Löschen (Archivieren vs. endgültig)
 * =========================================================================
 */

declare(strict_types=1);

use App\Actions\Tenancy\CreateTenantAction;
use App\Actions\Tenancy\DeleteTenantAction;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Helper: Mandant über den offiziellen Weg (Action) provisionieren.
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
 * Helper: Tenant-DB-Datei wieder entfernen (Tests hinterlassen sonst Dateien).
 */
function destroyTenant(Tenant $tenant): void
{
    app(DeleteTenantAction::class)->execute($tenant);
}

it('provisions a tenant with database, domain, roles and owner user', function () {
    $tenant = provisionTenant();

    try {
        // Zentrale Daten: Tenant + Domain
        expect($tenant->slug)->toBe('test-uhrenhandel-gmbh')
            ->and($tenant->status)->toBe(TenantStatus::Trial)
            ->and($tenant->primaryDomain())->toBe('test-uhrenhandel-gmbh.localhost');

        // Tenant-DB: Rollen geseedet, Owner angelegt und berechtigt
        $tenant->run(function () {
            expect(Role::count())->toBe(count(UserRole::cases()))
                ->and(User::count())->toBe(1);

            $owner = User::first();

            expect($owner->hasRole(UserRole::Owner->value))->toBeTrue()
                ->and($owner->can('users.create'))->toBeTrue()
                ->and($owner->email)->toBe('owner@example.test');
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('resolves slug collisions with a numeric suffix', function () {
    $first = provisionTenant('Juwelier Schmidt');
    $second = provisionTenant('Juwelier Schmidt');

    try {
        expect($first->slug)->toBe('juwelier-schmidt')
            ->and($second->slug)->toBe('juwelier-schmidt-2');
    } finally {
        destroyTenant($first);
        destroyTenant($second);
    }
});

it('archives a tenant reversibly without touching the tenant database', function () {
    $tenant = provisionTenant();

    try {
        app(DeleteTenantAction::class)->archive($tenant);

        // Soft-deleted + Status archiviert, aber Datensatz existiert weiter
        expect(Tenant::count())->toBe(0)
            ->and(Tenant::withTrashed()->count())->toBe(1);

        $archived = Tenant::withTrashed()->first();

        expect($archived->status)->toBe(TenantStatus::Archived)
            ->and($archived->trashed())->toBeTrue();

        // Die Tenant-DB lebt noch: Wiederherstellen + Zugriff funktioniert
        $archived->restore();
        $archived->run(function () {
            expect(User::count())->toBe(1);
        });
    } finally {
        destroyTenant($tenant->fresh());
    }
});

it('permanently deletes tenant record, domains and database', function () {
    $tenant = provisionTenant();
    $tenantId = $tenant->id;

    destroyTenant($tenant);

    expect(Tenant::withTrashed()->where('id', $tenantId)->exists())->toBeFalse()
        ->and(Domain::count())->toBe(0);
});
