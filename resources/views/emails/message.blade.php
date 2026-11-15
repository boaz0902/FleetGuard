<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $heading }}</title>
    <style>
        @media only screen and (max-width: 620px) {
            .email-shell { width: 100% !important; }
            .email-gutter { padding-right: 18px !important; padding-left: 18px !important; }
            .email-card { padding: 28px 22px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#F4F6F8;font-family:Arial,Helvetica,sans-serif;color:#101820;">
    <div style="display:none;font-size:1px;color:#F4F6F8;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
        {{ $preheader }}
    </div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#F4F6F8;">
        <tr>
            <td align="center" class="email-gutter" style="padding:32px 16px;">
                <table role="presentation" class="email-shell" width="600" cellspacing="0" cellpadding="0" border="0" style="width:600px;max-width:600px;">
                    <tr>
                        <td style="padding:22px 28px;background-color:#101820;color:#F4F6F8;font-size:21px;font-weight:700;letter-spacing:0.3px;">
                            {{ $businessName }}
                        </td>
                    </tr>
                    <tr>
                        <td class="email-card" style="padding:38px 40px;background-color:#FFFFFF;">
                            <h1 style="margin:0 0 16px;color:#101820;font-size:26px;line-height:1.25;font-weight:700;">{{ $heading }}</h1>
                            <p style="margin:0;color:#35404B;font-size:16px;line-height:1.65;">{{ $intro }}</p>

                            @if ($details !== [])
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:24px 0;border-collapse:collapse;">
                                    @foreach ($details as $detail)
                                        <tr>
                                            <td style="padding:9px 0;border-bottom:1px solid #E5E9ED;color:#687482;font-size:14px;vertical-align:top;">{{ $detail['label'] }}</td>
                                            <td align="right" style="padding:9px 0;border-bottom:1px solid #E5E9ED;color:#101820;font-size:14px;font-weight:600;vertical-align:top;">{{ $detail['value'] }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif

                            @if ($action)
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:26px 0 18px;">
                                    <tr>
                                        <td align="center" bgcolor="#3988D6" style="border-radius:4px;">
                                            <a href="{{ $action['url'] }}" style="display:inline-block;padding:14px 22px;border:1px solid #3988D6;border-radius:4px;color:#FFFFFF;font-size:15px;font-weight:700;text-decoration:none;">{{ $action['label'] }}</a>
                                        </td>
                                    </tr>
                                </table>
                                <p style="margin:0 0 18px;color:#687482;font-size:13px;line-height:1.6;">
                                    If the button does not work, copy and paste this link into your browser:<br>
                                    <a href="{{ $action['url'] }}" style="color:#2369AE;word-break:break-all;">{{ $action['url'] }}</a>
                                </p>
                            @endif

                            @if ($callout)
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:24px;background-color:#F4F6F8;border-left:3px solid #3988D6;">
                                    <tr>
                                        <td style="padding:17px 18px;">
                                            <p style="margin:0 0 8px;color:#101820;font-size:15px;font-weight:700;">{{ $callout['heading'] }}</p>
                                            <p style="margin:0;color:#35404B;font-size:14px;line-height:1.6;">{{ $callout['text'] }}</p>
                                            @if (! empty($callout['action']))
                                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:14px 0 8px;">
                                                    <tr>
                                                        <td align="center" bgcolor="#3988D6" style="border-radius:4px;">
                                                            <a href="{{ $callout['action']['url'] }}" style="display:inline-block;padding:12px 18px;border:1px solid #3988D6;border-radius:4px;color:#FFFFFF;font-size:14px;font-weight:700;text-decoration:none;">{{ $callout['action']['label'] }}</a>
                                                        </td>
                                                    </tr>
                                                </table>
                                                <p style="margin:0;color:#687482;font-size:12px;line-height:1.5;">Or copy this link into your browser: <a href="{{ $callout['action']['url'] }}" style="color:#2369AE;word-break:break-all;">{{ $callout['action']['url'] }}</a></p>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            @if ($note)
                                <p style="margin:22px 0 0;color:#687482;font-size:13px;line-height:1.6;">{{ $note }}</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:22px 28px;color:#89939F;font-size:12px;line-height:1.7;">
                            <strong style="color:#687482;">{{ $businessName }}</strong><br>
                            @forelse ($contactLines as $label => $value)
                                {{ $label }}: {{ $value }}<br>
                            @empty
                                Contact information is not currently listed.
                            @endforelse
                            <br>This is an automated message. Please do not reply to this email.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>