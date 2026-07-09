<?php

/**
 * =========================================================================
 * AuctionNotAwardedMail — Auktion beendet, kein Zuschlag (Modul 8b)
 * =========================================================================
 *
 * Zweck:
 *   Informiert den HÖCHSTBIETENDEN nach Auktionsende, dass sein Gebot
 *   zwar das höchste war, das Limit des Einlieferers aber nicht erreicht
 *   wurde — es gab daher keinen Zuschlag (Rückgang). Das Limit selbst
 *   wird bewusst NIE genannt (Geschäftsgeheimnis des Einlieferers).
 *   Lädt zum Nachverkaufs-Kontakt ein (einfach auf die Mail antworten).
 *
 * Versand: FinalizeAuctionAction (automatische Abwicklung bei
 * Auktionsende), nur wenn mindestens ein Gebot vorlag.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Mail;

use App\Models\AuctionBid;
use App\Models\AuctionLot;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AuctionNotAwardedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly AuctionLot $lot,
        public readonly AuctionBid $highestBid,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Auktion beendet — leider kein Zuschlag (Los '.$this->lot->lot_code.')',
        );
    }

    public function content(): Content
    {
        $lot = $this->lot;

        return new Content(
            view: 'emails.auction-not-awarded',
            with: [
                'bid' => $this->highestBid,
                'lot' => $lot,
                'auction' => $lot->auction,
                'watch' => $lot->watch,
                'tenantName' => (string) tenant('name'),
            ],
        );
    }
}
