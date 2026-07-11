{{--
=============================================================================
PDF: Bestands- und Wertübersicht für Versicherungen (dompdf)
=============================================================================
Erwartet: $report (rows/total/count/generatedAt/includePurchase/
includeConsignment), $seller (name/street/postal_code/city).
=============================================================================
--}}
@php
    $eur = fn ($value): string => $value !== null
        ? number_format((float) $value, 2, ',', '.').' €'
        : '—';
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #18181b; padding: 40px 44px; }
        .brand { font-size: 13pt; font-weight: bold; letter-spacing: 3px; text-transform: uppercase; }
        .brand-dot { color: #1e40af; }
        h1 { font-size: 14pt; margin: 24px 0 2px 0; }
        .subtitle { color: #71717a; font-size: 9pt; margin-bottom: 16px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.items th { text-align: left; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 1px; color: #71717a; border-bottom: 1pt solid #18181b; padding: 5px 6px 5px 0; }
        table.items td { padding: 8px 6px 8px 0; border-bottom: 0.5pt solid #e4e4e7; vertical-align: top; }
        .details { color: #52525b; font-size: 8pt; margin-top: 2px; line-height: 1.5; }
        .num { text-align: right; white-space: nowrap; }
        .source { color: #a1a1aa; font-size: 7pt; }
        .consignment { color: #b45309; font-size: 7.5pt; font-weight: bold; }
        table.totals { width: 50%; margin-left: 50%; margin-top: 10px; border-collapse: collapse; }
        table.totals td { padding: 4px 0; font-size: 9.5pt; }
        table.totals .grand td { border-top: 1pt solid #18181b; font-weight: bold; font-size: 11pt; padding-top: 6px; }
        .note { margin-top: 16px; font-size: 7.5pt; color: #71717a; background: #f4f4f5; padding: 9px 11px; border-radius: 4px; line-height: 1.6; }
        .footer { position: fixed; bottom: 20px; left: 44px; right: 44px; font-size: 7pt; color: #a1a1aa; border-top: 0.5pt solid #e4e4e7; padding-top: 6px; }
        img.thumb { width: 46px; height: auto; border-radius: 4px; }
    </style>
</head>
<body>

    <div class="brand"><span class="brand-dot">&#9679;</span> {{ $seller['name'] }}</div>
    @if (! empty($seller['street']))
        <div style="font-size: 8pt; color: #71717a; margin-top: 3px;">
            {{ $seller['street'] }} · {{ $seller['postal_code'] }} {{ $seller['city'] }}
        </div>
    @endif

    <h1>Bestands- und Wertübersicht</h1>
    <div class="subtitle">
        Stichtag {{ $report['generatedAt']->format('d.m.Y, H:i') }} Uhr ·
        {{ $report['count'] }} {{ $report['count'] === 1 ? 'Uhr' : 'Uhren' }} ·
        Wiederbeschaffungswerte für Versicherungszwecke
        @if ($report['includeConsignment']) · inkl. Kommissionsware (Fremdeigentum, gekennzeichnet) @endif
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 9%;">Foto</th>
                <th>Uhr</th>
                <th style="width: 14%;">Seriennummer</th>
                @if ($report['includePurchase'])
                    <th class="num" style="width: 13%;">Einkaufspreis</th>
                @endif
                <th class="num" style="width: 16%;">Wiederbeschaffung</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report['rows'] as $row)
                <tr>
                    <td>
                        @if ($row['thumb'])
                            <img class="thumb" src="data:image/jpeg;base64,{{ $row['thumb'] }}">
                        @endif
                    </td>
                    <td>
                        <strong>{{ $row['name'] }}</strong>
                        @if ($row['isConsignment'])
                            <span class="consignment">· Kommission</span>
                        @endif
                        <div class="details">
                            @if ($row['reference']) Referenz {{ $row['reference'] }} · @endif
                            @if ($row['year']) Baujahr {{ $row['year'] }} · @endif
                            @if ($row['condition']) Zustand: {{ $row['condition'] }} @endif
                            @if ($row['scope']) · Lieferumfang: {{ $row['scope'] }} @endif
                        </div>
                    </td>
                    <td>{{ $row['serial'] ?? '—' }}</td>
                    @if ($report['includePurchase'])
                        <td class="num">{{ $eur($row['purchasePrice']) }}</td>
                    @endif
                    <td class="num">
                        <strong>{{ $eur($row['value']) }}</strong>
                        @if ($row['valueSource'])
                            <div class="source">{{ $row['valueSource'] }}</div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr class="grand">
            <td>Gesamter Wiederbeschaffungswert</td>
            <td class="num" style="text-align: right;">{{ $eur($report['total']) }}</td>
        </tr>
    </table>

    <div class="note">
        Die ausgewiesenen Wiederbeschaffungswerte basieren auf aktuellen Marktwert-Schätzungen
        (KI-gestützte Wertermittlung), ersatzweise auf Angebots- bzw. Einkaufspreisen — die
        Quelle ist je Position vermerkt. Liegt der Marktwert unter dem Einkaufspreis, gilt als
        Untergrenze der Einkaufspreis zzgl. Alterszuschlag (1.&nbsp;Jahr +10&nbsp;%,
        2.&nbsp;Jahr +15&nbsp;%, ab dem 3.&nbsp;Jahr +20&nbsp;%). Angaben ohne Gewähr; für
        verbindliche Bewertungen empfiehlt sich ein Sachverständigengutachten.
    </div>

    <div class="footer">
        {{ $seller['name'] }} · Bestands- und Wertübersicht vom {{ $report['generatedAt']->format('d.m.Y') }} ·
        maschinell erstellt mit ChronoVault
    </div>

</body>
</html>
