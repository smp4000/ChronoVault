{{--
=============================================================================
E-Mail: Gegenangebot des Händlers zum Preisvorschlag (Shop)
=============================================================================
Erwartet: $proposal, $watch (nullable), $tenantName, $dealerMessage, $watchUrl.
=============================================================================
--}}
@php
    $formatEur = fn ($value): string => number_format((float) $value, 0, ',', '.').' €';
    // Foto inline einbetten (cid) — extern verlinkte Bilder blockieren viele Mailprogramme
    $photo = $watch?->firstPhotoForEmail();
    $photoSrc = ($photo !== null && isset($message))
        ? $message->embedData($photo['data'], $photo['name'], $photo['mime'])
        : null;
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unser Angebot für Sie</title>
</head>
<body style="margin:0; padding:0; background-color:#f5f5f4; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">
        Unser Angebot zu Ihrem Preisvorschlag: {{ $formatEur($proposal->counter_price) }}.
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

                                {{-- Blaue Kopfzeile: unser Angebot --}}
                                <tr>
                                    <td style="background-color:#1e40af; padding:36px 40px;" align="center">
                                        <p style="margin:0; font-size:12px; font-weight:600; letter-spacing:3px; text-transform:uppercase; color:#bfdbfe;">
                                            Unser Angebot für Sie
                                        </p>
                                        <p style="margin:12px 0 0 0; font-size:40px; font-weight:700; color:#ffffff; letter-spacing:-1px;">
                                            {{ $formatEur($proposal->counter_price) }}
                                        </p>
                                        <p style="margin:8px 0 0 0; font-size:14px; color:#bfdbfe;">
                                            {{ $watch?->fullName() ?? 'Ihre Wunschuhr' }} · Ihr Vorschlag: {{ $formatEur($proposal->proposed_price) }}
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:36px 40px 0 40px;">
                                        <p style="margin:0; font-size:15px; line-height:1.6; color:#3f3f46;">
                                            Guten Tag {{ $proposal->name }},
                                        </p>
                                        <p style="margin:14px 0 0 0; font-size:15px; line-height:1.6; color:#3f3f46;">
                                            vielen Dank für Ihren Preisvorschlag über
                                            <strong>{{ $formatEur($proposal->proposed_price) }}</strong>.
                                            Ganz können wir Ihnen dabei leider nicht entgegenkommen —
                                            aber wir machen Ihnen gerne dieses Angebot:
                                            <strong>{{ $formatEur($proposal->counter_price) }}</strong>.
                                        </p>
                                        @if (filled($dealerMessage))
                                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:18px; background-color:#f8fafc; border-radius:16px;">
                                                <tr>
                                                    <td style="padding:18px 22px;">
                                                        <p style="margin:0; font-size:11px; font-weight:600; letter-spacing:2px; text-transform:uppercase; color:#71717a;">Nachricht</p>
                                                        <p style="margin:8px 0 0 0; font-size:14px; line-height:1.7; color:#3f3f46; white-space:pre-line;">{{ $dealerMessage }}</p>
                                                    </td>
                                                </tr>
                                            </table>
                                        @endif
                                    </td>
                                </tr>

                                {{-- Uhr-Kachel --}}
                                @if ($watch)
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
                                @endif

                                {{-- CTA --}}
                                <tr>
                                    <td style="padding:28px 40px 36px 40px;" align="center">
                                        <a href="{{ $watchUrl }}"
                                           style="display:inline-block; background-color:#1e40af; color:#ffffff; font-size:14px; font-weight:600; text-decoration:none; padding:13px 34px; border-radius:999px;">
                                            Uhr ansehen
                                        </a>
                                        <p style="margin:14px 0 0 0; font-size:12px; color:#a1a1aa;">
                                            Einverstanden? Antworten Sie einfach auf diese E-Mail —
                                            wir machen den Kauf dann für Sie fertig.
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
