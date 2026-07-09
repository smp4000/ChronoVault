<?php

/**
 * =========================================================================
 * CounterPriceProposalAction — Gegenangebot zu einem Preisvorschlag
 * =========================================================================
 *
 * Zweck:
 *   Der Händler unterbreitet dem Kunden ein Gegenangebot: Preis +
 *   optionales Porto + frei formulierter Text werden am Vorschlag
 *   gespeichert (Status → Gegenangebot) und per CounterOfferMail an
 *   den Kunden geschickt. Die Mail enthält Annehmen-/Ablehnen-Buttons
 *   (signierte Links, 14 Tage): Annahme wickelt den Kauf komplett ab
 *   (Verkauf, Rechnung, Kaufvertrag, Zahlungs-Mail), Ablehnung
 *   schließt den Vorgang und schickt eine „Schade"-Mail.
 *
 * Aufrufer: PriceProposalsTable (Filament-Aktion „Gegenangebot").
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Actions\Shop;

use App\Enums\PriceProposalStatus;
use App\Mail\CounterOfferMail;
use App\Models\PriceProposal;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class CounterPriceProposalAction
{
    public function execute(
        PriceProposal $proposal,
        float $counterPrice,
        ?float $shippingPrice = null,
        ?string $introText = null,
    ): PriceProposal {
        $status = $proposal->getAttribute('status');

        if (! $status instanceof PriceProposalStatus || ! $status->isOpen()) {
            throw new RuntimeException('Dieser Preisvorschlag ist bereits abschließend bearbeitet.');
        }

        $proposal->update([
            'counter_price' => $counterPrice,
            'shipping_price' => $shippingPrice,
            'status' => PriceProposalStatus::Countered,
        ]);

        try {
            Mail::to($proposal->email)
                ->send(new CounterOfferMail($proposal->refresh(), $introText));
        } catch (Throwable $exception) {
            report($exception);
        }

        return $proposal;
    }
}
