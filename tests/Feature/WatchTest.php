<?php

/**
 * =========================================================================
 * WatchTest — Tests des Uhrenbestands (Modul 3)
 * =========================================================================
 *
 * Testumgebung:
 *   Wie MasterDataTest: watches existiert nur in Tenant-DBs → alle
 *   Assertions laufen innerhalb von $tenant->run(...); jeder Test räumt
 *   seinen Tenant wieder ab.
 *
 * Abgedeckt:
 *   - Factory, Beziehungen (Brand/Caliber), Enum-Casts, SoftDeletes
 *   - watches.*-Berechtigungen je Rolle
 *   - Referenz-Schutz: Stammdaten mit Uhren sind nicht löschbar
 *   - Scout-Suche (database-Driver) im Tenant-Kontext
 * =========================================================================
 */

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\WatchCondition;
use App\Enums\WatchStatus;
use App\Models\Brand;
use App\Models\Caliber;
use App\Models\User;
use App\Models\Watch;

it('creates watches with relations, casts and soft deletes', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $brand = Brand::where('name', 'Rolex')->firstOrFail();
            $caliber = $brand->calibers()->where('name', '3235')->firstOrFail();

            $watch = Watch::factory()->fullSet()->create([
                'brand_id' => $brand->id,
                'caliber_id' => $caliber->id,
                'model_name' => 'Submariner Date',
                'reference_number' => '126610LN',
            ]);

            expect($watch->brand->is($brand))->toBeTrue()
                ->and($watch->caliber->is($caliber))->toBeTrue()
                ->and($watch->status)->toBe(WatchStatus::InStock)
                ->and($watch->condition)->toBeInstanceOf(WatchCondition::class)
                ->and($watch->has_box)->toBeTrue()
                ->and($watch->fullName())->toBe('Rolex Submariner Date (126610LN)')
                ->and($brand->watches()->count())->toBe(1);

            // SoftDelete: verschwindet aus Standard-Queries, bleibt wiederherstellbar
            $watch->delete();

            expect(Watch::count())->toBe(0)
                ->and(Watch::withTrashed()->count())->toBe(1);
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('grants watch permissions according to role semantics', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $employee = User::factory()->create();
            $employee->assignRole(UserRole::Employee->value);

            $viewer = User::factory()->create();
            $viewer->assignRole(UserRole::Viewer->value);

            expect($employee->can('watches.view'))->toBeTrue()
                ->and($employee->can('watches.create'))->toBeTrue()
                ->and($employee->can('watches.update'))->toBeTrue()
                ->and($employee->can('watches.delete'))->toBeFalse();

            expect($viewer->can('watches.view'))->toBeTrue()
                ->and($viewer->can('watches.create'))->toBeFalse();
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('protects master data referenced by watches from deletion', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $owner = User::firstOrFail(); // Owner aus dem Provisioning

            $brand = Brand::where('name', 'Omega')->firstOrFail();
            $caliber = $brand->calibers()->where('name', '8800')->firstOrFail();

            Watch::factory()->create([
                'brand_id' => $brand->id,
                'caliber_id' => $caliber->id,
            ]);

            // Marke UND Kaliber sind jetzt referenziert → nicht löschbar
            expect($owner->can('delete', $brand))->toBeFalse()
                ->and($owner->can('delete', $caliber))->toBeFalse()
                ->and($owner->can('forceDelete', $caliber))->toBeFalse();

            // Auch eine soft-gelöschte Uhr blockiert weiter (Referenz existiert physisch)
            Watch::query()->first()->delete();

            expect($owner->can('delete', $caliber))->toBeFalse();
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('finds watches through scout database search', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $brand = Brand::where('name', 'Rolex')->firstOrFail();

            Watch::factory()->create([
                'brand_id' => $brand->id,
                'model_name' => 'GMT-Master II',
                'reference_number' => '126710BLRO',
                'stock_number' => 'CV-PEPSI-1',
            ]);
            Watch::factory()->create([
                'brand_id' => $brand->id,
                'model_name' => 'Datejust 41',
            ]);

            expect(Watch::search('126710')->get())->toHaveCount(1)
                ->and(Watch::search('CV-PEPSI-1')->get()->first()->model_name)->toBe('GMT-Master II')
                ->and(Watch::search('Datejust')->get())->toHaveCount(1);
        });
    } finally {
        destroyTenant($tenant);
    }
});
