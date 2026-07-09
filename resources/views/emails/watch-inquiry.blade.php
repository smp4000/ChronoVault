{{--
=============================================================================
E-Mail: Shop-Anfrage an den Händler (intern, Modul Shop)
=============================================================================
Erwartet: $watch, $inquiry (name/email/phone/message), $tenantName, $panelUrl.
--}}
@php
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
    <title>Shop-Anfrage</title>
</head>
<body style="margin:0; padding:0; background-color:#f5f5f4; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">
        Neue Anfrage von {{ $inquiry['name'] }} zu {{ $watch->fullName() }}.
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
                                    <td style="background-color:#1e40af; padding:28px 40px;" align="center">
                                        <p style="margin:0; font-size:12px; font-weight:600; letter-spacing:3px; text-transform:uppercase; color:#bfdbfe;">
                                            Neue Shop-Anfrage
                                        </p>
                                        <p style="margin:10px 0 0 0; font-size:22px; font-weight:700; color:#ffffff;">
                                            {{ $watch->fullName() }}
                                        </p>
                                    </td>
                                </tr>

                                {{-- Uhr-Kachel --}}
                                <tr>
                                    <td style="padding:28px 40px 0 40px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e7e5e4; border-radius:16px;">
                                            <tr>
                                                @if ($photoSrc)
                                                    <td width="104" style="padding:14px 0 14px 14px;" valign="top">
                                                        <img src="{{ $photoSrc }}" alt="" width="88" height="88"
                                                             style="display:block; width:88px; height:88px; object-fit:cover; border-radius:12px; border:1px solid #e7e5e4;">
                                                    </td>
                                                @endif
                                                <td style="padding:14px;" valign="middle">
                                                    <p style="margin:0; font-size:11px; font-weight:600; letter-spacing:2px; text-transform:uppercase; color:#1e40af;">
                                                        {{ $watch->brand->name }}
                                                    </p>
                                                    <p style="margin:4px 0 0 0; font-size:16px; font-weight:600; color:#18181b;">
                                                        {{ $watch->model_name }}
                                                    </p>
                                                    <p style="margin:4px 0 0 0; font-size:13px; color:#71717a;">
                                                        @if ($watch->reference_number) Ref. {{ $watch->reference_number }} · @endif
                                                        {{ $watch->formattedAskingPrice() ?? 'Preis auf Anfrage' }}
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                {{-- Kundendaten --}}
                                <tr>
                                    <td style="padding:24px 40px 0 40px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding:9px 0; border-bottom:1px solid #f4f4f5; font-size:14px; color:#71717a;">Name</td>
                                                <td style="padding:9px 0; border-bottom:1px solid #f4f4f5; font-size:14px; font-weight:600; color:#18181b;" align="right">{{ $inquiry['name'] }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:9px 0; border-bottom:1px solid #f4f4f5; font-size:14px; color:#71717a;">E-Mail</td>
                                                <td style="padding:9px 0; border-bottom:1px solid #f4f4f5; font-size:14px; font-weight:600; color:#18181b;" align="right">{{ $inquiry['email'] }}</td>
                                            </tr>
                                            @if (! empty($inquiry['phone']))
                                                <tr>
                                                    <td style="padding:9px 0; border-bottom:1px solid #f4f4f5; font-size:14px; color:#71717a;">Telefon</td>
                                                    <td style="padding:9px 0; border-bottom:1px solid #f4f4f5; font-size:14px; font-weight:600; color:#18181b;" align="right">{{ $inquiry['phone'] }}</td>
                                                </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>

                                {{-- Nachricht --}}
                                <tr>
                                    <td style="padding:24px 40px 0 40px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc; border-radius:16px;">
                                            <tr>
                                                <td style="padding:18px 22px;">
                                                    <p style="margin:0; font-size:11px; font-weight:600; letter-spacing:2px; text-transform:uppercase; color:#71717a;">Nachricht</p>
                                                    <p style="margin:8px 0 0 0; font-size:14px; line-height:1.7; color:#3f3f46; white-space:pre-line;">{{ $inquiry['message'] }}</p>
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
                                            Einfach auf diese E-Mail antworten — die Antwort geht direkt an {{ $inquiry['name'] }}.
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
