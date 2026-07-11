{{--
=============================================================================
PDF: Versicherungsmappe (dompdf)
=============================================================================
Vorne die ÜBERSICHT aller Uhren im Eigentum (kompakte Tabelle mit
Titelbild, Kenndaten und Wiederbeschaffungswert + Gesamtsumme), dahinter
je Uhr das komplette Wert-Zertifikat (pdf/partials/certificate: Seite 1
Titelbild + Kenndaten, Seite 2 Foto-Dokumentation).
Erwartet: $report (rows/total/count/generatedAt/includePurchase/
includeConsignment), $certificates (Array von certificateData-Sets,
leer ohne Zertifikate), $generatedAt, $seller.
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
        table.overview { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.overview th { font-size: 7pt; text-transform: uppercase; letter-spacing: 0.5px; color: #71717a; text-align: left; padding: 5px 8px 5px 0; border-bottom: 0.75pt solid #18181b; }
        table.overview td { font-size: 8pt; padding: 6px 8px 6px 0; border-bottom: 0.5pt solid #e4e4e7; vertical-align: middle; }
        table.overview .num { text-align: right; white-space: nowrap; }
        table.overview img { width: 44px; height: auto; border-radius: 3px; border: 0.5pt solid #e4e4e7; }
        .tag { font-size: 6.5pt; font-weight: bold; color: #b45309; }
        .tag-own { color: #1e40af; }
        .source { font-size: 6.5pt; color: #a1a1aa; }
        table.totals { width: 55%; margin-left: 45%; margin-top: 14px; border-collapse: collapse; }
        table.totals .grand td { border-top: 1pt solid #18181b; font-weight: bold; font-size: 11pt; padding-top: 7px; }
        .note { margin-top: 16px; font-size: 7.5pt; color: #71717a; background: #f4f4f5; padding: 9px 11px; border-radius: 4px; line-height: 1.6; }
        .cert-start { page-break-before: always; }
        @include('pdf.partials.certificate-styles')
        .footer { position: fixed; bottom: 20px; left: 44px; right: 44px; font-size: 7pt; color: #a1a1aa; border-top: 0.5pt solid #e4e4e7; padding-top: 6px; text-align: center; }
    </style>
</head>
<body>

    {{-- ============================== ÜBERSICHT ============================== --}}
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
        @if ($certificates !== []) · Einzel-Zertifikate im Anschluss @endif
    </div>

    <table class="overview">
        <tr>
            <th style="width: 4%;">Nr.</th>
            <th style="width: 9%;">Foto</th>
            <th>Uhr</th>
            <th style="width: 14%;">Referenz</th>
            <th style="width: 14%;">Seriennummer</th>
            <th style="width: 8%;">Baujahr</th>
            @if ($report['includePurchase'])
                <th class="num" style="width: 12%;">Kaufpreis</th>
            @endif
            <th class="num" style="width: 14%;">Wert</th>
        </tr>
        @foreach ($report['rows'] as $row)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>
                    @if ($row['photos'] !== [])
                        <img src="data:image/jpeg;base64,{{ $row['photos'][0]['data'] }}">
                    @endif
                </td>
                <td>
                    <strong>{{ $row['name'] }}</strong>
                    @if ($row['isConsignment'])
                        <br><span class="tag">Kommission (Fremdeigentum)</span>
                    @endif
                    @if ($row['isPrivate'])
                        <br><span class="tag tag-own">Eigentum (Sammlung)</span>
                    @endif
                </td>
                <td>{{ $row['reference'] ?? '—' }}</td>
                <td><strong>{{ $row['serial'] ?? '—' }}</strong></td>
                <td>{{ $row['year'] ?? '—' }}</td>
                @if ($report['includePurchase'])
                    <td class="num">{{ $eur($row['purchasePrice']) }}</td>
                @endif
                <td class="num">
                    <strong>{{ $eur($row['value']) }}</strong>
                    @if ($row['valueSource'])
                        <br><span class="source">{{ $row['valueSource'] }}</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>

    <table class="totals">
        <tr class="grand">
            <td>Gesamter Wiederbeschaffungswert</td>
            <td style="text-align: right; white-space: nowrap;">{{ $eur($report['total']) }}</td>
        </tr>
    </table>

    <div class="note">
        Die ausgewiesenen Wiederbeschaffungswerte basieren auf aktuellen Marktwert-Schätzungen
        (KI-gestützte Wertermittlung), ersatzweise auf Angebots- bzw. Einkaufspreisen — die
        Quelle ist je Position vermerkt. Liegt der Marktwert unter dem Einkaufspreis, gilt als
        Untergrenze der Einkaufspreis zzgl. Alterszuschlag (1.&nbsp;Jahr +10&nbsp;%,
        2.&nbsp;Jahr +15&nbsp;%, ab dem 3.&nbsp;Jahr +20&nbsp;%). Angaben ohne Gewähr; für
        verbindliche Bewertungen empfiehlt sich ein Sachverständigengutachten.
        @if ($certificates !== [])
            Für jede Uhr im Eigentum folgt auf den nächsten Seiten das ausführliche
            Wert-Zertifikat mit Foto-Dokumentation.
        @endif
    </div>

    {{-- ===================== ZERTIFIKATE (je Eigentums-Uhr) ===================== --}}
    @foreach ($certificates as $cert)
        <div class="cert-start"></div>
        @include('pdf.partials.certificate', ['cert' => $cert])
    @endforeach

    <div class="footer">
        {{ $seller['name'] }} · Versicherungs-Dokumentation vom {{ $report['generatedAt']->format('d.m.Y') }} ·
        maschinell erstellt mit ChronoVault
    </div>

</body>
</html>
