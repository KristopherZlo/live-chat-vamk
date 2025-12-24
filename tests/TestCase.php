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
            $ip = $request->ip();
            $compositeKey = implode('|', array_filter([$roomId, $userId, $sessionId, $ip]));

            return [
                Limit::perMinute(20)->by($compositeKey),
                Limit::perMinute(40)->by($ip),
            ];
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
