<?php

declare(strict_types=1);

return [
    'enabled' => env('BOOST_ENABLED', true),

    // Browser log injection conflicts with strict CSP unless every injected
    // script is nonce-aware. Keep it opt-in instead of always-on.
    'browser_logs_watcher' => env('BOOST_BROWSER_LOGS_WATCHER', false),
];
