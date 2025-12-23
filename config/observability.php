<?php

return [
    'retention' => [
        'audit_days' => env('AUDIT_LOG_RETENTION_DAYS', 180),
        'client_error_days' => env('CLIENT_ERROR_RETENTION_DAYS', 30),
    ],
    'alerts' => [
        'enabled' => env('OBS_ALERTS_ENABLED', true),
        'log_channel' => env('OBS_ALERT_LOG_CHANNEL', 'stack'),
        'cooldown_minutes' => env('OBS_ALERT_COOLDOWN_MINUTES', 30),
        'client_errors' => [
            'threshold' => env('OBS_CLIENT_ERROR_THRESHOLD', 20),
            'window_minutes' => env('OBS_CLIENT_ERROR_WINDOW_MINUTES', 5),
        ],
        'audit' => [
            'threshold' => env('OBS_AUDIT_LOG_THRESHOLD', 100),
            'window_minutes' => env('OBS_AUDIT_LOG_WINDOW_MINUTES', 10),
        ],
    ],
];
