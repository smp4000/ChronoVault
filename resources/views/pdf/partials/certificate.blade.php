{{--
=============================================================================
Partial: EIN Wert-Zertifikat (dompdf) — genutzt vom Einzel-Zertifikat
(pdf/certificate) und von der Versicherungsmappe (pdf/inventory).
Seite 1: Kopf, Eigentümer, Kenndaten mit Titelbild und Wert, Bestätigung,
Unterschrift. Seite 2 (nur bei mehr als einem Foto): Foto-Dokumentation.
Erwartet: $cert (watch/certNumber/issuedFor), $seller, $generatedAt.
Die zugehörigen Styles liefert pdf/partials/certificate-styles.
=============================================================================
--}}
@php
    $certEur = fn ($value): string => $value !== null
        ? number_format((float) $value, 2, ',', '.').' €'
        : '—';
    $certWatch = $cert['watch'];
    $certExtraPhotos = array_slice($certWatch['photos'], 1);
@endphp

<div class="cert-head">
    <div class="cert-brand"><span class="cert-brand-dot">&#9679;</span> {{ $seller['name'] }}</div>
    @if (! empty($seller['street']))
        <div class="cert-brand-sub">{{ $seller['street'] }} · {{ $seller['postal_code'] }} {{ $seller['city'] }}</div>
    @endif
</div>

<div class="cert-title">
    <div class="cert-rule">&mdash; ZERTIFIKAT &mdash;</div>
    <h1>WERT- UND BESTANDSZERTIFIKAT</h1>
</div>

<table class="cert-meta">
    <tr>
        <td width="55%">
            <div class="cert-label">Eigentümer(in) / Ausgestellt für</div>
            <div style="margin-top: 5px; line-height: 1.6; white-space: pre-line;">{{ $cert['issuedFor'] ?? $seller['name'] }}</div>
        </td>
        <td width="45%">
            <table class="cert-kv">
                <tr><td class="k">Zertifikat-Nr.</td><td><strong>{{ $cert['certNumber'] }}</strong></td></tr>
                <tr><td class="k">Ausstellungsdatum</td><td>{{ $generatedAt->format('d.m.Y') }}</td></tr>
                @if ($certWatch['valuedAt'])
                    <tr><td class="k">Wert-Stand</td><td>{{ \Illuminate\Support\Carbon::parse($certWatch['valuedAt'])->format('d.m.Y') }}</td></tr>
                @endif
            </table>
        </td>
    </tr>
</table>

