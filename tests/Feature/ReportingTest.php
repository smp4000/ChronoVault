<?php

/**
 * =========================================================================
 * ReportingTest — Kennzahlen-Aggregation für Dashboards (Modul 9)
 * =========================================================================
 *
 * Abgedeckt:
 *   - Umsatz/Marge/Anzahl je Monat (lückenlose Achse; Marge nur für
 *     Verkäufe mit Einkaufspreis)
 *   - Verkaufs-Kennzahlen inkl. Ø Standzeit (Einkauf → Verkauf)
 *   - Bestand nach Status (deutsche Labels, nur belegte Status)
 *   - Top-Marken nach gebundenem Kapital (unverkaufter Bestand)
 * =========================================================================
 */

declare(strict_types=1);

use App\Actions\Transactions\RecordSaleAction;
use App\Enums\WatchStatus;
use App\Filament\App\Widgets\InventoryByStatusWidget;
use App\Filament\App\Widgets\SalesChartWidget;
use App\Filament\App\Widgets\SalesStatsWidget;
use App\Filament\App\Widgets\TopBrandsWidget;
use App\Models\Brand;
use App\Models\User;
use App\Models\Watch;
use App\Services\ReportingService;

it('renders all reporting widgets with real data', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'purchase_price' => 5000,
                'purchase_date' => now()->subDays(30)->toDateString(),
            ]);
            app(RecordSaleAction::class)->execute($watch, [
                'price' => 8000,
                'transacted_at' => now()->toDateString(),
            ]);

            // Dashboard lädt Widgets lazy — direkt als Livewire-Komponenten
            // rendern deckt Property-/Daten-Fehler zuverlässig auf.
            $this->actingAs(User::firstOrFail());

            Livewire\Livewire::test(SalesStatsWidget::class)
                ->assertSee('Umsatz (12 Monate)')
                ->assertSee('8.000');

            Livewire\Livewire::test(SalesChartWidget::class)
                ->assertSee('Umsatz & Marge');

            Livewire\Livewire::test(InventoryByStatusWidget::class)
                ->assertSee('Bestand nach Status');

            Livewire\Livewire::test(TopBrandsWidget::class)
                ->assertSee('Top-Marken im Bestand');
        });
    } finally {
        tenancy()->end();
        destroyTenant($tenant);
    }
});

it('aggregates sales by month with margin only for priced purchases', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $brandId = Brand::where('name', 'Rolex')->firstOrFail()->id;

            // Verkauf diesen Monat: 8.000 € bei 5.000 € Einkauf → 3.000 € Marge
            $priced = Watch::factory()->create([
                'brand_id' => $brandId,
                'purchase_price' => 5000,
                'purchase_date' => now()->subDays(40)->toDateString(),
            ]);
            app(RecordSaleAction::class)->execute($priced, [
                'price' => 8000,
                'transacted_at' => now()->toDateString(),
            ]);

            // Verkauf letzten Monat OHNE Einkaufspreis: Umsatz ja, Marge nein
            $unpriced = Watch::factory()->create([
                'brand_id' => $brandId,
                'purchase_price' => null,
            ]);
            app(RecordSaleAction::class)->execute($unpriced, [
                'price' => 3000,
                'transacted_at' => now()->subMonthNoOverflow()->toDateString(),
            ]);

            $months = app(ReportingService::class)->salesByMonth(12);

            expect($months)->toHaveCount(12);

            $current = $months[11];
            $previous = $months[10];

            expect($current['revenue'])->toBe(8000.0)
                ->and($current['margin'])->toBe(3000.0)
                ->and($current['count'])->toBe(1)
                ->and($previous['revenue'])->toBe(3000.0)
                ->and($previous['margin'])->toBe(0.0)
                ->and($previous['count'])->toBe(1)
                // Monate ohne Verkauf bleiben als 0-Bucket erhalten
                ->and($months[0]['revenue'])->toBe(0.0)
                ->and($months[0]['count'])->toBe(0);
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('computes sales totals including average days in stock', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $brandId = Brand::where('name', 'Rolex')->firstOrFail()->id;

            // 40 Tage Standzeit
            $first = Watch::factory()->create([
                'brand_id' => $brandId,
                'purchase_price' => 5000,
                'purchase_date' => now()->subDays(40)->toDateString(),
            ]);
            app(RecordSaleAction::class)->execute($first, [
                'price' => 8000,
                'transacted_at' => now()->toDateString(),
            ]);

            // 20 Tage Standzeit
            $second = Watch::factory()->create([
                'brand_id' => $brandId,
                'purchase_price' => 2000,
                'purchase_date' => now()->subDays(20)->toDateString(),
            ]);
            app(RecordSaleAction::class)->execute($second, [
                'price' => 2500,
                'transacted_at' => now()->toDateString(),
            ]);

            $totals = app(ReportingService::class)->salesTotals(12);

            expect($totals['revenue'])->toBe(10500.0)
                ->and($totals['margin'])->toBe(3500.0)
                ->and($totals['count'])->toBe(2)
                ->and($totals['average_days_in_stock'])->toBe(30.0);
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('groups inventory by status and ranks brands by bound capital', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $rolex = Brand::where('name', 'Rolex')->firstOrFail();
            $omega = Brand::where('name', 'Omega')->firstOrFail();

            Watch::factory()->count(2)->create([
                'brand_id' => $rolex->id,
                'status' => WatchStatus::InStock,
                'purchase_price' => 10000,
            ]);
            Watch::factory()->create([
                'brand_id' => $omega->id,
                'status' => WatchStatus::Consignment,
                'purchase_price' => 3000,
            ]);
            // Verkauft: zählt im Status-Doughnut, NICHT bei den Top-Marken
            Watch::factory()->create([
                'brand_id' => $omega->id,
                'status' => WatchStatus::Sold,
                'purchase_price' => 99000,
            ]);

            $service = app(ReportingService::class);

            expect($service->inventoryByStatus())->toBe([
                'An Lager' => 2,
                'Kommission' => 1,
                'Verkauft' => 1,
            ]);

            $brands = $service->topBrandsByInventoryValue();

            expect($brands)->toHaveCount(2)
                ->and($brands[0]['brand'])->toBe('Rolex')
                ->and($brands[0]['value'])->toBe(20000.0)
                ->and($brands[0]['count'])->toBe(2)
                ->and($brands[1]['brand'])->toBe('Omega')
                ->and($brands[1]['value'])->toBe(3000.0);
        });
    } finally {
        destroyTenant($tenant);
    }
});
