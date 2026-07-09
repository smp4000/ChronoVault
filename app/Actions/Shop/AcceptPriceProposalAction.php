<?php

/**
 * =========================================================================
 * AcceptPriceProposalAction — Preisvorschlag annehmen = Zuschlag (Shop)
 * =========================================================================
 *
 * Zweck:
 *   Der Händler nimmt einen Preisvorschlag an — damit kommt der Kauf
 *   zum Wunschpreis verbindlich zustande:
 *   1. Guard unter DB-Sperre: Uhr noch veröffentlicht und verkäuflich.
 *   2. Käufer-Kontakt anlegen/wiedererkennen (optional mit Adresse,
 *      falls der Händler sie im Annehmen-Dialog angibt).
 *   3. Verkaufsbeleg zum Vorschlagspreis (Uhr → Verkauft).
 *   4. Rechnung erstellen (Fehler bei unvollständigen Betriebsdaten
 *      nur loggen — der Zuschlag steht trotzdem).
 *   5. ProposalAcceptedMail an den Kunden: Zuschlag, Zahlungsinfos,
 *      GiroCode, Rechnung (ZUGFeRD) + Kaufvertrag als PDF.
 *   6. Vorschlag → Angenommen; andere offene Vorschläge zur selben
 *      Uhr → Abgelehnt (die Uhr ist weg).
 *
 * Aufrufer: PriceProposalsTable (Filament-Aktion „Annehmen").
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Actions\Shop;

use App\Actions\Transactions\RecordSaleAction;
use App\Enums\ContactType;
use App\Enums\PaymentMethod;
use App\Enums\PriceProposalStatus;
use App\Enums\WatchStatus;
use App\Mail\ProposalAcceptedMail;
use App\Models\Contact;
use App\Models\PriceProposal;
use App\Models\Watch;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class AcceptPriceProposalAction
{
    public function __construct(
        private readonly RecordSaleAction $recordSale,
    ) {}

    /**
     * @param  array{street?: string|null, postal_code?: string|null, city?: string|null}  $address  Optionale Lieferadresse aus dem Annehmen-Dialog
     * @param  float|null  $priceOverride  Abweichender Verkaufspreis (z. B. Gegenangebot inkl. Versand) — sonst Wunschpreis
     * @param  string|null  $priceNote  Zusatz für den Beleg (z. B. Versand-Aufschlüsselung)
     * @param  string|null  $personalNote  Persönlicher Absatz für die Zusage-Mail (optional, ggf. KI-entworfen)
     */
    public function execute(PriceProposal $proposal, array $address = [], ?float $priceOverride = null, ?string $priceNote = null, ?string $personalNote = null): PriceProposal
    {
        $status = $proposal->getAttribute('status');

        if (! $status instanceof PriceProposalStatus || ! $status->isOpen()) {
            throw new RuntimeException('Dieser Preisvorschlag ist bereits abschließend bearbeitet.');
        }

        $salePrice = $priceOverride ?? (float) $proposal->proposed_price;

        [$buyer, $sale, $watch] = DB::transaction(function () use ($proposal, $address, $salePrice, $priceNote): array {
            $watch = Watch::query()
                ->lockForUpdate()
                ->findOrFail($proposal->getAttribute('watch_id'));

            $sellable = in_array($watch->getAttribute('status'), WatchStatus::sellableStatuses(), true);

            if (! $watch->is_published || ! $sellable) {
                throw new RuntimeException('Diese Uhr ist nicht mehr verfügbar — der Vorschlag kann nicht angenommen werden.');
            }

            $buyer = $this->buyerContact($proposal, $address);

            $sale = $this->recordSale->execute($watch, [
                'contact_id' => $buyer->getKey(),
                'price' => $salePrice,
                'transacted_at' => now(),
                'payment_method' => PaymentMethod::BankTransfer->value,
                'notes' => trim('Preisvorschlag angenommen (Shop) — Verkaufspreis '
                    .number_format($salePrice, 2, ',', '.').' €. '.($priceNote ?? '')),
            ]);

            $proposal->update(['status' => PriceProposalStatus::Accepted]);

            // Weitere offene Vorschläge zur selben Uhr sind hinfällig
            PriceProposal::query()
                ->where('watch_id', $watch->getKey())
                ->whereKeyNot($proposal->getKey())
                ->whereIn('status', [PriceProposalStatus::New->value, PriceProposalStatus::Countered->value])
                ->update(['status' => PriceProposalStatus::Declined->value]);

            return [$buyer, $sale, $watch];
        });

        // Rechnung NACH der Transaktion — Fehler (z. B. unvollständige
        // Betriebsdaten) dürfen den Zuschlag nie zurückrollen.
        $invoice = null;

        try {
            $invoice = app(InvoiceService::class)->getOrCreateForSale($sale);
        } catch (Throwable $exception) {
            report($exception);
        }

        try {
            Mail::to($buyer->email)->send(new ProposalAcceptedMail($watch->refresh(), $buyer, $proposal, $invoice, $salePrice, $personalNote));
        } catch (Throwable $exception) {
            report($exception);
        }

        return $proposal->refresh();
    }

    /**
     * Käufer per E-Mail wiedererkennen, sonst neu anlegen — "Max
     * Mustermann" wird in Vor-/Nachname zerlegt (wie contactFromBid).
     *
     * @param  array{street?: string|null, postal_code?: string|null, city?: string|null}  $address
     */
    private function buyerContact(PriceProposal $proposal, array $address): Contact
    {
        $addressData = array_filter([
            'street' => $address['street'] ?? null,
            'postal_code' => $address['postal_code'] ?? null,
            'city' => $address['city'] ?? null,
        ]);

        $existing = Contact::query()->where('email', $proposal->email)->first();

        if ($existing !== null) {
            if ($addressData !== []) {
                $existing->update($addressData);
            }

            return $existing;
        }

        $parts = preg_split('/\s+/', trim($proposal->name), 2) ?: [];

        return Contact::create([
            ...$addressData,
            'type' => ContactType::PrivatePerson,
            'first_name' => isset($parts[1]) ? $parts[0] : null,
            'last_name' => $parts[1] ?? $parts[0] ?? $proposal->name,
            'email' => $proposal->email,
            'notes' => 'Automatisch angelegt aus angenommenem Preisvorschlag (Shop).',
        ]);
    }
}
