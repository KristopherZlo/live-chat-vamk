@php
    $recipient = trim((string) ($recipientName ?? ''));
    $greetingName = $recipient !== '' ? $recipient : 'there';
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $appName }} verification code</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            background-color: #ececec;
            color: #111111;
            font-family: "Figtree", "Noto Sans", "Helvetica Neue", Helvetica, sans-serif;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        table {
            border-collapse: collapse;
        }

        img {
            border: 0;
            outline: none;
            text-decoration: none;
            -ms-interpolation-mode: bicubic;
        }

        a {
            color: #2563eb;
            text-decoration: none;
        }

        .preheader {
            display: none !important;
            visibility: hidden;
            opacity: 0;
            color: transparent;
            height: 0;
            width: 0;
            overflow: hidden;
            mso-hide: all;
        }

        .bg {
            background-color: #ececec;
            background-image: repeating-linear-gradient(
                -9deg,
                transparent 0,
                transparent 6px,
                rgba(0, 0, 0, 0.03) 6px,
                rgba(0, 0, 0, 0.03) 7px
            );
        }

        .panel {
            background: #ffffff;
            border: 1px solid #dddddd;
        }

        .title {
            margin: 0 0 20px;
            font-size: 38px;
            line-height: 1.2;
            color: #141414;
            font-weight: 700;
            letter-spacing: 0.01em;
        }

        .copy {
            margin: 0 0 14px;
            font-size: 18px;
            line-height: 1.55;
            color: #2b2b2b;
        }

        .code-plain-wrap {
            margin: 0 0 18px;
            border: 1px solid #d1d5db;
            background: #f8fafc;
        }

        .code-plain-label {
            margin: 0 0 6px;
            color: #4b5563;
            font-size: 13px;
            line-height: 1.4;
            font-weight: 600;
        }

        .code-plain-value {
            margin: 0;
            color: #111111;
            font-size: 34px;
            line-height: 1;
            letter-spacing: 0.16em;
            font-weight: 700;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
            user-select: text;
            -webkit-user-select: text;
        }

        .support {
            background: #f6f6f6;
            border: 1px solid #dddddd;
            border-top: 0;
        }

        .support-title {
            margin: 0 0 8px;
            color: #151515;
            font-size: 18px;
            line-height: 1.3;
            font-weight: 700;
        }

        .support-copy {
            margin: 0;
            color: #4b5563;
            font-size: 15px;
            line-height: 1.55;
        }

        @media only screen and (max-width: 680px) {
            .shell {
                width: 100% !important;
            }

            .panel-pad {
                padding: 24px 18px !important;
            }

            .title {
                font-size: 30px !important;
            }

            .copy {
                font-size: 16px !important;
            }

            .code-plain-value {
                font-size: 28px !important;
            }
        }
    </style>
</head>
<body>
<div class="preheader">Your 6-digit verification code for {{ $appName }} is {{ $code }}.</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="bg">
    <tr>
        <td align="center" style="padding: 22px 14px 34px;">
            <table role="presentation" width="640" cellpadding="0" cellspacing="0" border="0" class="shell">
                <tr>
                    <td align="center" style="padding: 8px 0 18px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="vertical-align: middle; padding-right: 8px;">
                                    <img src="{{ $logoUrl }}" alt="{{ $appName }}" width="34" height="34" style="display: block; width: 34px; height: 34px;">
                                </td>
                                <td style="vertical-align: middle; font-size: 24px; line-height: 1.05; font-weight: 700; color: #111111;">
                                    {{ $appName }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td class="panel panel-pad" style="padding: 34px 44px 30px;">
                        <h1 class="title">Verify This Email Address</h1>

                        <p class="copy">Hi {{ $greetingName }},</p>
                        <p class="copy">Welcome to {{ $appName }}.</p>
                        <p class="copy">Use the 6-digit code below to verify your email address:</p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="code-plain-wrap">
                            <tr>
                                <td style="padding: 12px 14px 13px;">
                                    <p class="code-plain-label">This is your verification code:</p>
                                    <p class="code-plain-value">{{ $code }}</p>
                                </td>
                            </tr>
                        </table>

                        <p class="copy">This code expires in <strong>{{ $ttlMinutes }} minutes</strong>.</p>
                        <p class="copy" style="margin-bottom: 0;">
                            If you did not sign up to {{ $appName }}, please ignore this email.
                            @if (!empty($supportEmail))
                                You can also contact us at <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.
                            @endif
                        </p>
                    </td>
                </tr>

                <tr>
                    <td class="support" style="padding: 18px 22px 20px;">
                        <p class="support-title">Need Support?</p>
                        <p class="support-copy">
                            Feel free to email us if you have any questions, comments or suggestions.
                            @if (!empty($supportEmail))
                                <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
                            @endif
                            @if (!empty($homeUrl))
                                . You can also visit <a href="{{ $homeUrl }}">{{ $appName }}</a>
                            @endif
                            .
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
