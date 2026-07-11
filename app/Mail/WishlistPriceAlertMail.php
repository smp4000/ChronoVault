<?php

/**
 * =========================================================================
 * WishlistPriceAlertMail — Zielpreis eines Wunschmodells erreicht
 * =========================================================================
 *
 * Zweck:
 *   Informiert den Händler/Sammler, dass der recherchierte Marktwert
 *   einer Wunschlisten-Uhr (Status wishlist) auf/unter dem Zielpreis
 *   liegt — Zeit zum Zuschlagen. Mit KI-Markteinschätzung (summary).
 *
 * Versand: RecordValuationAction::handleWishlistAlert (einmalig je
 * Unterschreitung — wishlist_notified_at, Re-Arm bei Preis über Ziel).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Mail;

use App\Models\Watch;
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
        public readonly Watch $watch,
        public readonly ?string $summary = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Zielpreis erreicht: '.$this->watch->fullName(),
        );
    }

    public function content(): Content
    {
        // Spanne aus der jüngsten Bewertung (falls vorhanden)
        $latest = $this->watch->valuations()->latest('valued_at')->first();

        return new Content(
            view: 'emails.wishlist-alert',
            with: [
                'watch' => $this->watch,
                'summary' => $this->summary,
                'valueLow' => $latest?->value_low,
                'valueHigh' => $latest?->value_high,
                'tenantName' => (string) tenant('name'),
            ],
        );
    }
}
