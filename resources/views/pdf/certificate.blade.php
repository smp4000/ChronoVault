{{--
=============================================================================
PDF: Wert-Zertifikat für EINE Uhr (dompdf) — Versicherungs-Zertifikat-Stil
=============================================================================
Dünner Wrapper: Markup und Styles kommen aus pdf/partials/certificate
bzw. certificate-styles (gemeinsam mit der Versicherungsmappe genutzt).
Seite 1: Titelbild + Kenndaten; Seite 2: Foto-Dokumentation.
Erwartet: $cert (watch/certNumber/issuedFor), $generatedAt, $seller.
=============================================================================
--}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #18181b; padding: 44px 52px; }
        @include('pdf.partials.certificate-styles')
        .footer { position: fixed; bottom: 20px; left: 52px; right: 52px; font-size: 7pt; color: #a1a1aa; border-top: 0.5pt solid #e4e4e7; padding-top: 6px; text-align: center; line-height: 1.6; }
    </style>
</head>
<body>

    @include('pdf.partials.certificate')

    <div class="footer">
        {{ $seller['name'] }}@if (! empty($seller['street'])) · {{ $seller['street'] }} · {{ $seller['postal_code'] }} {{ $seller['city'] }}@endif
        @if (! empty($seller['tax_number'])) · Steuernummer {{ $seller['tax_number'] }} @endif
        @if (! empty($seller['vat_id'])) · USt-IdNr. {{ $seller['vat_id'] }} @endif
        <br>Zertifikat {{ $cert['certNumber'] }} · maschinell erstellt mit ChronoVault
    </div>

</body>
</html>
