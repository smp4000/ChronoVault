{{--
=============================================================================
E-Mail: Zielpreis-Alarm der Wunschliste (an den Händler/Sammler)
=============================================================================
Erwartet: $item (WishlistItem mit brand), $summary (string|null), $tenantName.
=============================================================================
--}}
@php
    $eur = fn ($value): string => $value !== null
        ? number_format((float) $value, 0, ',', '.').' €'
        : '—';
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zielpreis erreicht</title>
</head>
<body style="margin:0; padding:0; background-color:#f5f5f4; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">
        {{ $item->displayName() }} ist auf {{ $eur($item->current_market_value) }} gefallen — Ihr Ziel: {{ $eur($item->target_price) }}.
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

                                {{-- Grüne Kopfzeile: Chance --}}
                                <tr>
                                    <td style="background-color:#047857; padding:36px 40px;" align="center">
                                        <p style="margin:0; font-size:12px; font-weight:600; letter-spacing:3px; text-transform:uppercase; color:#a7f3d0;">
                                            Zielpreis erreicht
                                        </p>
                                        <p style="margin:12px 0 0 0; font-size:40px; font-weight:700; color:#ffffff; letter-spacing:-1px;">
                                            {{ $eur($item->current_market_value) }}
                                        </p>
                                        <p style="margin:8px 0 0 0; font-size:14px; color:#a7f3d0;">
                                            {{ $item->displayName() }} · Ihr Ziel: {{ $eur($item->target_price) }}
                                        </p>
                                    </td>
                                </tr>

                                {{-- Eckdaten --}}
                                <tr>
                                    <td style="padding:32px 40px 0 40px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding:9px 0; border-bottom:1px solid #f4f4f5; font-size:14px; color:#71717a;">Aktueller Marktwert</td>
                                                <td style="padding:9px 0; border-bottom:1px solid #f4f4f5; font-size:14px; font-weight:700; color:#047857;" align="right">{{ $eur($item->current_market_value) }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:9px 0; border-bottom:1px solid #f4f4f5; font-size:14px; color:#71717a;">Ihr Zielpreis</td>
                                                <td style="padding:9px 0; border-bottom:1px solid #f4f4f5; font-size:14px; font-weight:600; color:#18181b;" align="right">{{ $eur($item->target_price) }}</td>
                                            </tr>
                                            @if ($item->value_low !== null || $item->value_high !== null)
                                                <tr>
                                                    <td style="padding:9px 0; font-size:14px; color:#71717a;">Marktspanne</td>
                                                    <td style="padding:9px 0; font-size:14px; font-weight:600; color:#18181b;" align="right">{{ $eur($item->value_low) }} – {{ $eur($item->value_high) }}</td>
                                                </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>

                                {{-- KI-Markteinschätzung --}}
                                @if (filled($summary))
                                    <tr>
                                        <td style="padding:24px 40px 0 40px;">
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc; border-radius:16px;">
                                                <tr>
                                                    <td style="padding:18px 22px;">
                                                        <p style="margin:0; font-size:11px; font-weight:600; letter-spacing:2px; text-transform:uppercase; color:#71717a;">Markteinschätzung</p>
                                                        <p style="margin:8px 0 0 0; font-size:14px; line-height:1.7; color:#3f3f46;">{{ $summary }}</p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                @endif

                                <tr>
                                    <td style="padding:24px 40px 36px 40px;">
                                        <p style="margin:0; font-size:13px; line-height:1.7; color:#71717a;">
                                            Jetzt lohnt sich der Blick auf Chrono24, eBay & Co. — gute Jagd!
                                            Diese Benachrichtigung kommt erst wieder, wenn der Preis über Ihr
                                            Ziel steigt und erneut darunter fällt.
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
