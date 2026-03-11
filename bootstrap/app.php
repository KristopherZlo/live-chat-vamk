<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Apache can expose APP_KEY as an empty server variable, which prevents
 * Dotenv from loading the real value from .env and causes MissingAppKeyException.
 */
if ((string) getenv('APP_KEY') === '') {
    $envPath = dirname(__DIR__).DIRECTORY_SEPARATOR.'.env';

    if (is_readable($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $trimmed = ltrim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#') || ! str_starts_with($trimmed, 'APP_KEY=')) {
                continue;
            }

            $appKey = trim(substr($trimmed, 8));
            $appKey = trim($appKey, " \t\n\r\0\x0B\"'");

            if ($appKey !== '') {
                putenv("APP_KEY={$appKey}");
                $_ENV['APP_KEY'] = $appKey;
                $_SERVER['APP_KEY'] = $appKey;
            }

            break;
        }
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'throttle' => ThrottleRequests::class,
            'dev' => \App\Http\Middleware\EnsureDev::class,
            'admin.ip' => \App\Http\Middleware\AdminIpGuard::class,
            'security.headers' => \App\Http\Middleware\SecurityHeaders::class,
            'request.id' => \App\Http\Middleware\RequestId::class,
            'normalize.error.html' => \App\Http\Middleware\NormalizeHtmlErrorResponse::class,
        ]);

        // Throttle all web routes: 20 req/sec (~1200 per minute) per IP
        $middleware->appendToGroup('web', 'throttle:web');
        $middleware->appendToGroup('web', 'request.id');
        $middleware->appendToGroup('web', 'security.headers');
        $middleware->appendToGroup('web', 'normalize.error.html');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
