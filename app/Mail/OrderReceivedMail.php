<?php

/**
 * =========================================================================
 * OrderReceivedMail — Shop-Bestellung an die Inhaber (intern)
 * =========================================================================
 *
 * Zweck:
 *   Informiert den Betrieb über einen verbindlichen Sofortkauf:
 *   Uhr (wurde automatisch RESERVIERT), Käufer mit Lieferadresse,
 *   erwartete Zahlung (Betrag + Verwendungszweck) und Panel-Link.
 *   Nach Zahlungseingang: Verkauf über die Verkaufen-Action abschließen.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Mail;

use App\Models\Contact;
use App\Models\Watch;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderReceivedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Watch $watch,
        public readonly Contact $buyer,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Neue Shop-Bestellung: '.$this->watch->fullName().' — '.number_format((float) $this->watch->asking_price, 0, ',', '.').' €',
            replyTo: [new Address((string) $this->buyer->email, $this->buyer->displayName())],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-received',
            with: [
                'watch' => $this->watch,
                'buyer' => $this->buyer,
                'tenantName' => (string) tenant('name'),
                'amount' => (float) $this->watch->asking_price,
                'remittance' => trim('Kauf '.($this->watch->reference_number ?? $this->watch->model_name).' '.$this->buyer->last_name),
                'panelUrl' => url('/app/watches/'.$this->watch->getKey().'/edit'),
            ],
        );
    }
}
