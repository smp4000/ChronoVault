{{--
=============================================================================
E-Mail: Kaufbestätigung Shop-Sofortkauf — Zahlungsinfos + GiroCode
=============================================================================
Erwartet: $watch, $buyer, $tenantName, $amount, $accountHolder, $iban,
$bic, $remittance, $qrPng (binär|null).
=============================================================================
--}}
@php
    $formatEur = fn ($value): string => number_format((float) $value, 2, ',', '.').' €';
    // Foto inline einbetten (cid) — extern verlinkte Bilder blockieren viele Mailprogramme
    $photo = $watch->firstPhotoForEmail();
    $photoSrc = ($photo !== null && isset($message))
        ? $message->embedData($photo['data'], $photo['name'], $photo['mime'])
        : null;
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kaufbestätigung</title>
</head>
<body style="margin:0; padding:0; background-color:#f5f5f4; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">
        Vielen Dank für Ihren Kauf — die Zahlungsinformationen für {{ $watch->fullName() }} finden Sie hier.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f5f5f4; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%;">

                    <tr>
                        <td style="padding:0 8px 20px 8px;" align="center">
                            <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background-color:#1e40af;"></span>
                            <span style="font-size:14px; font-weight:600; letter-spacing:4px; text-transform:uppercase; color:#18181b; vertical-align:middle; padding-left:10px;">
                                {{ $tenantName }}
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#ffffff; border-radius:24px; border:1px solid #e7e5e4; overflow:hidden;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">

                                {{-- Blaue Kopfzeile --}}
                                <tr>
                                    <td style="background-color:#1e40af; padding:36px 40px;" align="center">
                                        <p style="margin:0; font-size:12px; font-weight:600; letter-spacing:3px; text-transform:uppercase; color:#bfdbfe;">
                                            Kaufbestätigung
                                        </p>
                                        <p style="margin:12px 0 0 0; font-size:40px; font-weight:700; color:#ffffff; letter-spacing:-1px;">
                                            {{ $formatEur($amount) }}
                                        </p>
                                        <p style="margin:8px 0 0 0; font-size:14px; color:#bfdbfe;">
                                            {{ $watch->fullName() }}
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:36px 40px 0 40px;">
                                        <p style="margin:0; font-size:15px; line-height:1.6; color:#3f3f46;">
                                            Guten Tag {{ trim(($buyer->first_name ? $buyer->first_name.' ' : '').$buyer->last_name) }},
                                        </p>
                                        <p style="margin:14px 0 0 0; font-size:15px; line-height:1.6; color:#3f3f46;">
                                            vielen Dank für Ihren Kauf — hiermit bestätigen wir Ihren
                                            <strong>verbindlichen Kauf</strong> zum Preis von
                                            {{ $formatEur($amount) }} (inkl. MwSt., zzgl. Versand).
                                            Die Uhr ist für Sie reserviert; der Versand erfolgt nach
                                            Zahlungseingang an Ihre angegebene Adresse.
                                        </p>
                                    </td>
                                </tr>

                                {{-- Uhr-Kachel --}}
                                <tr>
                                    <td style="padding:24px 40px 0 40px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e7e5e4; border-radius:16px;">
                                            <tr>
                                                @if ($photoSrc)
                                                    <td width="120" style="padding:16px 0 16px 16px;" valign="top">
                                                        <img src="{{ $photoSrc }}" alt="{{ $watch->fullName() }}" width="104" height="104"
                                                             style="display:block; width:104px; height:104px; object-fit:cover; border-radius:12px; border:1px solid #e7e5e4;">
                                                    </td>
                                                @endif
                                                <td style="padding:16px;" valign="middle">
                                                    <p style="margin:0; font-size:11px; font-weight:600; letter-spacing:2px; text-transform:uppercase; color:#1e40af;">
                                                        {{ $watch->brand->name }}
                                                    </p>
                                                    <p style="margin:4px 0 0 0; font-size:17px; font-weight:600; color:#18181b;">
                                                        {{ $watch->model_name }}
                                                    </p>
                                                    @if ($watch->reference_number)
                                                        <p style="margin:4px 0 0 0; font-size:13px; color:#71717a;">
                                                            Referenz {{ $watch->reference_number }}
                                                        </p>
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                {{-- Lieferadresse --}}
                                <tr>
                                    <td style="padding:24px 40px 0 40px;">
                                        <p style="margin:0; font-size:12px; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:#1e40af;">
                                            Lieferadresse
                                        </p>
                                        <p style="margin:8px 0 0 0; font-size:14px; line-height:1.7; color:#3f3f46;">
                                            {{ trim(($buyer->first_name ? $buyer->first_name.' ' : '').$buyer->last_name) }}<br>
                                            {{ $buyer->street }}<br>
                                            {{ $buyer->postal_code }} {{ $buyer->city }}, {{ $buyer->country }}
                                        </p>
                                    </td>
                                </tr>

                                {{-- Zahlung --}}
                                <tr>
                                    <td style="padding:28px 40px 36px 40px;">
                                        <p style="margin:0; font-size:12px; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:#1e40af;">
                                            Zahlung per Überweisung
                                        </p>

                                        @if ($iban)
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:14px; background-color:#f8fafc; border-radius:16px;">
                                                <tr>
                                                    <td style="padding:20px 22px;" valign="top">
                                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                                            <tr>
                                                                <td style="padding:7px 0; font-size:13px; color:#71717a;">Empfänger</td>
                                                                <td style="padding:7px 0; font-size:13px; font-weight:600; color:#18181b;" align="right">{{ $accountHolder }}</td>
                                                            </tr>
                                                            <tr>
                                                                <td style="padding:7px 0; font-size:13px; color:#71717a;">IBAN</td>
                                                                <td style="padding:7px 0; font-size:13px; font-weight:600; color:#18181b;" align="right">{{ trim(chunk_split($iban, 4, ' ')) }}</td>
                                                            </tr>
                                                            @if ($bic)
                                                                <tr>
                                                                    <td style="padding:7px 0; font-size:13px; color:#71717a;">BIC</td>
                                                                    <td style="padding:7px 0; font-size:13px; font-weight:600; color:#18181b;" align="right">{{ $bic }}</td>
                                                                </tr>
                                                            @endif
                                                            <tr>
                                                                <td style="padding:7px 0; font-size:13px; color:#71717a;">Betrag</td>
                                                                <td style="padding:7px 0; font-size:13px; font-weight:600; color:#1e40af;" align="right">{{ $formatEur($amount) }}</td>
                                                            </tr>
                                                            <tr>
                                                                <td style="padding:7px 0; font-size:13px; color:#71717a;">Verwendungszweck</td>
                                                                <td style="padding:7px 0; font-size:13px; font-weight:600; color:#18181b;" align="right">{{ $remittance }}</td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                    @if ($qrPng && isset($message))
                                                        <td width="150" style="padding:16px 16px 16px 0;" valign="middle" align="center">
                                                            <img src="{{ $message->embedData($qrPng, 'girocode.png', 'image/png') }}"
                                                                 alt="GiroCode — mit Banking-App scannen" width="130" height="130"
                                                                 style="display:block; width:130px; height:130px; border:1px solid #e7e5e4; border-radius:12px; background:#ffffff;">
                                                            <p style="margin:8px 0 0 0; font-size:10px; color:#71717a;">
                                                                Mit der Banking-App<br>scannen &amp; überweisen
                                                            </p>
                                                        </td>
                                                    @endif
                                                </tr>
                                            </table>
                                            <p style="margin:12px 0 0 0; font-size:12px; color:#a1a1aa;">
                                                Bitte überweisen Sie den Betrag innerhalb von 7 Tagen.
                                                Der Versand erfolgt umgehend nach Zahlungseingang.
                                            </p>
                                            @if ($invoiceNumber)
                                                <p style="margin:10px 0 0 0; font-size:13px; line-height:1.6; color:#3f3f46;">
                                                    Ihre Rechnung <strong>{{ $invoiceNumber }}</strong> und den
                                                    Kaufvertrag finden Sie als PDF im Anhang — die Rechnung
                                                    inklusive E-Rechnungs-Daten (ZUGFeRD) und GiroCode.
                                                </p>
                                            @endif
                                        @else
                                            <p style="margin:10px 0 0 0; font-size:14px; line-height:1.6; color:#3f3f46;">
                                                Die Zahlungsinformationen erhalten Sie in einer
                                                separaten Nachricht von uns.
                                            </p>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px 8px 0 8px;" align="center">
                            <p style="margin:0; font-size:12px; line-height:1.7; color:#a1a1aa;">
                                Fragen zu Ihrem Kauf? Antworten Sie einfach auf diese E-Mail.
                            </p>
                            <p style="margin:14px 0 0 0; font-size:11px; color:#d4d4d8;">
                                &copy; {{ now()->year }} {{ $tenantName }} · Bereitgestellt über ChronoVault
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
