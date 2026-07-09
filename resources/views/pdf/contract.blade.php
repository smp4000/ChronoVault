{{--
=============================================================================
PDF: Kaufvertrag über eine gebrauchte Uhr (dompdf)
=============================================================================
Rendert aus dem Invoice-Snapshot (gleiche Datenbasis wie die Rechnung).
Übliche Klauseln des Gebrauchtuhrenhandels; Gewährleistung nach
gesetzlichen Regeln (B2C: 1 Jahr bei Gebrauchtwaren vereinbar).
Erwartet: $invoice.
=============================================================================
--}}
@php
    $seller = $invoice->seller;
    $buyer = $invoice->buyer;
    $line = $invoice->line;
    $eur = fn ($value): string => number_format((float) $value, 2, ',', '.').' €';
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #18181b; padding: 48px 56px; line-height: 1.55; }
        .brand { font-size: 13pt; font-weight: bold; letter-spacing: 3px; text-transform: uppercase; margin-bottom: 26px; }
        .brand-dot { color: #1e40af; }
        h1 { font-size: 15pt; margin-bottom: 2px; }
        .subtitle { color: #71717a; font-size: 9pt; margin-bottom: 20px; }
        .parties { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .parties td { width: 50%; vertical-align: top; padding: 10px 14px; border: 0.5pt solid #d4d4d8; }
        .parties .role { font-size: 8pt; text-transform: uppercase; letter-spacing: 1px; color: #1e40af; font-weight: bold; margin-bottom: 4px; }
        h2 { font-size: 10.5pt; margin: 16px 0 5px 0; }
        p { margin-bottom: 8px; }
        .object { background: #f4f4f5; border-radius: 4px; padding: 10px 12px; margin: 6px 0 10px 0; }
        .object .details { color: #52525b; font-size: 8.5pt; margin-top: 3px; }
        .price { font-size: 12pt; font-weight: bold; }
        .sign { width: 100%; margin-top: 46px; }
        .sign td { width: 50%; padding: 0 20px; }
        .sign .line { border-top: 0.5pt solid #18181b; padding-top: 4px; font-size: 8pt; color: #71717a; }
        .footer { position: fixed; bottom: 24px; left: 56px; right: 56px; font-size: 7.5pt; color: #a1a1aa; border-top: 0.5pt solid #e4e4e7; padding-top: 8px; }
    </style>
</head>
<body>

    <div class="brand"><span class="brand-dot">&#9679;</span> {{ $seller['name'] }}</div>

    <h1>Kaufvertrag über eine gebrauchte Uhr</h1>
    <div class="subtitle">zur Rechnung {{ $invoice->invoice_number }} vom {{ $invoice->issued_at->format('d.m.Y') }}</div>

    <table class="parties">
        <tr>
            <td>
                <div class="role">Verkäufer</div>
                <strong>{{ $seller['name'] }}</strong><br>
                {{ $seller['street'] }}<br>
                {{ $seller['postal_code'] }} {{ $seller['city'] }}
                @if (! empty($seller['vat_id']))<br>USt-IdNr. {{ $seller['vat_id'] }}@elseif (! empty($seller['tax_number']))<br>Steuernummer {{ $seller['tax_number'] }}@endif
            </td>
            <td>
                <div class="role">Käufer</div>
                <strong>{{ $buyer['name'] }}</strong><br>
                {{ $buyer['street'] }}<br>
                {{ $buyer['postal_code'] }} {{ $buyer['city'] }}<br>
                {{ $buyer['country'] }}
            </td>
        </tr>
    </table>

    <h2>§ 1 Kaufgegenstand</h2>
    <p>Der Verkäufer verkauft dem Käufer die folgende gebrauchte Uhr:</p>
    <div class="object">
        <strong>{{ $line['description'] }}</strong>
        @if (! empty($line['details']))
            <div class="details">
                @foreach ($line['details'] as $detail)
                    {{ $detail }}@if (! $loop->last) · @endif
                @endforeach
            </div>
        @endif
    </div>

    <h2>§ 2 Kaufpreis und Zahlung</h2>
    <p>
        Der Kaufpreis beträgt <span class="price">{{ $eur($invoice->total_amount) }}</span>
        @if ($invoice->tax_mode === 'regular')
            (inkl. 19 % USt. = {{ $eur($invoice->tax_amount) }}).
        @elseif ($invoice->tax_mode === 'small_business')
            (kein Ausweis der Umsatzsteuer gemäß § 19 UStG).
        @else
            (Differenzbesteuerung nach § 25a UStG — Gebrauchtgegenstände/Sonderregelung; die Umsatzsteuer wird nicht gesondert ausgewiesen).
        @endif
        Die Zahlung erfolgt per Überweisung innerhalb von 7 Tagen nach Vertragsschluss.
        Die Übergabe bzw. der Versand der Uhr erfolgt nach vollständigem Zahlungseingang.
    </p>

    <h2>§ 3 Zustand, Echtheit und Gewährleistung</h2>
    <p>
        Die Uhr wurde vom Verkäufer geprüft. Der Verkäufer versichert, dass es sich um ein
        echtes Erzeugnis des genannten Herstellers handelt. Der Käufer hatte Gelegenheit,
        Fotos und Beschreibung einzusehen; Gebrauchsspuren entsprechend Alter und Zustand
        sind keine Mängel. Es gelten die gesetzlichen Gewährleistungsrechte; beim Verkauf
        an Verbraucher ist die Verjährungsfrist für gebrauchte Sachen auf ein Jahr verkürzt,
        soweit gesetzlich zulässig.
    </p>

    <h2>§ 4 Eigentumsvorbehalt</h2>
    <p>Die Uhr bleibt bis zur vollständigen Zahlung des Kaufpreises Eigentum des Verkäufers.</p>

    <h2>§ 5 Schlussbestimmungen</h2>
    <p>
        Änderungen und Ergänzungen bedürfen der Textform. Sollte eine Bestimmung unwirksam
        sein, bleibt der Vertrag im Übrigen wirksam. Es gilt deutsches Recht.
    </p>

    <table class="sign">
        <tr>
            <td>
                <br><br>
                <div class="line">Ort, Datum · Unterschrift Verkäufer</div>
            </td>
            <td>
                <br><br>
                <div class="line">Ort, Datum · Unterschrift Käufer</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        {{ $seller['name'] }} · {{ $seller['street'] }} · {{ $seller['postal_code'] }} {{ $seller['city'] }} ·
        Kaufvertrag zur Rechnung {{ $invoice->invoice_number }}
    </div>

</body>
</html>
