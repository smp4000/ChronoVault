<?php

/**
 * =========================================================================
 * WishlistPriceAlertMail — Zielpreis eines Wunschmodells erreicht
 * =========================================================================
 *
 * Zweck:
 *   Informiert den Händler/Sammler, dass der recherchierte Marktwert
 *   eines Wunschmodells auf/unter dem Zielpreis liegt — Zeit zum
 *   Zuschlagen. Mit Markteinschätzung der KI (summary) und Spanne.
 *
 * Versand: ValuateWishlistItemAction (einmalig je Unterschreitung —
 * notified_at verhindert Spam, Re-Arm bei Preisen über Ziel).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Mail;

use App\Models\WishlistItem;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WishlistPriceAlertMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly WishlistItem $item,
        public readonly ?string $summary = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Zielpreis erreicht: '.$this->item->displayName(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.wishlist-alert',
            with: [
                'item' => $this->item,
                'summary' => $this->summary,
                'tenantName' => (string) tenant('name'),
            ],
        );
    }
}
