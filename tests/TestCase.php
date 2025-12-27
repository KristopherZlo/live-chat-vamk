<?php

namespace Tests;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected string $csrfToken = '';

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);

        // Keep @vite rendering stable in tests without a real build.
        Vite::useBuildDirectory('build-test');
        Vite::useHotFile(public_path('hot-test'));

        // Reset the room-messages limiter so tests do not leak overrides.
        RateLimiter::for('room-messages', function (Request $request) {
            $room = $request->route('room');
            $roomId = is_object($room) && method_exists($room, 'getKey') ? $room->getKey() : $room;
            $userId = optional($request->user())->getAuthIdentifier();
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

        $this->csrfToken = Str::random(40);
        $this->withHeader('X-CSRF-TOKEN', $this->csrfToken);
        $this->withSession(['_token' => $this->csrfToken]);
    }

    public function withSession(array $data)
    {
        $token = $data['_token'] ?? ($this->csrfToken ?: Str::random(40));
        $this->csrfToken = $token;
        $data['_token'] = $token;

        $this->withHeader('X-CSRF-TOKEN', $token);

        return parent::withSession(array_merge($this->session ?? [], $data));
    }
}
