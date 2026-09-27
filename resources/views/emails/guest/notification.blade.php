<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $organisationName }}</title>
</head>
{{--
    Mise en page par tableaux, styles en ligne uniquement (aucune balise <style> externe) :
    les clients de messagerie n'appliquent pas de fiche CSS separee de facon fiable. Les
    couleurs de marque arrivent deja validees par expression reguliere hexadecimale stricte
    (CLAUDE.md, « Organisation »), elles entrent donc sans risque dans un attribut style.
--}}
<body style="margin:0; padding:0; background-color:#f5f4f2; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f5f4f2; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; background-color:#ffffff; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="background-color:{{ $primaryColor }}; padding:24px 32px;">
                            <span style="color:#ffffff; font-size:18px; font-weight:bold;">{{ $organisationName }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            @foreach ($lines as $line)
                                <p style="margin:0 0 16px 0; color:#1b1917; font-size:15px; line-height:1.5;">{{ $line }}</p>
                            @endforeach

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:8px;">
                                <tr>
                                    <td style="border-radius:6px; background-color:{{ $secondaryColor }};">
                                        <a href="{{ $actionUrl }}" style="display:inline-block; padding:12px 24px; color:#1b1917; font-size:15px; font-weight:bold; text-decoration:none;">
                                            {{ $actionText }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px; background-color:#f5f4f2;">
                            <span style="color:#6b6862; font-size:12px;">{{ config('app.name') }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
