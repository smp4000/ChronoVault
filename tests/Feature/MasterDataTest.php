<?php

/**
 * =========================================================================
 * MasterDataTest — Tests der Stammdaten (Marken & Kaliber, Modul 2)
 * =========================================================================
 *
 * Testumgebung:
 *   Wie TenantProvisioningTest: zentrale DB sqlite :memory:, Tenant-DBs
 *   als sqlite-Dateien — jeder Test räumt seine Tenants wieder ab.
 *   brands/calibers existieren NUR in Tenant-DBs → alle Assertions
 *   laufen innerhalb von $tenant->run(...).
 *
 * Abgedeckt:
 *   - Starter-Stammdaten beim Provisioning (MasterDataSeeder) inkl.
 *     Idempotenz und Respekt vor mandantenseitigen Löschungen
 *   - master_data.*-Berechtigungen je Rolle
 *   - BrandPolicy-Referenz-Schutz (Marke mit Kalibern nicht löschbar)
 *   - Factories, Beziehung Brand ↔ Caliber, Enum-Cast
 * =========================================================================
 */

declare(strict_types=1);

use App\Enums\MovementType;
use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Caliber;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;

it('seeds starter master data idempotently and respects tenant deletions', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            // Grundstock ist da und verknüpft
            expect(Brand::count())->toBeGreaterThanOrEqual(20)
                ->and(Caliber::count())->toBeGreaterThan(10);

            $rolex = Brand::where('name', 'Rolex')->firstOrFail();

            expect($rolex->calibers()->pluck('name'))->toContain('3235')
                ->and($rolex->country)->toBe('Schweiz');

            // Idempotenz: erneutes Seeden erzeugt keine Duplikate
            $brandCount = Brand::count();
            $caliberCount = Caliber::count();

            (new MasterDataSeeder)->run();

            expect(Brand::count())->toBe($brandCount)
                ->and(Caliber::count())->toBe($caliberCount);

            // Vom Mandanten gelöschte Grundstock-Marken bleiben gelöscht
            $sellita = Brand::where('name', 'Sellita')->firstOrFail();
            $sellita->calibers()->delete();
            $sellita->delete();

            (new MasterDataSeeder)->run();

            expect(Brand::where('name', 'Sellita')->exists())->toBeFalse()
                ->and(Brand::withTrashed()->where('name', 'Sellita')->exists())->toBeTrue();
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('grants master data permissions according to role semantics', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $employee = User::factory()->create();
            $employee->assignRole(UserRole::Employee->value);

            $viewer = User::factory()->create();
            $viewer->assignRole(UserRole::Viewer->value);

            // Mitarbeiter pflegen Stammdaten, löschen aber nicht
            expect($employee->can('master_data.view'))->toBeTrue()
                ->and($employee->can('master_data.create'))->toBeTrue()
                ->and($employee->can('master_data.update'))->toBeTrue()
                ->and($employee->can('master_data.delete'))->toBeFalse();

            // Betrachter: Nur-Lese-Zugriff
            expect($viewer->can('master_data.view'))->toBeTrue()
                ->and($viewer->can('master_data.create'))->toBeFalse();
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('protects brands with calibers from deletion via policy', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $owner = User::firstOrFail(); // Owner aus dem Provisioning

            $brandWithCalibers = Brand::where('name', 'Rolex')->firstOrFail();
            $emptyBrand = Brand::where('name', 'Cartier')->firstOrFail();

            expect($owner->can('delete', $brandWithCalibers))->toBeFalse()
                ->and($owner->can('delete', $emptyBrand))->toBeTrue()
                ->and($owner->can('forceDelete', $brandWithCalibers))->toBeFalse();
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('creates related master data through factories with correct casts', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $brand = Brand::factory()->create(['name' => 'Testmarke AG']);
            $caliber = Caliber::factory()->manual()->create([
                'brand_id' => $brand->id,
                'name' => 'T-100',
            ]);

            expect($caliber->brand->is($brand))->toBeTrue()
                ->and($brand->calibers()->count())->toBe(1)
                ->and($caliber->movement_type)->toBe(MovementType::Manual)
                ->and($caliber->is_active)->toBeTrue();

            // SoftDelete: Kaliber verschwindet aus Standard-Queries,
            // bleibt aber wiederherstellbar
            $caliber->delete();

            expect(Caliber::where('name', 'T-100')->exists())->toBeFalse()
                ->and(Caliber::withTrashed()->where('name', 'T-100')->exists())->toBeTrue();
        });
    } finally {
        destroyTenant($tenant);
    }
});
