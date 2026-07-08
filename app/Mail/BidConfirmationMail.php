<?php

/**
 * =========================================================================
 * BidConfirmationMail — Gebotsbestätigung für Online-Bieter (Modul 8b)
 * =========================================================================
 *
 * Zweck:
 *   Bestätigt dem Bieter sein Gebot unmittelbar nach der Abgabe und
 *   weist ausdrücklich auf die VERBINDLICHKEIT hin. Premium-Design
 *   (weiße Karte, Blau-Akzent) in resources/views/emails/bid-confirmation.
 *
 * Versand:
 *   Synchron aus der PlaceBidAction (lokal läuft kein Queue-Worker,
 *   ADR-002) — Fehler beim Versand dürfen das Gebot NIE verhindern
 *   (try/catch in der Action). In Produktion mit Horizon auf
 *   ShouldQueue umstellen.
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

class BidConfirmationMail extends Mailable
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
            subject: 'Ihr Gebot über '.number_format((float) $this->bid->amount, 0, ',', '.').' € — Los '.$lot->lot_code.', '.$lot->auction->title,
        );
    }

    public function content(): Content
    {
        $lot = $this->bid->lot;

        return new Content(
            view: 'emails.bid-confirmation',
            with: [
                'bid' => $this->bid,
                'lot' => $lot,
                'auction' => $lot->auction,
                'watch' => $lot->watch,
                'tenantName' => (string) tenant('name'),
                'lotUrl' => route('shop.auctions.lot', [$lot->auction, $lot]),
            ],
        );
    }
}
