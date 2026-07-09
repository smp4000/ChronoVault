<?php

/**
 * =========================================================================
 * PriceProposalMail — Preisvorschlag aus dem Shop an den Händler (intern)
 * =========================================================================
 *
 * Zweck:
 *   Leitet einen Preisvorschlag von der Shop-Detailseite an die Inhaber
 *   des Betriebs weiter — vorgeschlagener Preis prominent, daneben der
 *   aktuelle Angebotspreis, Kundendaten und optionale Nachricht.
 *   Reply-To ist der Kunde: Der Händler antwortet einfach auf die Mail.
 *
 * Versand: synchron aus dem ShopController (siehe WatchInquiryMail).
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

class PriceProposalMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array{proposed_price: float|int|string, name: string, email: string, message?: string|null}  $proposal
     */
    public function __construct(
        public readonly Watch $watch,
        public readonly array $proposal,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Preisvorschlag: '.number_format((float) $this->proposal['proposed_price'], 0, ',', '.').' € für '.$this->watch->fullName(),
            replyTo: [new Address($this->proposal['email'], $this->proposal['name'])],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.price-proposal',
            with: [
                'watch' => $this->watch,
                'proposal' => $this->proposal,
                'tenantName' => (string) tenant('name'),
                'panelUrl' => url('/app/watches/'.$this->watch->getKey().'/edit'),
            ],
        );
    }
}
