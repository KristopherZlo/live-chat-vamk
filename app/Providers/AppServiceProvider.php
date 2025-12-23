<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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
        try {
            $version = Setting::getValue('app_version', config('app.version'));
            if ($version) {
                config(['app.version' => $version]);
            }
        } catch (\Throwable $e) {
            // Settings table may not exist during early migrations.
        }

        RateLimiter::for('web', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();
            $ip = $request->ip();

            if ($userId) {
                return [
                    Limit::perMinute(3600)->by('user|'.$userId),
                    Limit::perMinute(2400)->by($ip),
                ];
            }

            return Limit::perMinute(1200)->by($ip);
        });

        RateLimiter::for('login', function (Request $request) {
            $emailKey = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(10)->by($request->ip()),
                Limit::perMinute(8)->by($emailKey.'|'.$request->ip()),
            ];
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            $emailKey = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perMinute(5)->by($emailKey),
            ];
        });

        RateLimiter::for('room-messages', function (Request $request) {
            $room = $request->route('room');
            $roomId = is_object($room) && method_exists($room, 'getKey') ? $room->getKey() : $room;
            $userId = $request->user()?->getAuthIdentifier();
            $sessionId = $request->session()?->getId();
            $ip = $request->ip();
            $compositeKey = implode('|', array_filter([$roomId, $userId, $sessionId, $ip]));
            $isAuthenticated = (bool) $userId;
            $perMinute = $isAuthenticated ? 120 : 20;
            $perMinuteIp = $isAuthenticated ? 240 : 40;

            return [
                Limit::perMinute($perMinute)->by($compositeKey),
                Limit::perMinute($perMinuteIp)->by($ip),
            ];
        });

        RateLimiter::for('room-joins', function (Request $request) {
            $ip = $request->ip();
            $codeKey = Str::lower((string) $request->input('code'));

            return [
                Limit::perMinute(15)->by($ip),
                Limit::perMinute(5)->by($codeKey.'|'.$ip),
            ];
        });

        RateLimiter::for('room-exists', function (Request $request) {
            $ip = $request->ip();
            $slugKey = Str::lower((string) $request->route('slug'));

            return [
                Limit::perMinute(60)->by($ip),
                Limit::perMinute(15)->by($slugKey.'|'.$ip),
            ];
        });

        RateLimiter::for('client-errors', function (Request $request) {
            $ip = $request->ip();
            $userId = $request->user()?->getAuthIdentifier();

            if ($userId) {
                return [
                    Limit::perMinute(60)->by('user|'.$userId),
                    Limit::perMinute(30)->by($ip),
                ];
            }

            return Limit::perMinute(20)->by($ip);
        });
    }
}
