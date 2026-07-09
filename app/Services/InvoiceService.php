<?php

/**
 * =========================================================================
 * InvoiceService — Rechnungen, E-Rechnung (ZUGFeRD) und Kaufverträge
 * =========================================================================
 *
 * Zweck:
 *   1. getOrCreateForSale(): legt zur Verkaufs-Transaktion die Rechnung
 *      an — fortlaufende, lückenlose Nummer (RE-<Jahr>-<lfd. Nr.>) unter
 *      DB-Sperre, Beträge nach Besteuerungsart eingefroren,
 *      Snapshot von Verkäufer/Käufer/Position (GoBD-Gedanke).
 *   2. renderInvoicePdf(): klassisches Rechnungs-PDF (dompdf).
 *   3. renderZugferdPdf(): E-RECHNUNG — dasselbe PDF mit eingebettetem
 *      EN-16931-XML (ZUGFeRD/Factur-X, Profil EN 16931) via
 *      horstoeko/zugferd. Ein Dokument, menschen- UND maschinenlesbar.
 *   4. renderContractPdf(): Kaufvertrag aus demselben Snapshot.
 *
 * Steuer-Modi (Betriebsdaten-Seite):
 *   - differential   : § 25a UStG — KEIN USt-Ausweis, Pflichthinweis
 *                      „Gebrauchtgegenstände/Sonderregelung"
 *   - regular        : 19 % USt. (Preis = Brutto, Netto wird herausgerechnet)
 *   - small_business : § 19 UStG — kein USt-Ausweis, Kleinunternehmer-Hinweis
 *
 * Pflichtangaben-Guards: unvollständige Betriebsdaten oder fehlender
 * Käufer führen zu deutschen RuntimeExceptions für die UI.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Enums\WatchCondition;
use App\Models\Invoice;
use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoiceService
{
    /** Umsatzsteuersatz bei Regelbesteuerung. */
    private const VAT_RATE = 19.0;

    /**
     * Rechnung zum Verkaufsbeleg holen oder (einmalig) erstellen.
     */
    public function getOrCreateForSale(Transaction $sale): Invoice
    {
        if ($sale->getAttribute('type') !== TransactionType::Sale) {
            throw new RuntimeException('Rechnungen gibt es nur für Verkaufsbelege.');
        }

        $existing = Invoice::query()->where('transaction_id', $sale->getKey())->first();

        if ($existing !== null) {
            return $existing;
        }

        $buyer = $sale->contact;

        if ($buyer === null) {
            throw new RuntimeException('Der Verkaufsbeleg hat keinen Käufer — bitte zuerst einen Kontakt zuordnen.');
        }

        $seller = $this->sellerSnapshot();
        $taxMode = (string) (tenant('tax_mode') ?? 'differential');
        $total = (float) $sale->price;
        [$net, $tax] = $this->splitAmounts($total, $taxMode);

        $watch = $sale->watch;
        $transactedAt = $sale->getAttribute('transacted_at');

        // Enum-Cast typsicher lesen (etabliertes Larastan-Muster)
        $condition = $watch->getAttribute('condition');
        $conditionLabel = $condition instanceof WatchCondition ? $condition->getLabel() : null;

        $scope = implode(', ', array_filter([
            $watch->has_box ? 'Originalbox' : null,
            $watch->has_papers ? 'Papiere' : null,
        ]));

        return DB::transaction(function () use ($sale, $seller, $buyer, $taxMode, $total, $net, $tax, $watch, $transactedAt, $conditionLabel, $scope): Invoice {
            return Invoice::create([
                'transaction_id' => $sale->getKey(),
                'invoice_number' => $this->nextInvoiceNumber(),
                'issued_at' => now()->toDateString(),
                'delivery_date' => $transactedAt,
                'tax_mode' => $taxMode,
                'net_amount' => $net,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'currency' => $sale->currency ?? 'EUR',
                'seller' => $seller,
                'buyer' => [
                    'name' => $buyer->displayName(),
                    'street' => $buyer->street,
                    'postal_code' => $buyer->postal_code,
                    'city' => $buyer->city,
                    'country' => $buyer->country ?? 'Deutschland',
                    'email' => $buyer->email,
                ],
                'line' => [
                    'description' => $watch->fullName(),
                    'details' => array_values(array_filter([
                        $watch->serial_number ? 'Seriennummer: '.$watch->serial_number : null,
                        $watch->production_year ? 'Baujahr: '.($watch->is_production_year_approximate ? 'ca. ' : '').$watch->production_year : null,
                        $conditionLabel !== null ? 'Zustand: '.$conditionLabel : null,
                        $scope !== '' ? 'Lieferumfang: '.$scope : null,
                    ])),
                ],
            ]);
        });
    }

    /**
     * Klassisches Rechnungs-PDF (aus dem Snapshot, nie aus Live-Daten).
     */
    public function renderInvoicePdf(Invoice $invoice): string
    {
        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice])->output();
    }

    /**
     * E-RECHNUNG: ZUGFeRD-PDF (Profil EN 16931) — das Rechnungs-PDF mit
     * eingebettetem, maschinenlesbarem XML (Factur-X).
     */
    public function renderZugferdPdf(Invoice $invoice): string
    {
        $pdf = $this->renderInvoicePdf($invoice);

        $builder = new ZugferdDocumentPdfBuilder($this->buildZugferdXml($invoice), $pdf);

        return $builder->generateDocument()->downloadString();
    }

    /**
     * Kaufvertrag als PDF (aus demselben Snapshot wie die Rechnung).
     */
    public function renderContractPdf(Invoice $invoice): string
    {
        return Pdf::loadView('pdf.contract', ['invoice' => $invoice])->output();
    }

    /**
     * EN-16931-Dokument (XML) aus dem Rechnungs-Snapshot.
     */
    private function buildZugferdXml(Invoice $invoice): ZugferdDocumentBuilder
    {
        $seller = $invoice->sellerData();
        $buyer = $invoice->buyerData();
        $line = $invoice->lineData();

        $net = (float) $invoice->net_amount;
        $tax = (float) $invoice->tax_amount;
        $total = (float) $invoice->total_amount;

        [$category, $rate, $exemptionReason] = match ($invoice->tax_mode) {
            'regular' => ['S', self::VAT_RATE, null],
            'small_business' => ['E', 0.0, 'Kein Ausweis der Umsatzsteuer gemäß § 19 UStG (Kleinunternehmerregelung).'],
            default => ['E', 0.0, 'Gebrauchtgegenstände/Sonderregelung — Differenzbesteuerung nach § 25a UStG.'],
        };

        $doc = ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_EN16931);

        $sellerName = (string) ($seller['name'] ?? '');
        $details = implode('; ', array_map(strval(...), (array) ($line['details'] ?? [])));

        $doc->setDocumentInformation(
            $invoice->invoice_number,
            '380', // Handelsrechnung
            $invoice->issuedAtDate()->toDateTime(),
            $invoice->currency,
        )
            ->setDocumentSeller($sellerName)
            ->setDocumentSellerAddress((string) ($seller['street'] ?? ''), null, null, (string) ($seller['postal_code'] ?? ''), (string) ($seller['city'] ?? ''), 'DE')
            ->setDocumentBuyer((string) ($buyer['name'] ?? ''))
            ->setDocumentBuyerAddress((string) ($buyer['street'] ?? ''), null, null, (string) ($buyer['postal_code'] ?? ''), (string) ($buyer['city'] ?? ''), 'DE');

        if (! empty($seller['vat_id'])) {
            $doc->addDocumentSellerTaxRegistration('VA', (string) $seller['vat_id']);
        }

        if (! empty($seller['tax_number'])) {
            $doc->addDocumentSellerTaxRegistration('FC', (string) $seller['tax_number']);
        }

        $deliveryDate = $invoice->deliveryDateDate();

        if ($deliveryDate !== null) {
            $doc->setDocumentSupplyChainEvent($deliveryDate->toDateTime());
        }

        // SEPA-Überweisung als Zahlungsweg (sofern Bankdaten hinterlegt)
        if (! empty($seller['bank_iban'])) {
            $doc->addDocumentPaymentMean(
                '58',
                null,
                null,
                null,
                null,
                null,
                (string) $seller['bank_iban'],
                (string) ($seller['bank_account_holder'] ?? $sellerName),
                null,
                isset($seller['bank_bic']) ? (string) $seller['bank_bic'] : null,
            );
        }

        $doc->addNewPosition('1')
            ->setDocumentPositionProductDetails((string) ($line['description'] ?? ''), $details !== '' ? $details : null)
            ->setDocumentPositionQuantity(1.0, 'H87') // H87 = Stück
            ->setDocumentPositionNetPrice($net)
            ->setDocumentPositionLineSummation($net)
            ->addDocumentPositionTax($category, 'VAT', $rate);

        $doc->addDocumentTax($category, 'VAT', $net, $tax, $rate, $exemptionReason)
            ->setDocumentSummation($total, $total, $net, 0.0, 0.0, $net, $tax);

        return $doc;
    }

    /**
     * Nächste lückenlose Rechnungsnummer (RE-<Jahr>-<lfd. Nr.>) —
     * unter Sperre, damit zwei gleichzeitige Erstellungen nie dieselbe
     * Nummer ziehen. Rechnungen sind unlöschbar → count() ist lückenlos.
     */
    private function nextInvoiceNumber(): string
    {
        $prefix = 'RE-'.now()->format('Y').'-';

        $count = Invoice::query()
            ->where('invoice_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->count();

        return $prefix.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Verkäufer-Snapshot aus den Betriebsdaten — mit Pflichtangaben-Guard.
     *
     * @return array<string, mixed>
     */
    private function sellerSnapshot(): array
    {
        $seller = [
            'name' => (string) tenant('name'),
            'street' => tenant('company_street'),
            'postal_code' => tenant('company_postal_code'),
            'city' => tenant('company_city'),
            'tax_number' => tenant('tax_number'),
            'vat_id' => tenant('vat_id'),
            'bank_account_holder' => tenant('bank_account_holder'),
            'bank_iban' => tenant('bank_iban'),
            'bank_bic' => tenant('bank_bic'),
        ];

        if (blank($seller['street']) || blank($seller['postal_code']) || blank($seller['city'])) {
            throw new RuntimeException(
                'Bitte zuerst die Anschrift des Betriebs in den Betriebsdaten vervollständigen (Pflichtangabe auf Rechnungen).'
            );
        }

        if (blank($seller['tax_number']) && blank($seller['vat_id'])) {
            throw new RuntimeException(
                'Bitte Steuernummer ODER USt-IdNr. in den Betriebsdaten hinterlegen (Pflichtangabe auf Rechnungen, § 14 UStG).'
            );
        }

        return $seller;
    }

    /**
     * Brutto in Netto/Steuer zerlegen — je Besteuerungsart.
     *
     * @return array{0: float, 1: float}
     */
    private function splitAmounts(float $total, string $taxMode): array
    {
        if ($taxMode === 'regular') {
            $net = round($total / (1 + self::VAT_RATE / 100), 2);

            return [$net, round($total - $net, 2)];
        }

        // Differenzbesteuerung & Kleinunternehmer: kein USt-Ausweis
        return [$total, 0.0];
    }
}
