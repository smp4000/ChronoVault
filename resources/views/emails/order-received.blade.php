{{--
=============================================================================
E-Mail: Shop-Bestellung an den Händler (intern)
=============================================================================
Erwartet: $watch, $buyer, $tenantName, $amount, $remittance, $panelUrl.
--}}
@php
    $formatEur = fn ($value): string => number_format((float) $value, 2, ',', '.').' €';
    $photoUrl = $watch->firstPhotoUrl();
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Neue Bestellung</title>
</head>
<body style="margin:0; padding:0; background-color:#f5f5f4; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">
        {{ $buyer->displayName() }} hat {{ $watch->fullName() }} verbindlich gekauft — die Uhr ist reserviert.
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

                                <tr>
                                    <td style="background-color:#047857; padding:28px 40px;" align="center">
                                        <p style="margin:0; font-size:12px; font-weight:600; letter-spacing:3px; text-transform:uppercase; color:#a7f3d0;">
                                            Neue Shop-Bestellung
                                        </p>
                                        <p style="margin:10px 0 0 0; font-size:30px; font-weight:700; color:#ffffff;">
                                            {{ $formatEur($amount) }}
                                        </p>
                                        <p style="margin:8px 0 0 0; font-size:14px; color:#a7f3d0;">
                                            {{ $watch->fullName() }}
                                        </p>
                                    </td>
                                </tr>

                                {{-- Status-Hinweis --}}
                                <tr>
                                    <td style="padding:28px 40px 0 40px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eff6ff; border-radius:16px;">
                                            <tr>
                                                <td style="padding:16px 22px;">
                                                    <p style="margin:0; font-size:13px; line-height:1.6; color:#3f3f46;">
                                                        Die Uhr wurde automatisch auf <strong>Reserviert</strong> gesetzt
                                                        und ist aus dem Shop verschwunden. Erwartete Zahlung:
                                                        <strong>{{ $formatEur($amount) }}</strong> mit Verwendungszweck
                                                        „<strong>{{ $remittance }}</strong>". Nach Zahlungseingang den
                                                        Verkauf über „Verkaufen" abschließen — der Käufer ist bereits
                                                        im Kundenstamm.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                {{-- Käuferdaten --}}
                                <tr>
                                    <td style="padding:24px 40px 0 40px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding:9px 0; border-bottom:1px solid #f4f4f5; font-size:14px; color:#71717a;">Käufer</td>
                                                <td style="padding:9px 0; border-bottom:1px solid #f4f4f5; font-size:14px; font-weight:600; color:#18181b;" align="right">{{ $buyer->displayName() }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:9px 0; border-bottom:1px solid #f4f4f5; font-size:14px; color:#71717a;">E-Mail</td>
                                                <td style="padding:9px 0; border-bottom:1px solid #f4f4f5; font-size:14px; font-weight:600; color:#18181b;" align="right">{{ $buyer->email }}</td>
                                            </tr>
                                            @if ($buyer->phone)
                                                <tr>
                                                    <td style="padding:9px 0; border-bottom:1px solid #f4f4f5; font-size:14px; color:#71717a;">Telefon</td>
                                                    <td style="padding:9px 0; border-bottom:1px solid #f4f4f5; font-size:14px; font-weight:600; color:#18181b;" align="right">{{ $buyer->phone }}</td>
                                                </tr>
                                            @endif
                                            <tr>
                                                <td style="padding:9px 0; font-size:14px; color:#71717a;" valign="top">Lieferadresse</td>
                                                <td style="padding:9px 0; font-size:14px; font-weight:600; color:#18181b;" align="right">
                                                    {{ $buyer->street }}<br>{{ $buyer->postal_code }} {{ $buyer->city }}, {{ $buyer->country }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:28px 40px 36px 40px;" align="center">
                                        <a href="{{ $panelUrl }}"
                                           style="display:inline-block; background-color:#1e40af; color:#ffffff; font-size:14px; font-weight:600; text-decoration:none; padding:13px 34px; border-radius:999px;">
                                            Uhr im Panel öffnen
                                        </a>
                                        <p style="margin:14px 0 0 0; font-size:12px; color:#a1a1aa;">
                                            Antworten auf diese E-Mail gehen direkt an den Käufer.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px 8px 0 8px;" align="center">
                            <p style="margin:0; font-size:11px; color:#d4d4d8;">
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
