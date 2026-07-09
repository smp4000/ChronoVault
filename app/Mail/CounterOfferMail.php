<?php

/**
 * =========================================================================
 * CounterOfferMail — Gegenangebot des Händlers zum Preisvorschlag
 * =========================================================================
 *
 * Zweck:
 *   Teilt dem Kunden das Gegenangebot mit (Wunschpreis vs. Angebot des
 *   Händlers, optionale Nachricht) und verlinkt zur Uhr. Reply-To ist
 *   die Benachrichtigungs-Adresse des Betriebs — die Antwort des
 *   Kunden landet direkt beim Händler.
 *
 * Versand: CounterPriceProposalAction (Panel-Aktion „Gegenangebot").
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Mail;

use App\Models\PriceProposal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CounterOfferMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly PriceProposal $proposal,
        public readonly ?string $dealerMessage = null,
    ) {}

    public function envelope(): Envelope
    {
        $watch = $this->proposal->watch;

        $replyTo = [];
        $notificationEmail = tenant('notification_email');

        if (is_string($notificationEmail) && $notificationEmail !== '') {
            $replyTo[] = new Address($notificationEmail, (string) tenant('name'));
        }

        return new Envelope(
            subject: 'Unser Angebot für Sie: '.($watch?->fullName() ?? 'Ihre Uhr'),
            replyTo: $replyTo,
        );
    }

    public function content(): Content
    {
        $watch = $this->proposal->watch;

        return new Content(
            view: 'emails.counter-offer',
            with: [
                'proposal' => $this->proposal,
                'watch' => $watch,
                'tenantName' => (string) tenant('name'),
                'dealerMessage' => $this->dealerMessage,
                'watchUrl' => $watch !== null ? route('shop.show', $watch) : route('shop.index'),
            ],
        );
    }
}
