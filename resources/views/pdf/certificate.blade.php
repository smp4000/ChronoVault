{{--
=============================================================================
PDF: Wert-Zertifikat für eine Uhr (dompdf) — Versicherungs-Zertifikat-Stil
=============================================================================
Aufbau nach Juwelier-Vorbild: zentrierter Kopf, Eigentümer links /
Zertifikatsdaten rechts, Uhr mit Kenndaten + Wert, Foto-Dokumentation,
Bestätigungstext, Ort/Datum/Unterschrift, Fußzeile mit Betriebsdaten.
Erwartet: $watch (Array), $certNumber, $issuedFor, $generatedAt, $seller.
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
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #18181b; padding: 44px 52px; }
        .head { text-align: center; }
        .brand { font-size: 16pt; font-weight: bold; letter-spacing: 4px; text-transform: uppercase; }
        .brand-dot { color: #1e40af; }
        .brand-sub { font-size: 7.5pt; color: #71717a; margin-top: 3px; }
        .cert-title { text-align: center; margin-top: 26px; }
        .cert-title .rule { font-size: 10pt; letter-spacing: 6px; color: #1e40af; font-weight: bold; }
        .cert-title h1 { font-size: 14pt; letter-spacing: 3px; margin-top: 4px; }
        table.meta { width: 100%; border-collapse: collapse; margin-top: 26px; }
        table.meta td { vertical-align: top; font-size: 9pt; }
        .label { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 1px; color: #71717a; }
        .box { border: 0.75pt solid #d4d4d8; border-radius: 8px; padding: 14px 16px; margin-top: 22px; }
        table.item { width: 100%; border-collapse: collapse; }
        table.item td { vertical-align: top; }
        img.hero { width: 150px; height: auto; border-radius: 6px; border: 0.5pt solid #e4e4e7; }
        table.kv { width: 100%; border-collapse: collapse; }
        table.kv td { font-size: 8.5pt; padding: 2.5px 8px 2.5px 0; }
        table.kv .k { color: #71717a; width: 42%; white-space: nowrap; }
        .value-row td { border-top: 0.75pt solid #18181b; padding-top: 6px !important; font-weight: bold; }
        .value-row .amount { color: #1e40af; font-size: 11pt; }
        .photos { margin-top: 12px; }
        .photos td { padding: 0 5px 0 0; text-align: center; vertical-align: top; }
        .photos img { width: 100%; max-width: 74px; height: auto; border-radius: 4px; border: 0.5pt solid #e4e4e7; }
        .photos .plabel { font-size: 6pt; color: #a1a1aa; margin-top: 1px; }
        .statement { margin-top: 22px; font-size: 8.5pt; line-height: 1.7; color: #3f3f46; }
        table.sign { width: 100%; border-collapse: collapse; margin-top: 44px; }
        table.sign td { width: 33%; padding: 0 14px; text-align: center; font-size: 8pt; color: #71717a; }
        table.sign .line { border-top: 0.75pt solid #18181b; padding-top: 5px; }
        .footer { position: fixed; bottom: 20px; left: 52px; right: 52px; font-size: 7pt; color: #a1a1aa; border-top: 0.5pt solid #e4e4e7; padding-top: 6px; text-align: center; line-height: 1.6; }
    </style>
</head>
<body>

    <div class="head">
        <div class="brand"><span class="brand-dot">&#9679;</span> {{ $seller['name'] }}</div>
        @if (! empty($seller['street']))
            <div class="brand-sub">{{ $seller['street'] }} · {{ $seller['postal_code'] }} {{ $seller['city'] }}</div>
        @endif
    </div>

    <div class="cert-title">
        <div class="rule">&mdash; ZERTIFIKAT &mdash;</div>
        <h1>WERT- UND BESTANDSZERTIFIKAT</h1>
    </div>

    <table class="meta">
        <tr>
            <td width="55%">
                <div class="label">Eigentümer(in) / Ausgestellt für</div>
                <div style="margin-top: 5px; line-height: 1.6; white-space: pre-line;">{{ $issuedFor ?? $seller['name'] }}</div>
            </td>
            <td width="45%">
                <table class="kv">
                    <tr><td class="k">Zertifikat-Nr.</td><td><strong>{{ $certNumber }}</strong></td></tr>
                    <tr><td class="k">Ausstellungsdatum</td><td>{{ $generatedAt->format('d.m.Y') }}</td></tr>
                    @if ($watch['valuedAt'])
                        <tr><td class="k">Wert-Stand</td><td>{{ \Illuminate\Support\Carbon::parse($watch['valuedAt'])->format('d.m.Y') }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <div class="box">
        <div class="label" style="margin-bottom: 10px;">Zertifizierte Uhr</div>

        <table class="item">
            <tr>
                @if ($watch['photos'] !== [])
                    <td width="165">
                        <img class="hero" src="data:image/jpeg;base64,{{ $watch['photos'][0]['data'] }}">
                    </td>
                @endif
                <td style="padding-left: 6px;">
                    <table class="kv">
                        <tr><td class="k">Hersteller</td><td><strong>{{ $watch['brand'] }}</strong></td></tr>
                        <tr><td class="k">Modell</td><td><strong>{{ $watch['model'] }}</strong></td></tr>
                        <tr><td class="k">Referenznummer</td><td>{{ $watch['reference'] ?? '—' }}</td></tr>
                        <tr><td class="k">Seriennummer / Individual-Nr.</td><td><strong>{{ $watch['serial'] ?? '—' }}</strong></td></tr>
                        @if ($watch['stockNumber'])
                            <tr><td class="k">Artikel-Nr.</td><td>{{ $watch['stockNumber'] }}</td></tr>
                        @endif
                        @if ($watch['caliber'])
                            <tr><td class="k">Kaliber</td><td>{{ $watch['caliber'] }}</td></tr>
                        @endif
                        @if ($watch['material'])
                            <tr><td class="k">Gehäusematerial</td><td>{{ $watch['material'] }}</td></tr>
                        @endif
                        @if ($watch['diameter'])
                            <tr><td class="k">Durchmesser</td><td>{{ $watch['diameter'] }}</td></tr>
                        @endif
                        @if ($watch['year'])
                            <tr><td class="k">Baujahr</td><td>{{ $watch['year'] }}</td></tr>
                        @endif
                        @if ($watch['condition'])
                            <tr><td class="k">Zustand</td><td>{{ $watch['condition'] }}</td></tr>
                        @endif
                        @if ($watch['scope'])
                            <tr><td class="k">Lieferumfang / Zubehör</td><td>{{ $watch['scope'] }}</td></tr>
                        @endif
                        @if ($watch['specials'])
                            <tr><td class="k">Besonderheiten</td><td>{{ $watch['specials'] }}</td></tr>
                        @endif
                        @if ($watch['purchaseDate'])
                            <tr><td class="k">Kaufdatum</td><td>{{ $watch['purchaseDate'] }}</td></tr>
                        @endif
                        @if ($watch['purchasePrice'] !== null)
                            <tr><td class="k">Brutto-Kaufpreis</td><td>{{ $eur($watch['purchasePrice']) }}</td></tr>
                        @endif
                        <tr class="value-row">
                            <td class="k">Versicherungswert</td>
                            <td><span class="amount">{{ $eur($watch['value']) }}</span>
                                @if ($watch['valueSource'])
                                    <span style="font-size: 7pt; color: #a1a1aa;">({{ $watch['valueSource'] }})</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- Foto-Dokumentation: weitere Perspektiven --}}
        @if (count($watch['photos']) > 1)
            <table class="photos" width="100%">
                <tr>
                    @foreach (array_slice($watch['photos'], 1) as $photo)
                        <td width="{{ (int) (100 / max(count($watch['photos']) - 1, 4)) }}%">
                            <img src="data:image/jpeg;base64,{{ $photo['data'] }}">
                            @if ($photo['label'])
                                <div class="plabel">{{ $photo['label'] }}</div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            </table>
        @endif
    </div>

    <div class="statement">
        Hiermit wird bestätigt, dass die vorstehend beschriebene Uhr am Ausstellungsdatum
        im Bestand von {{ $seller['name'] }} dokumentiert und in Augenschein genommen wurde.
        Der ausgewiesene Versicherungswert entspricht dem aktuellen Wiederbeschaffungswert
        auf Basis von Marktwert-Recherchen; liegt der Marktwert unter dem Kaufpreis, gilt
        der Kaufpreis zuzüglich Alterszuschlag (1.&nbsp;Jahr +10&nbsp;%, 2.&nbsp;Jahr
        +15&nbsp;%, ab dem 3.&nbsp;Jahr +20&nbsp;%). Dieses Zertifikat dient der Dokumentation
        gegenüber Versicherungen und ersetzt kein Sachverständigengutachten.
    </div>

    <table class="sign">
        <tr>
            <td><div class="line">Ort</div></td>
            <td><div class="line">Datum</div></td>
            <td><div class="line">Unterschrift {{ $seller['name'] }}</div></td>
        </tr>
        <tr>
            <td style="padding-top: 4px;">{{ $seller['city'] ?? '' }}</td>
            <td style="padding-top: 4px;">{{ $generatedAt->format('d.m.Y') }}</td>
            <td></td>
        </tr>
    </table>

    <div class="footer">
        {{ $seller['name'] }}@if (! empty($seller['street'])) · {{ $seller['street'] }} · {{ $seller['postal_code'] }} {{ $seller['city'] }}@endif
        @if (! empty($seller['tax_number'])) · Steuernummer {{ $seller['tax_number'] }} @endif
        @if (! empty($seller['vat_id'])) · USt-IdNr. {{ $seller['vat_id'] }} @endif
        <br>Zertifikat {{ $certNumber }} · maschinell erstellt mit ChronoVault
    </div>

</body>
</html>
