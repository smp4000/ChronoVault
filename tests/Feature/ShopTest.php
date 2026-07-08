<?php

/**
 * =========================================================================
 * ShopTest — Öffentliches Schaufenster (Shop auf der Tenant-Domain)
 * =========================================================================
 *
 * Abgedeckt:
 *   - Listing zeigt nur veröffentlichte UND verkäufliche Uhren
 *   - Preisanzeige (formatiert) vs. „Preis auf Anfrage"
 *   - Markenfilter (?marke=<brand_id>)
 *   - Detailseite: 200 für veröffentlichte, 404 für unveröffentlichte
 *     und verkaufte Uhren (Interna bleiben unsichtbar)
 *
 * WICHTIG (Muster aus WatchPhotoDownloadTest): HTTP-Requests auf die
 * Tenant-Domain initialisieren Tenancy und beenden sie nicht — ohne
 * tenancy()->end() im finally räumt PHPUnit auf der bereits gelöschten
 * Tenant-Verbindung auf und maskiert das echte Testergebnis.
 * =========================================================================
 */

declare(strict_types=1);

use App\Enums\WatchStatus;
use App\Models\Brand;
use App\Models\Watch;

it('lists only published sellable watches in the shop', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $rolex = Brand::where('name', 'Rolex')->firstOrFail();

            Watch::factory()->create([
                'brand_id' => $rolex->id,
                'model_name' => 'Sichtbare Submariner',
                'status' => WatchStatus::InStock,
                'is_published' => true,
                'asking_price' => 12500,
            ]);

            Watch::factory()->create([
                'brand_id' => $rolex->id,
                'model_name' => 'Unveroeffentlichte GMT',
                'status' => WatchStatus::InStock,
                'is_published' => false,
            ]);

            Watch::factory()->create([
                'brand_id' => $rolex->id,
                'model_name' => 'Verkaufte Daytona',
                'status' => WatchStatus::Sold,
                'is_published' => true,
            ]);
        });

        $response = $this->get('http://'.$tenant->primaryDomain().'/');

        $response->assertOk()
            ->assertSee('Sichtbare Submariner')
            ->assertSee('12.500')
            ->assertDontSee('Unveroeffentlichte GMT')
            ->assertDontSee('Verkaufte Daytona');
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});

it('shows price on request when no asking price is set', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'model_name' => 'Preislose Explorer',
                'status' => WatchStatus::InStock,
                'is_published' => true,
                'asking_price' => null,
            ]);
        });

        $this->get('http://'.$tenant->primaryDomain().'/')
            ->assertOk()
            ->assertSee('Preislose Explorer')
            ->assertSee('Preis auf Anfrage');
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});

it('filters the shop listing by brand', function () {
    $tenant = provisionTenant();

    try {
        $omegaId = null;

        $tenant->run(function () use (&$omegaId) {
            $rolex = Brand::where('name', 'Rolex')->firstOrFail();
            $omega = Brand::where('name', 'Omega')->firstOrFail();
            $omegaId = $omega->id;

            Watch::factory()->create([
                'brand_id' => $rolex->id,
                'model_name' => 'Rolex Modell',
                'status' => WatchStatus::InStock,
                'is_published' => true,
            ]);

            Watch::factory()->create([
                'brand_id' => $omega->id,
                'model_name' => 'Omega Modell',
                'status' => WatchStatus::InStock,
                'is_published' => true,
            ]);
        });

        $this->get('http://'.$tenant->primaryDomain().'/?marke='.$omegaId)
            ->assertOk()
            ->assertSee('Omega Modell')
            ->assertDontSee('Rolex Modell');
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});

it('shows the detail page for a published watch', function () {
    $tenant = provisionTenant();

    try {
        $watchId = null;

        $tenant->run(function () use (&$watchId) {
            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'model_name' => 'Detail Submariner',
                'reference_number' => '126610LN',
                'status' => WatchStatus::InStock,
                'is_published' => true,
                'asking_price' => 13900.50,
            ]);
            $watchId = $watch->id;
        });

        $this->get('http://'.$tenant->primaryDomain().'/uhren/'.$watchId)
            ->assertOk()
            ->assertSee('Detail Submariner')
            ->assertSee('126610LN')
            ->assertSee('13.900,50')
            ->assertSee('Technische Daten');
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});

it('returns 404 for unpublished and sold watches on the detail page', function () {
    $tenant = provisionTenant();

    try {
        $unpublishedId = null;
        $soldId = null;

        $tenant->run(function () use (&$unpublishedId, &$soldId) {
            $rolex = Brand::where('name', 'Rolex')->firstOrFail();

            $unpublishedId = Watch::factory()->create([
                'brand_id' => $rolex->id,
                'status' => WatchStatus::InStock,
                'is_published' => false,
            ])->id;

            $soldId = Watch::factory()->create([
                'brand_id' => $rolex->id,
                'status' => WatchStatus::Sold,
                'is_published' => true,
            ])->id;
        });

        $domain = $tenant->primaryDomain();

        $this->get('http://'.$domain.'/uhren/'.$unpublishedId)->assertNotFound();
        $this->get('http://'.$domain.'/uhren/'.$soldId)->assertNotFound();
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});
