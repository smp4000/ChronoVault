<?php

/**
 * =========================================================================
 * SendProposalReplyAction — Antwort auf einen Preisvorschlag senden
 * =========================================================================
 *
 * Zweck:
 *   Versendet die im Panel verfasste (optional KI-entworfene) Antwort
 *   als DealerReplyMail an den Kunden. Bewusst KEINE Statusänderung —
 *   Annehmen/Gegenangebot/Ablehnen bleiben eigene Aktionen.
 *
 * Aufrufer: PriceProposalsTable (Filament-Aktion „Antworten").
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Actions\Shop;

use App\Mail\DealerReplyMail;
use App\Models\PriceProposal;
use Illuminate\Support\Facades\Mail;

class SendProposalReplyAction
{
    public function execute(PriceProposal $proposal, string $subject, string $message): void
    {
        Mail::to($proposal->email)
            ->send(new DealerReplyMail($proposal, $subject, $message));
    }
}
