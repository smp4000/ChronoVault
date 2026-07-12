<?php

/**
 * =========================================================================
 * MarketplaceTest — Zentraler Marktplatz (Listings-Spiegel, eBay-Prinzip)
 * =========================================================================
 *
 * Abgedeckt:
 *   - Observer spiegelt veröffentlichte kaufbare Uhren in die zentrale
 *     marketplace_listings-Tabelle (und entfernt Verkauftes/Unveröffent-
 *     lichtes wieder)
 *   - Marktplatz-Seite auf der ZENTRALEN Domain zeigt Angebote mit
 *     Verkäufer-Badge (privat/gewerblich) und verlinkt in den Shop
 *   - Freitextsuche + Verkäufertyp-Filter
 *   - marketplace:sync als Backfill/Reparatur
 * =========================================================================
 */

declare(strict_types=1);

use App\Enums\WatchStatus;
use App\Models\Brand;
use App\Models\MarketplaceListing;
use App\Models\Watch;

it('mirrors published buyable watches into the central marketplace', function () {
    $tenant = provisionTenant();

    try {
        $watchId = null;

        $tenant->run(function () use (&$watchId) {
            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'model_name' => 'Marktplatz Submariner',
                'reference_number' => '126610LV',
                'status' => WatchStatus::InStock,
                'is_published' => true,
                'asking_price' => 14200,
            ]);
            $watchId = (string) $watch->getKey();

            // Unveröffentlicht → NICHT auf dem Marktplatz
            Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'model_name' => 'Interne GMT',
                'status' => WatchStatus::InStock,
                'is_published' => false,
            ]);
        });

        // Spiegel-Zeile in der ZENTRALEN Datenbank (Observer bei saved)
        $listing = MarketplaceListing::query()
            ->where('tenant_id', (string) $tenant->getTenantKey())
            ->where('watch_id', $watchId)
            ->first();

        expect($listing)->not->toBeNull()
            ->and($listing->brand_name)->toBe('Rolex')
            ->and($listing->model_name)->toBe('Marktplatz Submariner')
            ->and((float) $listing->price)->toBe(14200.0)
            ->and($listing->seller_type)->toBe('commercial')
            ->and($listing->detail_url)->toContain('/uhren/'.$watchId)
            ->and(MarketplaceListing::query()->where('model_name', 'Interne GMT')->exists())->toBeFalse();

        // Verkauf nimmt das Angebot vom Marktplatz
        $tenant->run(function () use ($watchId) {
            Watch::findOrFail($watchId)->update(['status' => WatchStatus::Sold]);
        });

        expect(MarketplaceListing::query()->where('watch_id', $watchId)->exists())->toBeFalse();
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});

it('shows marketplace listings on the central domain with seller badge and search', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $rolex = Brand::where('name', 'Rolex')->firstOrFail();
            $omega = Brand::where('name', 'Omega')->firstOrFail();

            Watch::factory()->create([
                'brand_id' => $rolex->id,
                'model_name' => 'Zentrale Daytona',
                'reference_number' => '116500LN',
                'status' => WatchStatus::InStock,
                'is_published' => true,
                'asking_price' => 29500,
            ]);

            Watch::factory()->create([
                'brand_id' => $omega->id,
                'model_name' => 'Zentrale Speedmaster',
                'status' => WatchStatus::InStock,
                'is_published' => true,
                'asking_price' => 6100,
            ]);
        });

        tenancy()->end();

        // Marktplatz läuft auf der ZENTRALEN Domain (localhost)
        $this->get('http://localhost/')
            ->assertOk()
            ->assertSee('Zentrale Daytona')
            ->assertSee('Zentrale Speedmaster')
            ->assertSee('Gewerblich')
            ->assertSee('29.500');

        // Freitextsuche grenzt ein
        $this->get('http://localhost/?suche=Daytona')
            ->assertOk()
            ->assertSee('Zentrale Daytona')
            ->assertDontSee('Zentrale Speedmaster');

        // Verkäufertyp-Filter: privat blendet gewerbliche Angebote aus
        $this->get('http://localhost/?verkaeufer=private')
            ->assertOk()
            ->assertDontSee('Zentrale Daytona');
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});

it('rebuilds the marketplace mirror via marketplace:sync', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'model_name' => 'Backfill Explorer',
                'status' => WatchStatus::InStock,
                'is_published' => true,
                'asking_price' => 7300,
            ]);
        });

        tenancy()->end();

        // Spiegel absichtlich leeren — der Sync baut ihn wieder auf
        MarketplaceListing::query()->where('tenant_id', (string) $tenant->getTenantKey())->delete();

        $this->artisan('marketplace:sync')->assertSuccessful();

        expect(
            MarketplaceListing::query()
                ->where('tenant_id', (string) $tenant->getTenantKey())
                ->where('model_name', 'Backfill Explorer')
                ->exists(),
        )->toBeTrue();
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});
