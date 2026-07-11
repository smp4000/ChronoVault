{{--
=============================================================================
E-Mail: Passwort zurücksetzen (ChronoVault-Design, beide Panels)
=============================================================================
Erwartet: $url, $userName, $brandName, $expireMinutes.
=============================================================================
--}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Passwort zurücksetzen</title>
</head>
<body style="margin:0; padding:0; background-color:#f5f5f4; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">
        Setzen Sie Ihr Passwort zurück — der Link ist {{ $expireMinutes }} Minuten gültig.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f5f5f4; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%;">

                    <tr>
                        <td style="padding:0 8px 20px 8px;" align="center">
                            <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background-color:#1e40af;"></span>
                            <span style="font-size:14px; font-weight:600; letter-spacing:4px; text-transform:uppercase; color:#18181b; vertical-align:middle; padding-left:10px;">
                                {{ $brandName }}
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#ffffff; border-radius:24px; border:1px solid #e7e5e4; overflow:hidden;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">

                                <tr>
                                    <td style="background-color:#1e40af; padding:32px 40px;" align="center">
                                        <p style="margin:0; font-size:12px; font-weight:600; letter-spacing:3px; text-transform:uppercase; color:#bfdbfe;">
                                            Passwort zurücksetzen
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:32px 40px 8px 40px;">
                                        <p style="margin:0; font-size:15px; line-height:1.6; color:#3f3f46;">
                                            Guten Tag{{ $userName !== '' ? ' '.$userName : '' }},
                                        </p>
                                        <p style="margin:14px 0 0 0; font-size:15px; line-height:1.7; color:#3f3f46;">
                                            für Ihr Konto wurde das Zurücksetzen des Passworts angefordert.
                                            Klicken Sie auf den Knopf, um ein neues Passwort zu vergeben:
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:24px 40px 8px 40px;" align="center">
                                        <a href="{{ $url }}"
                                           style="display:inline-block; background-color:#1e40af; color:#ffffff; font-size:15px; font-weight:600; text-decoration:none; padding:14px 38px; border-radius:999px;">
                                            Neues Passwort vergeben
                                        </a>
                                        <p style="margin:12px 0 0 0; font-size:12px; color:#a1a1aa;">
                                            Der Link ist {{ $expireMinutes }} Minuten gültig.
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:16px 40px 32px 40px;">
                                        <p style="margin:0; font-size:13px; line-height:1.7; color:#71717a;">
                                            Sie haben das nicht angefordert? Dann können Sie diese E-Mail
                                            einfach ignorieren — Ihr Passwort bleibt unverändert.
                                        </p>
                                        <p style="margin:14px 0 0 0; font-size:11px; line-height:1.7; color:#a1a1aa; word-break:break-all;">
                                            Falls der Knopf nicht funktioniert, kopieren Sie diesen Link in
                                            Ihren Browser:<br>{{ $url }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px 8px 0 8px;" align="center">
                            <p style="margin:0; font-size:11px; color:#d4d4d8;">
                                &copy; {{ now()->year }} {{ $brandName }} · Bereitgestellt über ChronoVault
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
