<?php

/**
 * =========================================================================
 * DealerReplyMail — Freitext-Antwort des Händlers an den Kunden (Shop)
 * =========================================================================
 *
 * Zweck:
 *   Versendet die im Antworten-Dialog der Preisvorschläge verfasste
 *   (optional KI-entworfene) Nachricht als sauber gestaltete Mail an
 *   den Kunden — mit Uhr-Kachel. Reply-To ist die Benachrichtigungs-
 *   Adresse des Betriebs, die Kundenantwort landet direkt beim Händler.
 *
 * Versand: SendProposalReplyAction (Panel-Aktion „Antworten").
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

class DealerReplyMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly PriceProposal $proposal,
        public readonly string $mailSubject,
        public readonly string $messageText,
    ) {}

    public function envelope(): Envelope
    {
        $replyTo = [];
        $notificationEmail = tenant('notification_email');

        if (is_string($notificationEmail) && $notificationEmail !== '') {
            $replyTo[] = new Address($notificationEmail, (string) tenant('name'));
        }

        return new Envelope(
            subject: $this->mailSubject,
            replyTo: $replyTo,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.dealer-reply',
            with: [
                'proposal' => $this->proposal,
                'watch' => $this->proposal->watch,
                'tenantName' => (string) tenant('name'),
                'messageText' => $this->messageText,
            ],
        );
    }
}
