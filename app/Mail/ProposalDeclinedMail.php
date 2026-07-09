<?php

/**
 * =========================================================================
 * ProposalDeclinedMail — „Schade"-Mail nach abgelehntem Gegenangebot
 * =========================================================================
 *
 * Zweck:
 *   Freundlicher Abschluss, wenn der Kunde das Gegenangebot über den
 *   Ablehnen-Button ausschlägt: kurzes Bedauern, Einladung, die
 *   Kollektion im Blick zu behalten. Reply-To ist die
 *   Benachrichtigungs-Adresse des Betriebs.
 *
 * Versand: ShopController::proposalDecision (Ablehnen-Link der Mail).
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

class ProposalDeclinedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly PriceProposal $proposal,
    ) {}

    public function envelope(): Envelope
    {
        $replyTo = [];
        $notificationEmail = tenant('notification_email');

        if (is_string($notificationEmail) && $notificationEmail !== '') {
            $replyTo[] = new Address($notificationEmail, (string) tenant('name'));
        }

        return new Envelope(
            subject: 'Schade — vielleicht beim nächsten Mal',
            replyTo: $replyTo,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.proposal-declined',
            with: [
                'proposal' => $this->proposal,
                'watch' => $this->proposal->watch,
                'tenantName' => (string) tenant('name'),
                'shopUrl' => route('shop.index'),
            ],
        );
    }
}
