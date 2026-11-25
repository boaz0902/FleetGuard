<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $heading }}</title>
</head>
<body style="margin:0;padding:24px 12px;background-color:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#101820;">
    <div style="display:none;font-size:1px;color:#f4f6f8;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
        {{ $preheader }}
    </div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:560px;background-color:#ffffff;">
                    <tr>
                        <td style="padding:24px 28px;border-bottom:1px solid #e8edf2;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td style="padding-right:10px;">
                                        <img src="{{ $logoUrl }}" width="36" height="36" alt="" style="display:block;border:0;">
                                    </td>
                                    <td style="color:#101820;font-size:20px;font-weight:700;letter-spacing:0.2px;">
                                        {{ $businessName }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 28px;">
                            <h1 style="margin:0 0 14px;font-size:23px;line-height:1.3;font-weight:700;">{{ $heading }}</h1>
                            <p style="margin:0;color:#35404b;font-size:15px;line-height:1.65;">{{ $intro }}</p>

                            @if ($code)
                                <p style="margin:26px 0 8px;color:#687482;font-size:13px;">Your verification code</p>
                                <p style="margin:0;padding:16px;background-color:#f1f5fa;border-radius:8px;color:#101820;font-family:Arial,Helvetica,sans-serif;font-size:30px;font-weight:700;letter-spacing:9px;text-align:center;">
                                    {{ $code }}
                                </p>
                            @endif

                            @if ($action)
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:24px 0 12px;">
                                    <tr>
                                        <td bgcolor="#101820" style="border-radius:5px;">
                                            <a href="{{ $action['url'] }}" style="display:inline-block;padding:13px 20px;border:1px solid #101820;border-radius:5px;color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;">{{ $action['label'] }}</a>
                                        </td>
                                    </tr>
                                </table>
                                <p style="margin:0;color:#687482;font-size:12px;line-height:1.6;">
                                    If the button does not work, copy and paste this link:<br>
                                    <a href="{{ $action['url'] }}" style="color:#2369ae;word-break:break-all;">{{ $action['url'] }}</a>
                                </p>
                            @endif

                            @if ($note)
                                <p style="margin:22px 0 0;color:#687482;font-size:13px;line-height:1.6;">{{ $note }}</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px;border-top:1px solid #e8edf2;color:#687482;font-size:12px;line-height:1.6;">
                            This message relates to your {{ $businessName }} account. If you need help, contact us through our website.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
