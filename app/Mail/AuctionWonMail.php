<?php

/**
 * =========================================================================
 * AuctionWonMail — Zuschlag-Benachrichtigung an den Gewinner (Modul 8b)
 * =========================================================================
 *
 * Zweck:
 *   Gratuliert dem Höchstbietenden zum Zuschlag und liefert alles für
 *   die Abwicklung:
 *   - Zahlungsinformationen (Kontoinhaber/IBAN/BIC aus den
 *     Betriebsdaten, Betrag, Verwendungszweck)
 *   - GiroCode-QR (EPC069-12) als cid-Anhang — mit der Banking-App
 *     scannen, Überweisung ist vorausgefüllt
 *   - Signierter Link (14 Tage gültig) zur Erfassung der Lieferdaten
 *
 * Ohne hinterlegte Bankverbindung entfallen Zahlungsblock/QR — die
 * Mail weist dann auf separate Zahlungsinformationen hin.
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Mail;

use App\Models\AuctionBid;
use App\Models\AuctionLot;
use App\Support\GiroCode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class AuctionWonMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly AuctionLot $lot,
        public readonly AuctionBid $winningBid,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Zuschlag! Los '.$this->lot->lot_number.' — '.$this->lot->auction->title,
        );
    }

    public function content(): Content
    {
        $lot = $this->lot;
        $auction = $lot->auction;
        $amount = (float) $lot->hammer_price;
        $remittance = 'Los '.$lot->lot_number.' '.$auction->title;

        $iban = tenant('bank_iban');
        $accountHolder = tenant('bank_account_holder') ?? (string) tenant('name');

        // GiroCode nur mit hinterlegter IBAN (Betriebsdaten-Seite)
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
            view: 'emails.auction-won',
            with: [
                'lot' => $lot,
                'auction' => $auction,
                'watch' => $lot->watch,
                'bid' => $this->winningBid,
                'tenantName' => (string) tenant('name'),
                'amount' => $amount,
                'accountHolder' => $accountHolder,
                'iban' => is_string($iban) && $iban !== '' ? $iban : null,
                'bic' => tenant('bank_bic'),
                'remittance' => $remittance,
                'qrPng' => $qrPng,
                // Signierter Link zur Datenerfassung — 14 Tage gültig
                'dataUrl' => URL::temporarySignedRoute(
                    'shop.auctions.winner',
                    now()->addDays(14),
                    ['auction' => $auction->getKey(), 'lot' => $lot->getKey()],
                ),
            ],
        );
    }
}
