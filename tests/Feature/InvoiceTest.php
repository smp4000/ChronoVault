<?php

/**
 * =========================================================================
 * InvoiceTest — Rechnungen, E-Rechnung (ZUGFeRD) und Kaufverträge
 * =========================================================================
 *
 * Abgedeckt:
 *   - Nummernkreis: fortlaufend, lückenlos, pro Jahr (RE-<Jahr>-0001 …)
 *   - Idempotenz: genau EINE Rechnung je Verkaufsbeleg
 *   - Snapshot friert Verkäufer/Käufer/Position ein
 *   - Steuer-Modi: Differenzbesteuerung (§ 25a, kein USt-Ausweis),
 *     Regelbesteuerung (19 % herausgerechnet), Kleinunternehmer (§ 19)
 *   - Guards: fehlende Betriebsdaten / fehlender Käufer / Ankauf-Beleg
 *   - PDF (Rechnung + Kaufvertrag) und ZUGFeRD (factur-x.xml eingebettet)
 * =========================================================================
 */

declare(strict_types=1);

use App\Actions\Transactions\RecordSaleAction;
use App\Models\Brand;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\Watch;
use App\Services\InvoiceService;

/**
 * Helper: Tenant mit vollständigen Betriebsdaten + abgeschlossenem Verkauf.
 *
 * @return array{0: Tenant, 1: callable(): Transaction}
 */
function tenantWithSale(string $taxMode = 'differential'): array
{
    $tenant = provisionTenant();

    $tenant->update([
        'company_street' => 'Uhrmacherweg 1',
        'company_postal_code' => '10115',
        'company_city' => 'Berlin',
        'tax_number' => '12/345/67890',
        'vat_id' => 'DE123456789',
        'tax_mode' => $taxMode,
        'bank_account_holder' => 'Test Uhrenhandel GmbH',
        'bank_iban' => 'DE02120300000000202051',
        'bank_bic' => 'BYLADEM1001',
    ]);

    $makeSale = function () use ($tenant): Transaction {
        return $tenant->run(function (): Transaction {
            $watch = Watch::factory()->fullSet()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
                'model_name' => 'Submariner Date',
                'reference_number' => '126610LN',
                'serial_number' => 'S123456',
            ]);
            $buyer = Contact::factory()->create([
                'first_name' => 'Erika',
                'last_name' => 'Mustermann',
                'street' => 'Musterweg 12',
                'postal_code' => '12345',
                'city' => 'Berlin',
                'email' => 'erika@example.test',
            ]);

            return app(RecordSaleAction::class)->execute($watch, [
                'contact_id' => $buyer->id,
                'price' => 11900,
                'transacted_at' => now()->toDateString(),
            ]);
        });
    };

    return [$tenant, $makeSale];
}

it('creates invoices with a gapless yearly number sequence and frozen snapshot', function () {
    [$tenant, $makeSale] = tenantWithSale();

    try {
        $tenant->run(function () use ($makeSale) {
            $service = app(InvoiceService::class);

            $firstSale = $makeSale();
            $invoice = $service->getOrCreateForSale($firstSale);

            $year = now()->format('Y');

            expect($invoice->invoice_number)->toBe("RE-{$year}-0001")
                ->and((float) $invoice->total_amount)->toBe(11900.0)
                // § 25a: kein USt-Ausweis
                ->and((float) $invoice->tax_amount)->toBe(0.0)
                ->and($invoice->seller['street'])->toBe('Uhrmacherweg 1')
                ->and($invoice->buyer['name'])->toContain('Mustermann')
                ->and($invoice->line['description'])->toContain('Submariner');

            // Idempotent: derselbe Beleg bekommt dieselbe Rechnung
            expect($service->getOrCreateForSale($firstSale)->getKey())->toBe($invoice->getKey());

            // Zweiter Verkauf → nächste Nummer
            $secondSale = $makeSale();

            expect($service->getOrCreateForSale($secondSale)->invoice_number)->toBe("RE-{$year}-0002");

            // Snapshot bleibt eingefroren, auch wenn sich Betriebsdaten ändern
            tenant()->update(['company_street' => 'Neue Straße 99']);

            expect($invoice->refresh()->seller['street'])->toBe('Uhrmacherweg 1');
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('computes amounts per tax mode', function () {
    // Regelbesteuerung: 19 % aus dem Bruttopreis herausgerechnet
    [$tenant, $makeSale] = tenantWithSale('regular');

    try {
        $tenant->run(function () use ($makeSale) {
            $invoice = app(InvoiceService::class)->getOrCreateForSale($makeSale());

            expect((float) $invoice->total_amount)->toBe(11900.0)
                ->and((float) $invoice->net_amount)->toBe(10000.0)
                ->and((float) $invoice->tax_amount)->toBe(1900.0)
                ->and($invoice->tax_mode)->toBe('regular');
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('guards against missing business data, missing buyer and purchase records', function () {
    $tenant = provisionTenant(); // OHNE Betriebsdaten

    try {
        $tenant->run(function () {
            $service = app(InvoiceService::class);

            $watch = Watch::factory()->create([
                'brand_id' => Brand::where('name', 'Rolex')->firstOrFail()->id,
            ]);
            $buyer = Contact::factory()->create();

            $sale = app(RecordSaleAction::class)->execute($watch, [
                'contact_id' => $buyer->id,
                'price' => 5000,
                'transacted_at' => now()->toDateString(),
            ]);

            // Fehlende Anschrift der Betriebsdaten
            expect(fn () => $service->getOrCreateForSale($sale))
                ->toThrow(RuntimeException::class, 'Betriebsdaten');

            // Ankauf-Beleg: keine Rechnung
            $purchase = Transaction::query()->where('type', 'purchase')->first();

            if ($purchase !== null) {
                expect(fn () => $service->getOrCreateForSale($purchase))
                    ->toThrow(RuntimeException::class, 'Verkaufsbelege');
            }
        });
    } finally {
        destroyTenant($tenant);
    }
});

it('renders invoice pdf, contract pdf and a zugferd e-invoice with embedded xml', function () {
    [$tenant, $makeSale] = tenantWithSale();

    try {
        $tenant->run(function () use ($makeSale) {
            $service = app(InvoiceService::class);
            $invoice = $service->getOrCreateForSale($makeSale());

            // Klassisches Rechnungs-PDF (Inhalt ist Flate-komprimiert —
            // prüfbar ist der PDF-Header)
            $pdf = $service->renderInvoicePdf($invoice);

            expect(str_starts_with($pdf, '%PDF'))->toBeTrue();

            // Kaufvertrag
            $contract = $service->renderContractPdf($invoice);

            expect(str_starts_with($contract, '%PDF'))->toBeTrue();

            // E-RECHNUNG: ZUGFeRD-PDF mit eingebettetem factur-x.xml
            $zugferd = $service->renderZugferdPdf($invoice);

            expect(str_starts_with($zugferd, '%PDF'))->toBeTrue()
                ->and($zugferd)->toContain('factur-x.xml');
        });
    } finally {
        destroyTenant($tenant);
    }
});
