{{--
=============================================================================
PDF: Bestands- und Wertübersicht für Versicherungen (dompdf)
=============================================================================
Je Uhr ein Dokumentations-Block nach Versicherungs-Checkliste: mehrere
Fotos (Perspektiven), Stammdaten, Kaufdaten, Wert mit Quelle, Zertifikat,
Zubehör, Besonderheiten, Beleg-Nachweis.
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
        .subtitle { color: #71717a; font-size: 9pt; margin-bottom: 14px; }
        .watch { border: 0.75pt solid #d4d4d8; border-radius: 8px; padding: 14px 16px; margin-top: 14px; page-break-inside: avoid; }
        .watch-head { width: 100%; border-collapse: collapse; }
        .watch-name { font-size: 11pt; font-weight: bold; }
        .tag { font-size: 7pt; font-weight: bold; color: #b45309; }
        .value-box { text-align: right; white-space: nowrap; }
        .value-box .amount { font-size: 12pt; font-weight: bold; color: #1e40af; }
        .value-box .source { font-size: 7pt; color: #a1a1aa; }
        .photos { margin-top: 10px; }
        .photos td { padding: 0 6px 0 0; text-align: center; vertical-align: top; }
        .photos img { width: 100%; max-width: 78px; height: auto; border-radius: 4px; border: 0.5pt solid #e4e4e7; }
        .photos .plabel { font-size: 6pt; color: #a1a1aa; margin-top: 1px; }
        table.facts { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.facts td { font-size: 8.5pt; padding: 3px 10px 3px 0; vertical-align: top; }
        table.facts .k { color: #71717a; width: 17%; white-space: nowrap; }
        table.facts .v { width: 33%; }
        table.totals { width: 55%; margin-left: 45%; margin-top: 14px; border-collapse: collapse; }
        table.totals .grand td { border-top: 1pt solid #18181b; font-weight: bold; font-size: 11pt; padding-top: 7px; }
        .note { margin-top: 16px; font-size: 7.5pt; color: #71717a; background: #f4f4f5; padding: 9px 11px; border-radius: 4px; line-height: 1.6; }
        .footer { position: fixed; bottom: 20px; left: 44px; right: 44px; font-size: 7pt; color: #a1a1aa; border-top: 0.5pt solid #e4e4e7; padding-top: 6px; }
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

    @foreach ($report['rows'] as $row)
        <div class="watch">
            <table class="watch-head">
                <tr>
                    <td>
                        <span class="watch-name">{{ $row['name'] }}</span>
                        @if ($row['isConsignment'])
                            <span class="tag">· Kommission (Fremdeigentum)</span>
                        @endif
                        @if ($row['isPrivate'])
                            <span class="tag" style="color: #1e40af;">· Eigentum (Sammlung)</span>
                        @endif
                    </td>
                    <td class="value-box">
                        <div class="amount">{{ $eur($row['value']) }}</div>
                        @if ($row['valueSource'])
                            <div class="source">Wiederbeschaffung · {{ $row['valueSource'] }}</div>
                        @endif
                    </td>
                </tr>
            </table>

            {{-- Foto-Dokumentation: alle Perspektiven --}}
            @if ($row['photos'] !== [])
                <table class="photos" width="100%">
                    <tr>
                        @foreach ($row['photos'] as $photo)
                            <td width="{{ (int) (100 / max(count($row['photos']), 4)) }}%">
                                <img src="data:image/jpeg;base64,{{ $photo['data'] }}">
                                @if ($photo['label'])
                                    <div class="plabel">{{ $photo['label'] }}</div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                </table>
            @endif

            {{-- Checklisten-Daten --}}
            <table class="facts">
                <tr>
                    <td class="k">Hersteller</td><td class="v">{{ $row['brand'] }}</td>
                    <td class="k">Kaufdatum</td><td class="v">{{ $row['purchaseDate'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="k">Modell</td><td class="v">{{ $row['model'] }}</td>
                    @if ($report['includePurchase'])
                        <td class="k">Kaufpreis</td><td class="v">{{ $eur($row['purchasePrice']) }}</td>
                    @else
                        <td class="k">Beleg</td><td class="v">{{ $row['hasReceipt'] ? 'Ankaufsbeleg im System hinterlegt' : '—' }}</td>
                    @endif
                </tr>
                <tr>
                    <td class="k">Referenz</td><td class="v">{{ $row['reference'] ?? '—' }}</td>
                    @if ($report['includePurchase'])
                        <td class="k">Beleg</td><td class="v">{{ $row['hasReceipt'] ? 'Ankaufsbeleg im System hinterlegt' : '—' }}</td>
                    @else
                        <td class="k">Zertifikat</td><td class="v">{{ $row['certificate'] }}</td>
                    @endif
                </tr>
                <tr>
                    <td class="k">Seriennummer</td><td class="v"><strong>{{ $row['serial'] ?? '—' }}</strong></td>
                    @if ($report['includePurchase'])
                        <td class="k">Zertifikat</td><td class="v">{{ $row['certificate'] }}</td>
                    @else
                        <td class="k">Zubehör</td><td class="v">{{ $row['scope'] ?? '—' }}</td>
                    @endif
                </tr>
                <tr>
                    <td class="k">Baujahr</td><td class="v">{{ $row['year'] ?? '—' }}</td>
                    @if ($report['includePurchase'])
                        <td class="k">Zubehör</td><td class="v">{{ $row['scope'] ?? '—' }}</td>
                    @else
                        <td class="k">Besonderheiten</td><td class="v">{{ $row['specials'] ?? '—' }}</td>
                    @endif
                </tr>
                <tr>
                    <td class="k">Zustand</td><td class="v">{{ $row['condition'] ?? '—' }}</td>
                    @if ($report['includePurchase'])
                        <td class="k">Besonderheiten</td><td class="v">{{ $row['specials'] ?? '—' }}</td>
                    @else
                        <td class="k"></td><td class="v"></td>
                    @endif
                </tr>
            </table>
        </div>
    @endforeach

    <table class="totals">
        <tr class="grand">
            <td>Gesamter Wiederbeschaffungswert</td>
            <td style="text-align: right;">{{ $eur($report['total']) }}</td>
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
