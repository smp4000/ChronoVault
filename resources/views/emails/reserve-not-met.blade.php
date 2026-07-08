{{--
=============================================================================
E-Mail: Gebot erfasst, Limit noch nicht erreicht (Modul 8b)
=============================================================================
WICHTIG: Das Limit (reserve_price) wird NIE genannt — nur der Hinweis,
dass es noch nicht erreicht ist, plus Mindestgebot (10-€-Schritte).
Erwartet: $bid, $lot, $auction, $watch, $tenantName, $lotUrl, $minimumNextBid.
=============================================================================
--}}
@php
    $formatEur = fn ($value): string => number_format((float) $value, 0, ',', '.').' €';
    $photoUrl = $watch->firstPhotoUrl();
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Limit noch nicht erreicht</title>
</head>
<body style="margin:0; padding:0; background-color:#f5f5f4; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">
        Ihr Gebot über {{ $formatEur($bid->amount) }} ist erfasst — für den Zuschlag muss das Limit des Einlieferers erreicht werden.
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

                                {{-- Bernstein-Kopfzeile: Achtung, aber kein Alarm --}}
                                <tr>
                                    <td style="background-color:#b45309; padding:36px 40px;" align="center">
                                        <p style="margin:0; font-size:12px; font-weight:600; letter-spacing:3px; text-transform:uppercase; color:#fde68a;">
                                            Limit noch nicht erreicht
                                        </p>
                                        <p style="margin:12px 0 0 0; font-size:40px; font-weight:700; color:#ffffff; letter-spacing:-1px;">
                                            {{ $formatEur($bid->amount) }}
                                        </p>
                                        <p style="margin:8px 0 0 0; font-size:14px; color:#fde68a;">
                                            Ihr Gebot · Los {{ $lot->lot_code }} · {{ $auction->title }}
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:36px 40px 0 40px;">
                                        <p style="margin:0; font-size:15px; line-height:1.6; color:#3f3f46;">
                                            Guten Tag {{ $bid->bidder_name }},
                                        </p>
                                        <p style="margin:14px 0 0 0; font-size:15px; line-height:1.6; color:#3f3f46;">
                                            Ihr Gebot über <strong>{{ $formatEur($bid->amount) }}</strong> ist
                                            erfasst und aktuell das höchste. Es liegt jedoch noch unter dem
                                            Limit des Einlieferers — wird das Limit bis zum Auktionsende
                                            nicht erreicht, kann <strong>kein Zuschlag</strong> erteilt werden.
                                        </p>
                                        <p style="margin:14px 0 0 0; font-size:15px; line-height:1.6; color:#3f3f46;">
                                            Sie können Ihr Gebot jederzeit erhöhen — um mindestens
                                            {{ $formatEur($bidIncrement) }}, der Betrag ist frei wählbar.
                                        </p>
                                    </td>
                                </tr>

                                {{-- Uhr-Kachel --}}
                                <tr>
                                    <td style="padding:24px 40px 0 40px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e7e5e4; border-radius:16px;">
                                            <tr>
                                                @if ($photoUrl)
                                                    <td width="120" style="padding:16px 0 16px 16px;" valign="top">
                                                        <img src="{{ $photoUrl }}" alt="{{ $watch->fullName() }}" width="104" height="104"
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

                                {{-- Eckdaten --}}
                                <tr>
                                    <td style="padding:24px 40px 0 40px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding:10px 0; border-bottom:1px solid #f4f4f5; font-size:14px; color:#71717a;">Ihr Gebot (Höchstgebot)</td>
                                                <td style="padding:10px 0; border-bottom:1px solid #f4f4f5; font-size:14px; font-weight:600; color:#18181b;" align="right">{{ $formatEur($bid->amount) }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:10px 0; border-bottom:1px solid #f4f4f5; font-size:14px; color:#71717a;">Nächstes Mindestgebot</td>
                                                <td style="padding:10px 0; border-bottom:1px solid #f4f4f5; font-size:14px; font-weight:600; color:#b45309;" align="right">{{ $formatEur($minimumNextBid) }}</td>
                                            </tr>
                                            @if ($auction->ends_at)
                                                <tr>
                                                    <td style="padding:10px 0; font-size:14px; color:#71717a;">Auktionsende</td>
                                                    <td style="padding:10px 0; font-size:14px; font-weight:600; color:#18181b;" align="right">{{ $auction->ends_at->format('d.m.Y \u\m H:i') }} Uhr</td>
                                                </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>

                                {{-- CTA --}}
                                <tr>
                                    <td style="padding:28px 40px 36px 40px;" align="center">
                                        <a href="{{ $lotUrl }}"
                                           style="display:inline-block; background-color:#1e40af; color:#ffffff; font-size:14px; font-weight:600; text-decoration:none; padding:13px 34px; border-radius:999px;">
                                            Gebot jetzt erhöhen
                                        </a>
                                        <p style="margin:14px 0 0 0; font-size:12px; color:#a1a1aa;">
                                            Ihr Gebot bleibt verbindlich, bis es überboten wird oder die Auktion endet.
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
