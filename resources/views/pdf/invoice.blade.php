{{--
=============================================================================
PDF: Rechnung (dompdf) — Pflichtangaben nach § 14 UStG
=============================================================================
Rendert AUSSCHLIESSLICH aus dem Invoice-Snapshot (seller/buyer/line) —
nie aus Live-Daten. Dient auch als Sichtteil der ZUGFeRD-E-Rechnung.
Erwartet: $invoice.
=============================================================================
--}}
@php
    $seller = $invoice->seller;
    $buyer = $invoice->buyer;
    $line = $invoice->line;
    $eur = fn ($value): string => number_format((float) $value, 2, ',', '.').' €';

    $taxNote = match ($invoice->tax_mode) {
        'regular' => null,
        'small_business' => 'Kein Ausweis der Umsatzsteuer gemäß § 19 UStG (Kleinunternehmerregelung).',
        default => 'Gebrauchtgegenstände/Sonderregelung — Differenzbesteuerung nach § 25a UStG. Die Umsatzsteuer wird nicht gesondert ausgewiesen.',
    };
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #18181b; padding: 48px 56px; }
        .brand { font-size: 13pt; font-weight: bold; letter-spacing: 3px; text-transform: uppercase; }
        .brand-dot { color: #1e40af; }
        .sender-line { font-size: 7pt; color: #71717a; margin-top: 28px; border-bottom: 0.5pt solid #d4d4d8; padding-bottom: 2px; width: 60%; }
        .address { margin-top: 8px; line-height: 1.5; }
        .meta { margin-top: 6px; width: 100%; }
        .meta td { padding: 1px 0; font-size: 9pt; }
        .meta .label { color: #71717a; padding-right: 14px; }
        h1 { font-size: 15pt; margin: 34px 0 4px 0; }
        .subtitle { color: #71717a; font-size: 9pt; margin-bottom: 18px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.items th { text-align: left; font-size: 8pt; text-transform: uppercase; letter-spacing: 1px; color: #71717a; border-bottom: 1pt solid #18181b; padding: 6px 8px 6px 0; }
        table.items td { padding: 10px 8px 10px 0; border-bottom: 0.5pt solid #e4e4e7; vertical-align: top; }
        .details { color: #52525b; font-size: 8.5pt; margin-top: 3px; line-height: 1.5; }
        .num { text-align: right; white-space: nowrap; }
        table.totals { width: 45%; margin-left: 55%; margin-top: 12px; border-collapse: collapse; }
        table.totals td { padding: 4px 0; font-size: 9.5pt; }
        table.totals .grand td { border-top: 1pt solid #18181b; font-weight: bold; font-size: 11pt; padding-top: 7px; }
        .tax-note { margin-top: 16px; font-size: 8.5pt; color: #3f3f46; background: #f4f4f5; padding: 10px 12px; border-radius: 4px; }
        .payment { margin-top: 22px; font-size: 9pt; line-height: 1.6; }
        .payment .head { font-size: 8pt; text-transform: uppercase; letter-spacing: 1px; color: #1e40af; font-weight: bold; margin-bottom: 4px; }
        .footer { position: fixed; bottom: 24px; left: 56px; right: 56px; font-size: 7.5pt; color: #a1a1aa; border-top: 0.5pt solid #e4e4e7; padding-top: 8px; line-height: 1.6; }
    </style>
</head>
<body>

    <div class="brand"><span class="brand-dot">&#9679;</span> {{ $seller['name'] }}</div>

    <div class="sender-line">
        {{ $seller['name'] }} · {{ $seller['street'] }} · {{ $seller['postal_code'] }} {{ $seller['city'] }}
    </div>

    <table width="100%" style="margin-top: 4px;">
        <tr>
            <td width="55%" valign="top">
                <div class="address">
                    <strong>{{ $buyer['name'] }}</strong><br>
                    {{ $buyer['street'] }}<br>
                    {{ $buyer['postal_code'] }} {{ $buyer['city'] }}<br>
                    {{ $buyer['country'] }}
                </div>
            </td>
            <td width="45%" valign="top">
                <table class="meta">
                    <tr><td class="label">Rechnungsnummer</td><td><strong>{{ $invoice->invoice_number }}</strong></td></tr>
                    <tr><td class="label">Rechnungsdatum</td><td>{{ $invoice->issued_at->format('d.m.Y') }}</td></tr>
                    @if ($invoice->delivery_date)
                        <tr><td class="label">Liefer-/Leistungsdatum</td><td>{{ $invoice->delivery_date->format('d.m.Y') }}</td></tr>
                    @endif
                    @if (! empty($seller['tax_number']))
                        <tr><td class="label">Steuernummer</td><td>{{ $seller['tax_number'] }}</td></tr>
                    @endif
                    @if (! empty($seller['vat_id']))
                        <tr><td class="label">USt-IdNr.</td><td>{{ $seller['vat_id'] }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <h1>Rechnung {{ $invoice->invoice_number }}</h1>
    <div class="subtitle">Vielen Dank für Ihren Einkauf.</div>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 6%;">Pos.</th>
                <th>Bezeichnung</th>
                <th class="num" style="width: 10%;">Menge</th>
                <th class="num" style="width: 18%;">Betrag</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>
                    <strong>{{ $line['description'] }}</strong>
                    @if (! empty($line['details']))
                        <div class="details">
                            @foreach ($line['details'] as $detail)
                                {{ $detail }}@if (! $loop->last)<br>@endif
                            @endforeach
                        </div>
                    @endif
                </td>
                <td class="num">1 Stück</td>
                <td class="num">{{ $eur($invoice->tax_mode === 'regular' ? $invoice->net_amount : $invoice->total_amount) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        @if ($invoice->tax_mode === 'regular')
            <tr><td>Nettobetrag</td><td class="num" style="text-align:right;">{{ $eur($invoice->net_amount) }}</td></tr>
            <tr><td>Umsatzsteuer 19 %</td><td class="num" style="text-align:right;">{{ $eur($invoice->tax_amount) }}</td></tr>
        @endif
        <tr class="grand"><td>Rechnungsbetrag</td><td style="text-align:right;">{{ $eur($invoice->total_amount) }}</td></tr>
    </table>

    @if ($taxNote)
        <div class="tax-note">{{ $taxNote }}</div>
    @endif

    @if (! empty($seller['bank_iban']))
        <div class="payment">
            <div class="head">Zahlung per Überweisung</div>
            <table width="100%" style="border-collapse: collapse;">
                <tr>
                    <td valign="top" style="font-size: 9pt; line-height: 1.6; padding-right: 16px;">
                        Bitte überweisen Sie den Rechnungsbetrag innerhalb von 7 Tagen ohne Abzug:<br>
                        <strong>{{ $seller['bank_account_holder'] ?? $seller['name'] }}</strong> ·
                        IBAN {{ trim(chunk_split((string) $seller['bank_iban'], 4, ' ')) }}
                        @if (! empty($seller['bank_bic'])) · BIC {{ $seller['bank_bic'] }} @endif<br>
                        Verwendungszweck: <strong>{{ $invoice->invoice_number }}</strong>
                    </td>
                    @if (! empty($giroQr ?? null))
                        <td width="110" valign="top" style="text-align: center;">
                            {{-- GiroCode: Überweisung per Banking-App-Scan vorausgefüllt --}}
                            <img src="data:image/png;base64,{{ $giroQr }}" width="96" height="96" style="width: 96px; height: 96px;">
                            <div style="font-size: 6.5pt; color: #71717a; margin-top: 2px;">GiroCode — mit der<br>Banking-App scannen</div>
                        </td>
                    @endif
                </tr>
            </table>
        </div>
    @endif

    <div class="footer">
        {{ $seller['name'] }} · {{ $seller['street'] }} · {{ $seller['postal_code'] }} {{ $seller['city'] }}
        @if (! empty($seller['tax_number'])) · Steuernummer {{ $seller['tax_number'] }} @endif
        @if (! empty($seller['vat_id'])) · USt-IdNr. {{ $seller['vat_id'] }} @endif
        <br>Dieses Dokument wurde maschinell erstellt und ist ohne Unterschrift gültig.
    </div>

</body>
</html>
