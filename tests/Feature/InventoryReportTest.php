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
 *   - Wert-Zertifikat je Uhr (renderCertificatePdf) rendert als PDF
 * =========================================================================
 */

declare(strict_types=1);

use App\Enums\WatchStatus;
use App\Models\Brand;
use App\Models\Watch;
use App\Services\InventoryReportService;
use Barryvdh\DomPDF\Facade\Pdf;

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

            // Eigentum (private Sammlung) → zählt IMMER mit (versichert)
            Watch::factory()->create([
                'brand_id' => $brandId,
                'model_name' => 'Sammlungs-Uhr',
                'status' => WatchStatus::PrivateCollection,
                'current_market_value' => 3000,
            ]);

            $service = app(InventoryReportService::class);

            // Ohne Kommission: 3 Uhren (inkl. Sammlung), Summe 20.000
            $report = $service->data();

            expect($report['count'])->toBe(3)
                ->and($report['total'])->toBe(20000.0)
                ->and(collect($report['rows'])->pluck('name')->join(' '))->not->toContain('Verkaufte Uhr')
                ->and(collect($report['rows'])->firstWhere('serial', 'S-111')['valueSource'])->toBe('Marktwert')
                ->and(collect($report['rows'])->firstWhere('valueSource', 'Angebotspreis'))->not->toBeNull()
                // Einkaufspreise standardmäßig NICHT ausgewiesen
                ->and(collect($report['rows'])->pluck('purchasePrice')->filter()->all())->toBe([]);

            // Mit Kommission + Einkaufspreisen
            $full = $service->data(includeConsignment: true, includePurchase: true);

            expect($full['count'])->toBe(4)
                ->and($full['total'])->toBe(27000.0)
                ->and(collect($full['rows'])->firstWhere('isConsignment', true)['name'])->toContain('Kommissions-Uhr')
                ->and((float) collect($full['rows'])->firstWhere('serial', 'S-111')['purchasePrice'])->toBe(8000.0);

            // Mappe rendert (Übersicht + Zertifikate) — auch ohne Zertifikate
            expect(str_starts_with($service->renderPdf(), '%PDF'))->toBeTrue()
                ->and(str_starts_with($service->renderPdf(withCertificates: false), '%PDF'))->toBeTrue();
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('applies the purchase price floor with age surcharge when market is below purchase', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $brandId = Brand::where('name', 'Rolex')->firstOrFail()->id;

            // Marktwert (3.300) UNTER EK (4.150), Kauf vor 6 Monaten -> EK +10 %
            Watch::factory()->create([
                'brand_id' => $brandId,
                'model_name' => 'Junge Uhr',
                'status' => WatchStatus::InStock,
                'purchase_price' => 4150,
                'purchase_date' => now()->subMonths(6),
                'current_market_value' => 3300,
            ]);

            // Kauf vor 1,5 Jahren -> EK +15 %
            Watch::factory()->create([
                'brand_id' => $brandId,
                'model_name' => 'Mittlere Uhr',
                'status' => WatchStatus::InStock,
                'purchase_price' => 1000,
                'purchase_date' => now()->subMonths(18),
                'current_market_value' => 900,
            ]);

            // Kauf vor 4 Jahren -> EK +20 %
            Watch::factory()->create([
                'brand_id' => $brandId,
                'model_name' => 'Alte Uhr',
                'status' => WatchStatus::InStock,
                'purchase_price' => 2000,
                'purchase_date' => now()->subYears(4),
                'current_market_value' => 1500,
            ]);

            // Marktwert UEBER EK -> Marktwert gilt unveraendert
            Watch::factory()->create([
                'brand_id' => $brandId,
                'model_name' => 'Gestiegene Uhr',
                'status' => WatchStatus::InStock,
                'purchase_price' => 5000,
                'purchase_date' => now()->subYears(2),
                'current_market_value' => 7000,
            ]);

            $rows = collect(app(InventoryReportService::class)->data()['rows']);

            $valueOf = fn (string $name) => $rows->firstWhere(fn ($row) => str_contains($row['name'], $name));

            expect($valueOf('Junge Uhr')['value'])->toBe(4565.0)     // 4150 * 1,10
                ->and($valueOf('Junge Uhr')['valueSource'])->toBe('EK +10 % (1. Jahr)')
                ->and($valueOf('Mittlere Uhr')['value'])->toBe(1150.0) // 1000 * 1,15
                ->and($valueOf('Mittlere Uhr')['valueSource'])->toBe('EK +15 % (2. Jahr)')
                ->and($valueOf('Alte Uhr')['value'])->toBe(2400.0)     // 2000 * 1,20
                ->and($valueOf('Alte Uhr')['valueSource'])->toBe('EK +20 % (ab 3. Jahr)')
                ->and($valueOf('Gestiegene Uhr')['value'])->toBe(7000.0)
                ->and($valueOf('Gestiegene Uhr')['valueSource'])->toBe('Marktwert');
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('renders a value certificate pdf for a single watch', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'model_name' => 'Zertifikats-Uhr',
                'status' => WatchStatus::PrivateCollection,
                'serial_number' => 'Z998877',
                'purchase_price' => 6000,
                'purchase_date' => now()->subMonths(3),
                'current_market_value' => 9000,
            ]);

            $service = app(InventoryReportService::class);

            // Zertifikat rendert als gültiges PDF (Inhalt ist komprimiert)
            $pdf = $service->renderCertificatePdf($watch, "Max Mustermann\nMusterweg 1\n12345 Musterstadt");

            expect(str_starts_with($pdf, '%PDF'))->toBeTrue();

            // Auch ohne Kaufdaten und mit geschwärzter Seriennummer
            $masked = $service->renderCertificatePdf($watch, null, includePurchase: false, maskSerial: true);

            expect(str_starts_with($masked, '%PDF'))->toBeTrue();
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('attaches original documents (image and pdf) to the certificate', function () {
    $tenant = provisionTenant();

    try {
        $tenant->run(function () {
            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'model_name' => 'Beleg-Uhr',
                'status' => WatchStatus::PrivateCollection,
                'purchase_price' => 5000,
                'current_market_value' => 6000,
            ]);

            // Bild-Beleg (fotografierte Kaufrechnung)
            $image = imagecreatetruecolor(800, 1100);
            imagefill($image, 0, 0, imagecolorallocate($image, 245, 245, 245));
            $imagePath = sys_get_temp_dir().'/receipt.jpg';
            imagejpeg($image, $imagePath);
            imagedestroy($image);
            $watch->addMedia($imagePath)->usingFileName('kaufrechnung-foto.jpg')->toMediaCollection('documents');

            // PDF-Beleg (Original-Kaufrechnung als PDF)
            $pdfPath = sys_get_temp_dir().'/receipt.pdf';
            file_put_contents($pdfPath, Pdf::loadHTML('<p>Original-Kaufrechnung 12345</p>')->output());
            $watch->addMedia($pdfPath)->usingFileName('kaufrechnung.pdf')->toMediaCollection('documents');

            $service = app(InventoryReportService::class);

            $without = $service->renderCertificatePdf($watch, withDocuments: false);
            $with = $service->renderCertificatePdf($watch->fresh());

            // Mit Belegen: gültiges (FPDI-gemergtes) PDF, deutlich größer
            expect(str_starts_with($with, '%PDF'))->toBeTrue()
                ->and(strlen($with))->toBeGreaterThan(strlen($without));

            // Mappe heftet die Belege ebenfalls an
            $folder = $service->renderPdf(includePurchase: true);

            expect(str_starts_with($folder, '%PDF'))->toBeTrue();
        });
    } finally {
        destroyTenant($tenant);
    }
});
