<?php

/**
 * =========================================================================
 * WatchInquiryMail — Kaufanfrage aus dem Shop an den Händler (intern)
 * =========================================================================
 *
 * Zweck:
 *   Leitet eine Anfrage von der Shop-Detailseite an die Inhaber des
 *   Betriebs weiter — mit Kundendaten, Nachricht, Uhr-Kachel und
 *   Direktlink ins Panel. Reply-To ist der Kunde: Der Händler
 *   antwortet einfach auf die Mail.
 *
 * Versand: synchron aus dem ShopController (siehe BidConfirmationMail).
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Mail;

use App\Models\Watch;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WatchInquiryMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array{name: string, email: string, phone?: string|null, message: string}  $inquiry
     */
    public function __construct(
        public readonly Watch $watch,
        public readonly array $inquiry,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Shop-Anfrage: '.$this->watch->fullName(),
            replyTo: [new Address($this->inquiry['email'], $this->inquiry['name'])],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.watch-inquiry',
            with: [
                'watch' => $this->watch,
                'inquiry' => $this->inquiry,
                'tenantName' => (string) tenant('name'),
                'panelUrl' => url('/app/watches/'.$this->watch->getKey().'/edit'),
            ],
        );
    }
}
