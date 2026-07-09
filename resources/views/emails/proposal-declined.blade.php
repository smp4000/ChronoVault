{{--
=============================================================================
E-Mail: „Schade"-Mail nach abgelehntem Gegenangebot (Shop)
=============================================================================
Erwartet: $proposal, $watch (nullable), $tenantName, $shopUrl.
=============================================================================
--}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Schade — vielleicht beim nächsten Mal</title>
</head>
<body style="margin:0; padding:0; background-color:#f5f5f4; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">
        Schade, dass wir diesmal nicht zusammengekommen sind.
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
                                    <td style="background-color:#3f3f46; padding:32px 40px;" align="center">
                                        <p style="margin:0; font-size:12px; font-weight:600; letter-spacing:3px; text-transform:uppercase; color:#d4d4d8;">
                                            Schade — vielleicht beim nächsten Mal
                                        </p>
                                        @if ($watch)
                                            <p style="margin:10px 0 0 0; font-size:20px; font-weight:700; color:#ffffff;">
                                                {{ $watch->fullName() }}
                                            </p>
                                        @endif
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:32px 40px 8px 40px;">
                                        <p style="margin:0; font-size:15px; line-height:1.6; color:#3f3f46;">
                                            Guten Tag {{ $proposal->name }},
                                        </p>
                                        <p style="margin:14px 0 0 0; font-size:15px; line-height:1.7; color:#3f3f46;">
                                            schade, dass wir diesmal nicht zusammengekommen sind —
                                            vielen Dank trotzdem für Ihr Interesse! Unsere Kollektion
                                            wächst laufend: Schauen Sie gerne wieder vorbei, vielleicht
                                            ist bald genau das richtige Stück für Sie dabei.
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:20px 40px 36px 40px;" align="center">
                                        <a href="{{ $shopUrl }}"
                                           style="display:inline-block; background-color:#1e40af; color:#ffffff; font-size:14px; font-weight:600; text-decoration:none; padding:13px 34px; border-radius:999px;">
                                            Kollektion ansehen
                                        </a>
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
