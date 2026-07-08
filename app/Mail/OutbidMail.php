<?php

/**
 * =========================================================================
 * OutbidMail — Benachrichtigung „Sie wurden überboten" (Modul 8b)
 * =========================================================================
 *
 * Zweck:
 *   Informiert den BISHERIGEN Höchstbietenden, sobald ein höheres Gebot
 *   eingeht — mit neuem Gebotsstand, Mindestgebot und CTA zum
 *   Nachbieten. Es wird bewusst NUR der abgelöste Höchstbietende
 *   benachrichtigt (alle früheren Bieter wurden bereits informiert,
 *   als sie selbst überboten wurden — kein Mail-Spam).
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

class OutbidMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        /** Das abgelöste Gebot (Empfänger der Mail). */
        public readonly AuctionBid $outbid,
        /** Das neue Höchstgebot. */
        public readonly AuctionBid $newHighest,
    ) {}

    public function envelope(): Envelope
    {
        $lot = $this->outbid->lot;

        return new Envelope(
            subject: 'Sie wurden überboten — Los '.$lot->lot_number.', '.$lot->auction->title,
        );
    }

    public function content(): Content
    {
        $lot = $this->outbid->lot;

        return new Content(
            view: 'emails.outbid',
            with: [
                'outbid' => $this->outbid,
                'newHighest' => $this->newHighest,
                'lot' => $lot,
                'auction' => $lot->auction,
                'watch' => $lot->watch,
                'tenantName' => (string) tenant('name'),
                'lotUrl' => route('shop.auctions.lot', [$lot->auction, $lot]),
                'minimumNextBid' => $lot->minimumNextBid(),
            ],
        );
    }
}
