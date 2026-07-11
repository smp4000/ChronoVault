<?php

/**
 * =========================================================================
 * InventoryReportTest — Bestands- und Wertübersicht (Versicherungs-PDF)
 * =========================================================================
 *
 * Abgedeckt:
 *   - Wert-Logik: Marktwert → Angebotspreis → Einkaufspreis (mit Quelle)
 *   - Verkaufte Uhren fliegen raus; Kommission nur auf Wunsch
 *   - Einkaufspreise nur bei include_purchase
 *   - PDF-Rendern liefert ein gültiges PDF
 * =========================================================================
 */

declare(strict_types=1);

use App\Enums\WatchStatus;
use App\Models\Brand;
use App\Models\Watch;
use App\Services\InventoryReportService;

it('builds the inventory report with value fallbacks and totals', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $brandId = Brand::where('name', 'Rolex')->firstOrFail()->id;

            // Marktwert vorhanden → gewinnt
            Watch::factory()->create([
                'brand_id' => $brandId,
                'model_name' => 'Marktwert-Uhr',
                'status' => WatchStatus::InStock,
                'current_market_value' => 12000,
                'asking_price' => 11000,
                'purchase_price' => 8000,
                'serial_number' => 'S-111',
            ]);

            // Kein Marktwert → Angebotspreis
            Watch::factory()->create([
                'brand_id' => $brandId,
                'model_name' => 'Angebots-Uhr',
                'status' => WatchStatus::Reserved,
                'current_market_value' => null,
                'asking_price' => 5000,
                'purchase_price' => 3000,
            ]);

            // Verkauft → taucht nie auf
            Watch::factory()->create([
                'brand_id' => $brandId,
                'model_name' => 'Verkaufte Uhr',
                'status' => WatchStatus::Sold,
                'current_market_value' => 99999,
            ]);

            // Kommission → nur auf Wunsch (gekennzeichnet)
            Watch::factory()->create([
                'brand_id' => $brandId,
                'model_name' => 'Kommissions-Uhr',
                'status' => WatchStatus::Consignment,
                'current_market_value' => 7000,
            ]);

            $service = app(InventoryReportService::class);

            // Ohne Kommission: 2 Uhren, Summe 17.000
            $report = $service->data();

            expect($report['count'])->toBe(2)
                ->and($report['total'])->toBe(17000.0)
                ->and(collect($report['rows'])->pluck('name')->join(' '))->not->toContain('Verkaufte Uhr')
                ->and(collect($report['rows'])->firstWhere('serial', 'S-111')['valueSource'])->toBe('Marktwert')
                ->and(collect($report['rows'])->firstWhere('valueSource', 'Angebotspreis'))->not->toBeNull()
                // Einkaufspreise standardmäßig NICHT ausgewiesen
                ->and(collect($report['rows'])->pluck('purchasePrice')->filter()->all())->toBe([]);

            // Mit Kommission + Einkaufspreisen
            $full = $service->data(includeConsignment: true, includePurchase: true);

            expect($full['count'])->toBe(3)
                ->and($full['total'])->toBe(24000.0)
                ->and(collect($full['rows'])->firstWhere('isConsignment', true)['name'])->toContain('Kommissions-Uhr')
                ->and((float) collect($full['rows'])->firstWhere('serial', 'S-111')['purchasePrice'])->toBe(8000.0);

            // PDF rendert (dompdf komprimiert den Inhalt — nur Header prüfbar)
            expect(str_starts_with($service->renderPdf(), '%PDF'))->toBeTrue();
        });
    } finally {
        destroyTenant($tenant);
    }
});
