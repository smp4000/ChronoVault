<?php

/**
 * =========================================================================
 * CounterPriceProposalAction — Gegenangebot zu einem Preisvorschlag
 * =========================================================================
 *
 * Zweck:
 *   Der Händler unterbreitet dem Kunden ein Gegenangebot: Preis (+
 *   optionale Nachricht) wird am Vorschlag gespeichert (Status →
 *   Gegenangebot) und per CounterOfferMail an den Kunden geschickt —
 *   Reply-To ist die Benachrichtigungs-Adresse des Betriebs, damit die
 *   Antwort des Kunden direkt beim Händler landet.
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
    public function execute(PriceProposal $proposal, float $counterPrice, ?string $message = null): PriceProposal
    {
        $status = $proposal->getAttribute('status');

        if (! $status instanceof PriceProposalStatus || ! $status->isOpen()) {
            throw new RuntimeException('Dieser Preisvorschlag ist bereits abschließend bearbeitet.');
        }

        $proposal->update([
            'counter_price' => $counterPrice,
            'status' => PriceProposalStatus::Countered,
        ]);

        try {
            Mail::to($proposal->email)
                ->send(new CounterOfferMail($proposal->refresh(), $message));
        } catch (Throwable $exception) {
            report($exception);
        }

        return $proposal;
    }
}