<div class="cert-box">
    <div class="cert-label" style="margin-bottom: 10px;">Zertifizierte Uhr</div>

    <table class="cert-item">
        <tr>
            @if ($certWatch['photos'] !== [])
                <td width="185">
                    <img class="cert-hero" src="data:image/jpeg;base64,{{ $certWatch['photos'][0]['data'] }}">
                    @if ($certWatch['photos'][0]['label'])
                        <div style="font-size: 6.5pt; color: #a1a1aa; text-align: center; margin-top: 2px;">{{ $certWatch['photos'][0]['label'] }}</div>
                    @endif
                </td>
            @endif
            <td style="padding-left: 6px;">
                <table class="cert-kv">
                    <tr><td class="k">Hersteller</td><td><strong>{{ $certWatch['brand'] }}</strong></td></tr>
                    <tr><td class="k">Modell</td><td><strong>{{ $certWatch['model'] }}</strong></td></tr>
                    <tr><td class="k">Referenznummer</td><td>{{ $certWatch['reference'] ?? '—' }}</td></tr>
                    <tr><td class="k">Seriennummer / Individual-Nr.</td><td><strong>{{ $certWatch['serial'] ?? '—' }}</strong></td></tr>
                    @if ($certWatch['stockNumber'])
                        <tr><td class="k">Artikel-Nr.</td><td>{{ $certWatch['stockNumber'] }}</td></tr>
                    @endif
                    @if ($certWatch['caliber'])
                        <tr><td class="k">Kaliber</td><td>{{ $certWatch['caliber'] }}</td></tr>
                    @endif
                    @if ($certWatch['material'])
                        <tr><td class="k">Gehäusematerial</td><td>{{ $certWatch['material'] }}</td></tr>
                    @endif
                    @if ($certWatch['diameter'])
                        <tr><td class="k">Durchmesser</td><td>{{ $certWatch['diameter'] }}</td></tr>
                    @endif
                    @if ($certWatch['year'])
                        <tr><td class="k">Baujahr</td><td>{{ $certWatch['year'] }}</td></tr>
                    @endif
                    @if ($certWatch['condition'])
                        <tr><td class="k">Zustand</td><td>{{ $certWatch['condition'] }}</td></tr>
                    @endif
                    @if ($certWatch['scope'])
                        <tr><td class="k">Lieferumfang / Zubehör</td><td>{{ $certWatch['scope'] }}</td></tr>
                    @endif
                    @if ($certWatch['specials'])
                        <tr><td class="k">Besonderheiten</td><td>{{ $certWatch['specials'] }}</td></tr>
                    @endif
                    @if ($certWatch['purchaseDate'])
                        <tr><td class="k">Kaufdatum</td><td>{{ $certWatch['purchaseDate'] }}</td></tr>
                    @endif
                    @if ($certWatch['purchasePrice'] !== null)
                        <tr><td class="k">Brutto-Kaufpreis</td><td>{{ $certEur($certWatch['purchasePrice']) }}</td></tr>
                    @endif
                    <tr class="cert-value-row">
                        <td class="k">Versicherungswert</td>
                        <td><span class="amount">{{ $certEur($certWatch['value']) }}</span>
                            @if ($certWatch['valueSource'])
                                <span style="font-size: 7pt; color: #a1a1aa;">({{ $certWatch['valueSource'] }})</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>

<div class="cert-statement">
    Hiermit wird bestätigt, dass die vorstehend beschriebene Uhr am Ausstellungsdatum
    im Bestand von {{ $seller['name'] }} dokumentiert und in Augenschein genommen wurde.
    Der ausgewiesene Versicherungswert entspricht dem aktuellen Wiederbeschaffungswert
    auf Basis von Marktwert-Recherchen; liegt der Marktwert unter dem Kaufpreis, gilt
    der Kaufpreis zuzüglich Alterszuschlag (1.&nbsp;Jahr +10&nbsp;%, 2.&nbsp;Jahr
    +15&nbsp;%, ab dem 3.&nbsp;Jahr +20&nbsp;%). Dieses Zertifikat dient der Dokumentation
    gegenüber Versicherungen und ersetzt kein Sachverständigengutachten.
</div>

<table class="cert-sign">
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

{{-- Seite 2: Foto-Dokumentation (alle weiteren Perspektiven, groß) --}}
@if ($certExtraPhotos !== [])
    <div class="cert-photo-page">
        <div class="cert-head">
            <div class="cert-brand" style="font-size: 12pt;"><span class="cert-brand-dot">&#9679;</span> {{ $seller['name'] }}</div>
        </div>
        <div class="cert-title" style="margin-top: 18px;">
            <div class="cert-rule" style="font-size: 8.5pt;">&mdash; FOTO-DOKUMENTATION &mdash;</div>
            <h1 style="font-size: 11pt;">{{ $certWatch['name'] }}</h1>
            <div style="font-size: 7.5pt; color: #71717a; margin-top: 3px;">Zertifikat {{ $cert['certNumber'] }} · Stand {{ $generatedAt->format('d.m.Y') }}</div>
        </div>

        @foreach (array_chunk($certExtraPhotos, 2) as $pair)
            <table class="cert-photo-grid">
                <tr>
                    @foreach ($pair as $photo)
                        <td>
                            <img src="data:image/jpeg;base64,{{ $photo['data'] }}">
                            @if ($photo['label'])
                                <div class="plabel">{{ $photo['label'] }}</div>
                            @endif
                        </td>
                    @endforeach
                    @if (count($pair) === 1)
                        <td></td>
                    @endif
                </tr>
            </table>
        @endforeach
    </div>
@endif
