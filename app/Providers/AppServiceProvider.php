<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
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

        $monthDay = now()->format('m-d');
        $isHolidayLogoSeason = $monthDay >= '12-15' || $monthDay <= '01-15';
        $seasonalLogoAssets = $isHolidayLogoSeason
            ? [
                'light' => 'icons/logo_black_xmas.svg',
                'dark' => 'icons/logo_white_xmas.svg',
                'meta' => 'icons/logo_black_xmas.svg',
                'favicon' => 'icons/logo_white_xmas.svg',
            ]
            : [
                'light' => 'icons/logo_black.svg',
                'dark' => 'icons/logo_white.svg',
                'meta' => 'icons/logo_black.svg',
                'favicon' => 'icons/logo_white.svg',
            ];
        View::share('isHolidayLogoSeason', $isHolidayLogoSeason);
        View::share('seasonalLogoAssets', $seasonalLogoAssets);

        $subnetKeyResolver = static function (?string $ip): string {
            $ip = (string) $ip;
            if ($ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP)) {
                return 'unknown';
            }

            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $parts = explode('.', $ip);
                if (count($parts) !== 4) {
                    return 'unknown-v4';
                }

                return sprintf('%s.%s.%s.0/24', $parts[0], $parts[1], $parts[2]);
            }

            $packed = inet_pton($ip);
            if ($packed === false) {
                return 'unknown-v6';
            }

            return bin2hex(substr($packed, 0, 8)).'/64';
        };

        RateLimiter::for('web', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();
            $ip = $request->ip();
            $guestIpPerMinute = (int) config('ghostroom.limits.web.guest_ip_per_minute', 1200);
            $userPerMinute = (int) config('ghostroom.limits.web.user_per_minute', 3600);
            $userIpPerMinute = (int) config('ghostroom.limits.web.user_ip_per_minute', 2400);

            if ($userId) {
                return [
                    Limit::perMinute($userPerMinute)->by('user|'.$userId),
                    Limit::perMinute($userIpPerMinute)->by($ip),
                ];
            }

            return Limit::perMinute($guestIpPerMinute)->by($ip);
        });

        RateLimiter::for('login', function (Request $request) {
            $emailKey = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(10)->by($request->ip()),
                Limit::perMinute(8)->by($emailKey.'|'.$request->ip()),
            ];
        });

        RateLimiter::for('register', function (Request $request) use ($subnetKeyResolver) {
            $ip = (string) $request->ip();
            $subnet = $subnetKeyResolver($ip);
            $perMinuteIp = (int) config('ghostroom.limits.auth.register_per_minute_ip', 4);
            $perHourIp = (int) config('ghostroom.limits.auth.register_per_hour_ip', 20);
            $perHourSubnet = (int) config('ghostroom.limits.auth.register_per_hour_subnet', 60);

            return [
                Limit::perMinute($perMinuteIp)->by('register|ip-minute|'.$ip),
                Limit::perHour($perHourIp)->by('register|ip-hour|'.$ip),
                Limit::perHour($perHourSubnet)->by('register|subnet-hour|'.$subnet),
            ];
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
            $fingerprint = $request->cookie('lc_fp');
            $ip = $request->ip();
            $isAuthenticated = (bool) $userId;
            $perMinuteAuth = (int) config('ghostroom.limits.room.messages_per_minute_auth', 120);
            $perMinuteGuest = (int) config('ghostroom.limits.room.messages_per_minute_guest', 20);
            $perMinuteIpAuth = (int) config('ghostroom.limits.room.messages_per_minute_ip_auth', 240);
            $perMinuteIpGuest = (int) config('ghostroom.limits.room.messages_per_minute_ip_guest', 40);
            $perMinute = $isAuthenticated ? $perMinuteAuth : $perMinuteGuest;
            $perMinuteIp = $isAuthenticated ? $perMinuteIpAuth : $perMinuteIpGuest;
            $identityKey = $userId
                ? implode('|', ['user', $userId, 'session', $sessionId])
                : ($fingerprint ? 'fp|'.$fingerprint : ($sessionId ? 'session|'.$sessionId : 'ip|'.$ip));
            $compositeKey = implode('|', array_filter([$roomId, $identityKey, $ip]));
            $limits = [
                Limit::perMinute($perMinute)->by($compositeKey),
                Limit::perMinute($perMinuteIp)->by($ip),
            ];

            if ($roomId) {
                $perMinuteRoom = (int) config('ghostroom.limits.room.messages_per_minute_room', 600);
                $limits[] = Limit::perMinute($perMinuteRoom)->by('room|'.$roomId);
            }

            if (!$isAuthenticated && $fingerprint && $roomId) {
                $perMinuteFingerprint = (int) config('ghostroom.limits.room.messages_per_minute_fingerprint', $perMinute);
                $limits[] = Limit::perMinute($perMinuteFingerprint)->by('room|'.$roomId.'|fp|'.$fingerprint);
            }

            return $limits;
        });

        RateLimiter::for('room-joins', function (Request $request) {
            $ip = $request->ip();
            $codeKey = Str::lower((string) $request->input('code'));
            $perMinuteIp = (int) config('ghostroom.limits.room.join_per_minute_ip', 15);
            $perMinuteCodeIp = (int) config('ghostroom.limits.room.join_per_minute_code_ip', 5);

            return [
                Limit::perMinute($perMinuteIp)->by($ip),
                Limit::perMinute($perMinuteCodeIp)->by($codeKey.'|'.$ip),
            ];
        });

        RateLimiter::for('room-reorder', function (Request $request) {
            $ip = $request->ip();
            $userId = $request->user()?->getAuthIdentifier();
            $perMinuteUser = (int) config('ghostroom.limits.room.reorder_per_minute_user', 90);
            $perMinuteIp = (int) config('ghostroom.limits.room.reorder_per_minute_ip', 240);
            $identityKey = $userId ? 'user|'.$userId : 'guest|'.$ip;

            return [
                Limit::perMinute($perMinuteUser)->by('reorder|'.$identityKey),
                Limit::perMinute($perMinuteIp)->by('reorder|ip|'.$ip),
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

        RateLimiter::for('verification-resend', function (Request $request) {
            $ip = (string) $request->ip();
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $perMinuteUser = (int) config('ghostroom.limits.auth.verification_resend_per_minute_user', 2);
            $perHourUser = (int) config('ghostroom.limits.auth.verification_resend_per_hour_user', 12);
            $perMinuteIp = (int) config('ghostroom.limits.auth.verification_resend_per_minute_ip', 6);
            $perHourIp = (int) config('ghostroom.limits.auth.verification_resend_per_hour_ip', 30);

            return [
                Limit::perMinute($perMinuteUser)->by('verification-resend|user-minute|'.$userId),
                Limit::perHour($perHourUser)->by('verification-resend|user-hour|'.$userId),
                Limit::perMinute($perMinuteIp)->by('verification-resend|ip-minute|'.$ip),
                Limit::perHour($perHourIp)->by('verification-resend|ip-hour|'.$ip),
            ];
        });

        RateLimiter::for('verification-code', function (Request $request) {
            $ip = (string) $request->ip();
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');
            $perMinuteUser = (int) config('ghostroom.limits.auth.verification_code_attempts_per_minute_user', 8);
            $perHourUser = (int) config('ghostroom.limits.auth.verification_code_attempts_per_hour_user', 40);
            $perMinuteIp = (int) config('ghostroom.limits.auth.verification_code_attempts_per_minute_ip', 20);
            $perHourIp = (int) config('ghostroom.limits.auth.verification_code_attempts_per_hour_ip', 100);

            return [
                Limit::perMinute($perMinuteUser)->by('verification-code|user-minute|'.$userId),
                Limit::perHour($perHourUser)->by('verification-code|user-hour|'.$userId),
                Limit::perMinute($perMinuteIp)->by('verification-code|ip-minute|'.$ip),
                Limit::perHour($perHourIp)->by('verification-code|ip-hour|'.$ip),
            ];
        });
    }
}
