{{ $appName }}

Verify This Email Address

Hi {{ trim((string) ($recipientName ?? '')) !== '' ? $recipientName : 'there' }},

Welcome to {{ $appName }}.
This is your verification code: {{ $code }}

This code expires in {{ $ttlMinutes }} minutes.

If you did not sign up to {{ $appName }}, please ignore this email.
@if(!empty($supportEmail))
Support: {{ $supportEmail }}
@endif
@if(!empty($homeUrl))
Website: {{ $homeUrl }}
@endif
