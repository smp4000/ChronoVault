<?php

/**
 * =========================================================================
 * ReserveNotMetMail — Gebot erfasst, Limit noch nicht erreicht (8b)
 * =========================================================================
 *
 * Zweck:
 *   Ersetzt die normale Gebotsbestätigung, wenn das Gebot zwar
 *   Höchstgebot ist, aber UNTER dem Limit des Einlieferers liegt:
 *   Ohne höheres Gebot gibt es am Auktionsende KEINEN Zuschlag.
 *   Die Mail nennt das Limit bewusst NICHT (Geschäftsgeheimnis des
 *   Einlieferers) — nur den Hinweis + Mindestgebot (+10 €-Schritte).
 *
 * Versand: synchron aus der PlaceBidAction (siehe BidConfirmationMail).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Mail;

use App\Models\AuctionBid;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReserveNotMetMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly AuctionBid $bid,
    ) {}

    public function envelope(): Envelope
    {
        $lot = $this->bid->lot;

        return new Envelope(
            subject: 'Ihr Gebot ist erfasst — das Limit ist noch nicht erreicht (Los '.$lot->lot_code.')',
        );
    }

    public function content(): Content
    {
        $lot = $this->bid->lot;

        return new Content(
            view: 'emails.reserve-not-met',
            with: [
                'bid' => $this->bid,
                'lot' => $lot,
                'auction' => $lot->auction,
                'watch' => $lot->watch,
                'tenantName' => (string) tenant('name'),
                'lotUrl' => route('shop.auctions.lot', [$lot->auction, $lot]),
                'minimumNextBid' => $lot->minimumNextBid(),
                'bidIncrement' => $lot->bidIncrement(),
            ],
        );
    }
}
