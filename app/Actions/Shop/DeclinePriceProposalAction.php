<?php

/**
 * =========================================================================
 * DeclinePriceProposalAction — Preisvorschlag ablehnen + Kunden-Mail
 * =========================================================================
 *
 * Zweck:
 *   Schließt einen Preisvorschlag als „Abgelehnt" und schickt dem
 *   Kunden die „Schade"-Mail — mit optional frei formuliertem Text
 *   des Händlers (sonst Standardtext). Ein Mail-Fehler verhindert
 *   die Ablehnung nie (nur Log).
 *
 * Aufrufer: PriceProposalsTable (Panel-Aktion „Ablehnen") und
 * ShopController::proposalDecision (Ablehnen-Link des Kunden — dort
 * ohne eigenen Text).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Actions\Shop;

use App\Enums\PriceProposalStatus;
use App\Mail\ProposalDeclinedMail;
use App\Models\PriceProposal;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class DeclinePriceProposalAction
{
    public function execute(PriceProposal $proposal, ?string $customText = null): PriceProposal
    {
        $status = $proposal->getAttribute('status');

        if (! $status instanceof PriceProposalStatus || ! $status->isOpen()) {
            throw new RuntimeException('Dieser Preisvorschlag ist bereits abschließend bearbeitet.');
        }

        $proposal->update(['status' => PriceProposalStatus::Declined]);

        try {
            Mail::to($proposal->email)
                ->send(new ProposalDeclinedMail($proposal->refresh(), $customText));
        } catch (Throwable $exception) {
            report($exception);
        }

        return $proposal;
    }
}
