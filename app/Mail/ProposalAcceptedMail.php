<?php

/**
 * =========================================================================
 * ProposalAcceptedMail — Preisvorschlag angenommen: Zuschlag (Shop)
 * =========================================================================
 *
 * Zweck:
 *   Bestätigt dem Kunden, dass sein Preisvorschlag angenommen wurde —
 *   damit ist der verbindliche Kauf zum Wunschpreis zustande gekommen.
 *   Enthält Zahlungsinformationen mit GiroCode-QR sowie Rechnung
 *   (ZUGFeRD-E-Rechnung) und Kaufvertrag als PDF-Anhänge. Fehlt die
 *   Lieferadresse, bittet die Mail um Antwort mit der Anschrift.
 *
 * Versand: AcceptPriceProposalAction (Panel-Aktion „Annehmen").
 * =========================================================================
 */

declare(strict_types=1);

namespace App\Mail;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\PriceProposal;
use App\Models\Watch;
use App\Services\InvoiceService;
use App\Support\GiroCode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProposalAcceptedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Watch $watch,
        public readonly Contact $buyer,
        public readonly PriceProposal $proposal,
        public readonly ?Invoice $invoice = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ihr Preisvorschlag wurde angenommen: '.$this->watch->fullName(),
        );
    }

    public function content(): Content
    {
        $amount = (float) $this->proposal->proposed_price;
        $remittance = $this->invoice !== null
            ? $this->invoice->invoice_number
            : trim('Kauf '.($this->watch->reference_number ?? $this->watch->model_name).' '.$this->buyer->last_name);

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
            view: 'emails.proposal-accepted',
            with: [
                'watch' => $this->watch,
                'buyer' => $this->buyer,
                'proposal' => $this->proposal,
                'tenantName' => (string) tenant('name'),
                'amount' => $amount,
                'accountHolder' => $accountHolder,
                'iban' => is_string($iban) && $iban !== '' ? $iban : null,
                'bic' => tenant('bank_bic'),
                'remittance' => $remittance,
                'qrPng' => $qrPng,
                'invoiceNumber' => $this->invoice?->invoice_number,
                // Adresse vorhanden? Sonst bittet die Mail um Antwort damit
                'hasAddress' => filled($this->buyer->street) && filled($this->buyer->city),
            ],
        );
    }

    /**
     * Rechnung (ZUGFeRD) + Kaufvertrag als PDF-Anhänge — ein
     * Render-Fehler darf den Versand nie verhindern.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->invoice === null) {
            return [];
        }

        $service = app(InvoiceService::class);

        try {
            $invoicePdf = $service->renderZugferdPdf($this->invoice);
            $contractPdf = $service->renderContractPdf($this->invoice);
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }

        return [
            Attachment::fromData(fn (): string => $invoicePdf, 'Rechnung-'.$this->invoice->invoice_number.'.pdf')
                ->withMime('application/pdf'),
            Attachment::fromData(fn (): string => $contractPdf, 'Kaufvertrag-'.$this->invoice->invoice_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
