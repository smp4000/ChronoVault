<?php

/**
 * =========================================================================
 * OrderConfirmationMail — Kaufbestätigung Shop-Sofortkauf (an Käufer)
 * =========================================================================
 *
 * Zweck:
 *   Bestätigt den VERBINDLICHEN Kauf zum Festpreis und liefert die
 *   Zahlungsinformationen (Betriebsdaten-Bankverbindung, GiroCode-QR
 *   wie bei der Auktions-Zuschlag-Mail). Verwendungszweck: Referenz
 *   der Uhr + Nachname — eindeutig zuordenbar.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Mail;

use App\Models\Contact;
use App\Models\Watch;
use App\Support\GiroCode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable
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
            subject: 'Kaufbestätigung: '.$this->watch->fullName(),
        );
    }

    public function content(): Content
    {
        $amount = (float) $this->watch->asking_price;
        $remittance = trim('Kauf '.($this->watch->reference_number ?? $this->watch->model_name).' '.$this->buyer->last_name);

        $iban = tenant('bank_iban');
        $accountHolder = tenant('bank_account_holder') ?? (string) tenant('name');

        $qrPng = null;

        if (is_string($iban) && $iban !== '') {
            $qrPng = GiroCode::png(
                accountHolder: $accountHolder,
                iban: $iban,
                bic: tenant('bank_bic'),
                amount: $amount,
                remittance: $remittance,
            );
        }

        return new Content(
            view: 'emails.order-confirmation',
            with: [
                'watch' => $this->watch,
                'buyer' => $this->buyer,
                'tenantName' => (string) tenant('name'),
                'amount' => $amount,
                'accountHolder' => $accountHolder,
                'iban' => is_string($iban) && $iban !== '' ? $iban : null,
                'bic' => tenant('bank_bic'),
                'remittance' => $remittance,
                'qrPng' => $qrPng,
            ],
        );
    }
}
