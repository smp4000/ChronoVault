<?php

/**
 * =========================================================================
 * TransactionTest — Kauf/Verkauf & Preishistorie (Modul 5)
 * =========================================================================
 *
 * Abgedeckt:
 *   - Verkauf über RecordSaleAction (Beleg + Status Verkauft + Marge)
 *   - Ankauf/Rückkauf über RecordPurchaseAction (Sync + zurück in Bestand)
 *   - Auto-Ankauf-Beleg beim Anlegen einer Uhr mit Einkaufsdaten (Observer)
 *   - Berechtigungen (contacts.* und transactions.*) je Rolle
 *   - Referenz-Schutz: Kontakt mit Belegen nicht löschbar
 * =========================================================================
 */

declare(strict_types=1);

use App\Actions\Transactions\RecordPurchaseAction;
use App\Actions\Transactions\RecordSaleAction;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Enums\WatchStatus;
use App\Models\Brand;
use App\Models\Contact;
use App\Models\User;
use App\Models\Watch;

it('records a sale, marks the watch as sold and calculates the margin', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'purchase_price' => '10000.00',
            ]);
            $buyer = Contact::factory()->create();

            $action = app(RecordSaleAction::class);
            $sale = $action->execute($watch, [
                'contact_id' => $buyer->id,
                'price' => 12500,
                'transacted_at' => '2026-07-01',
                'document_number' => 'VK-2026-0001',
            ]);

            expect($sale->type)->toBe(TransactionType::Sale)
                ->and($sale->contact->is($buyer))->toBeTrue()
                ->and($watch->refresh()->status)->toBe(WatchStatus::Sold)
                ->and($action->margin($watch, 12500.0))->toBe(2500.0)
                ->and($watch->transactions()->count())->toBeGreaterThanOrEqual(1);
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('records a repurchase and brings the watch back into stock', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $watch = Watch::factory()->sold()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
            ]);

            app(RecordPurchaseAction::class)->execute($watch, [
                'price' => 9000,
                'transacted_at' => '2026-07-05',
            ]);

            $watch->refresh();

            expect($watch->status)->toBe(WatchStatus::InStock)
                ->and($watch->purchase_price)->toBe('9000.00')
                ->and($watch->purchase_date->toDateString())->toBe('2026-07-05')
                ->and($watch->transactions()->where('type', TransactionType::Purchase)->count())->toBe(1);
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('creates a purchase record automatically when a watch is created with purchase data', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Omega')->firstOrFail()->id,
                'purchase_price' => '4200.00',
                'purchase_date' => '2026-06-15',
                'purchase_location' => 'Privatankauf',
            ]);

            $purchase = $watch->transactions()->where('type', TransactionType::Purchase)->first();

            expect($purchase)->not->toBeNull()
                ->and($purchase->price)->toBe('4200.00')
                ->and($purchase->transacted_at->toDateString())->toBe('2026-06-15')
                ->and($purchase->notes)->toContain('Privatankauf');

            // Uhren OHNE Einkaufsdaten bekommen keinen Auto-Beleg
            $bare = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Omega')->firstOrFail()->id,
            ]);

            expect($bare->transactions()->count())->toBe(0);
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('grants transaction and contact permissions according to role semantics', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $employee = User::factory()->create();
            $employee->assignRole(UserRole::Employee->value);

            $viewer = User::factory()->create();
            $viewer->assignRole(UserRole::Viewer->value);

            expect($employee->can('transactions.create'))->toBeTrue()
                ->and($employee->can('contacts.create'))->toBeTrue()
                ->and($employee->can('transactions.delete'))->toBeFalse()
                ->and($viewer->can('transactions.view'))->toBeTrue()
                ->and($viewer->can('transactions.create'))->toBeFalse();
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('protects contacts with transactions from deletion via policy', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $owner = User::firstOrFail();

            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
            ]);
            $usedContact = Contact::factory()->create();
            $freshContact = Contact::factory()->create();

            app(RecordSaleAction::class)->execute($watch, [
                'contact_id' => $usedContact->id,
                'price' => 5000,
                'transacted_at' => '2026-07-01',
            ]);

            expect($owner->can('delete', $usedContact))->toBeFalse()
                ->and($owner->can('forceDelete', $usedContact))->toBeFalse()
                ->and($owner->can('delete', $freshContact))->toBeTrue();
        });
    } finally {
        destroyTenant($tenant);
    }
});
