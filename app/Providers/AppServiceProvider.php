<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('web', function (Request $request) {
            return Limit::perMinute(1200)->by($request->ip());
        });

        RateLimiter::for('room-messages', function (Request $request) {
            $room = $request->route('room');
            $roomId = is_object($room) && method_exists($room, 'getKey') ? $room->getKey() : $room;
            $userId = optional($request->user())->getAuthIdentifier();
            $sessionId = $request->session()?->getId();
            $ip = $request->ip();
            $compositeKey = implode('|', array_filter([$roomId, $userId, $sessionId, $ip]));

            return [
                Limit::perMinute(30)->by($compositeKey),
                Limit::perMinute(60)->by($ip),
            ];
        });
    }
}
