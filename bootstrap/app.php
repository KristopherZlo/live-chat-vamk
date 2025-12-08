<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\ThrottleRequests;

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
        ]);

        // Throttle all web routes: 20 req/sec (~1200 per minute) per IP
        $middleware->appendToGroup('web', 'throttle:web');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
