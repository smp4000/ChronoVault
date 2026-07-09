<?php

/**
 * =========================================================================
 * CounterOfferMail — Gegenangebot des Händlers zum Preisvorschlag
 * =========================================================================
 *
 * Zweck:
 *   Teilt dem Kunden das Gegenangebot mit: gegliederter Preis (Angebot
 *   + Versand = Gesamt), frei formulierter Händler-Text sowie
 *   Annehmen-/Ablehnen-Buttons (signierte Links, 14 Tage gültig).
 *   Annahme wickelt den Kauf automatisch ab, Ablehnung schließt den
 *   Vorgang. Reply-To ist die Benachrichtigungs-Adresse des Betriebs.
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
use Illuminate\Support\Facades\URL;

class CounterOfferMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly PriceProposal $proposal,
        public readonly ?string $introText = null,
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

        // Signierte Entscheidungs-Links (14 Tage): Annahme wickelt den
        // Kauf ab, Ablehnung schließt den Vorgang — beides ohne Login.
        $decisionUrl = fn (string $decision): string => URL::temporarySignedRoute(
            'shop.proposal.decision',
            now()->addDays(14),
            ['proposal' => $this->proposal->getKey(), 'decision' => $decision],
        );

        return new Content(
            view: 'emails.counter-offer',
            with: [
                'proposal' => $this->proposal,
                'watch' => $watch,
                'tenantName' => (string) tenant('name'),
                'introText' => $this->introText,
                'watchUrl' => $watch !== null ? route('shop.show', $watch) : route('shop.index'),
                'acceptUrl' => $decisionUrl('annehmen'),
                'declineUrl' => $decisionUrl('ablehnen'),
            ],
        );
    }
}
