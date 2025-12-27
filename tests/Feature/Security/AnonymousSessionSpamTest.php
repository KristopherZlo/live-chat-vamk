<?php

use App\Models\Message;
use App\Models\Participant;
use App\Models\Room;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

test('rotating anonymous sessions cannot bypass room message and reaction throttles', function () {
    RateLimiter::for('room-messages', function (Request $request) {
        return [
            Limit::perMinute(4)->by($request->ip()),
        ];
    });

    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Throttle room',
        'slug' => Str::random(8),
    ]);

    $message = Message::create([
        'room_id' => $room->id,
        'participant_id' => null,
        'user_id' => $owner->id,
        'is_system' => false,
        'content' => 'React to me',
    ]);

    $ip = '203.0.113.10';
    $sessionCookie = config('session.cookie');
    $emoji = "\u{1F44D}";

    $seedSession = function () use ($room) {
        $participant = Participant::create([
            'room_id' => $room->id,
            'session_token' => (string) Str::uuid(),
            'display_name' => 'Spammer',
        ]);

        $sessionId = Str::random(40);
        $csrfToken = Str::random(40);
        $sessionKey = 'room_participant_' . $room->id;
        $session = app('session.store');
        $session->setId($sessionId);
        $session->setExists(false);
        $session->flush();
        $session->put($sessionKey, $participant->id);
        $session->put('_token', $csrfToken);
        $session->save();

        return [$sessionId, $csrfToken];
    };

    $sendMessage = function (string $sessionId, string $csrfToken, string $content) use ($room, $ip, $sessionCookie) {
        return $this
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withCookie($sessionCookie, $sessionId)
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->withCredentials()
            ->postJson(route('rooms.messages.store', $room), [
                'content' => $content,
            ]);
    };

    $sendReaction = function (string $sessionId, string $csrfToken) use ($room, $message, $ip, $sessionCookie, $emoji) {
        return $this
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withCookie($sessionCookie, $sessionId)
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->withCredentials()
            ->postJson(route('rooms.messages.reactions.toggle', [$room, $message]), [
                'emoji' => $emoji,
            ]);
    };

    for ($i = 0; $i < 2; $i++) {
        [$sessionId, $csrfToken] = $seedSession();
        $sendMessage($sessionId, $csrfToken, "Spam message {$i}")->assertStatus(201);
    }

    for ($i = 0; $i < 2; $i++) {
        [$sessionId, $csrfToken] = $seedSession();
        $sendReaction($sessionId, $csrfToken)->assertOk();
    }

    [$sessionId, $csrfToken] = $seedSession();
    $sendMessage($sessionId, $csrfToken, 'blocked')->assertStatus(429);
});

test('rotating IPs cannot bypass fingerprint throttles', function () {
    config([
        'ghostroom.limits.room.messages_per_minute_fingerprint' => 2,
        'ghostroom.limits.room.messages_per_minute_room' => 1000,
    ]);

    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Fingerprint room',
        'slug' => Str::random(8),
    ]);

    $message = Message::create([
        'room_id' => $room->id,
        'participant_id' => null,
        'user_id' => $owner->id,
        'is_system' => false,
        'content' => 'React to me',
    ]);

    $sessionCookie = config('session.cookie');
    $fingerprint = (string) Str::uuid();
    $emoji = "\u{1F44D}";

    $seedSession = function () use ($room) {
        $participant = Participant::create([
            'room_id' => $room->id,
            'session_token' => (string) Str::uuid(),
            'display_name' => 'Spammer',
        ]);

        $sessionId = Str::random(40);
        $csrfToken = Str::random(40);
        $sessionKey = 'room_participant_' . $room->id;
        $session = app('session.store');
        $session->setId($sessionId);
        $session->setExists(false);
        $session->flush();
        $session->put($sessionKey, $participant->id);
        $session->put('_token', $csrfToken);
        $session->save();

        return [$sessionId, $csrfToken];
    };

    $sendMessage = function (string $sessionId, string $csrfToken, string $ip, string $content) use ($room, $sessionCookie, $fingerprint) {
        return $this
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withCookie($sessionCookie, $sessionId)
            ->withCookie('lc_fp', $fingerprint)
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->withCredentials()
            ->postJson(route('rooms.messages.store', $room), [
                'content' => $content,
            ]);
    };

    $sendReaction = function (string $sessionId, string $csrfToken, string $ip) use ($room, $message, $sessionCookie, $fingerprint, $emoji) {
        return $this
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withCookie($sessionCookie, $sessionId)
            ->withCookie('lc_fp', $fingerprint)
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->withCredentials()
            ->postJson(route('rooms.messages.reactions.toggle', [$room, $message]), [
                'emoji' => $emoji,
            ]);
    };

    [$sessionOne, $tokenOne] = $seedSession();
    $sendMessage($sessionOne, $tokenOne, '203.0.113.11', 'Spam 1')->assertStatus(201);

    [$sessionTwo, $tokenTwo] = $seedSession();
    $sendMessage($sessionTwo, $tokenTwo, '203.0.113.12', 'Spam 2')->assertStatus(201);

    [$sessionThree, $tokenThree] = $seedSession();
    $sendReaction($sessionThree, $tokenThree, '203.0.113.13')->assertStatus(429);
});

test('participant creation is throttled per fingerprint', function () {
    config([
        'ghostroom.limits.room.participant_create_per_minute' => 2,
        'ghostroom.limits.room.participant_create_per_minute_ip' => 1000,
    ]);

    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Join throttle room',
        'slug' => Str::random(8),
    ]);

    $fingerprint = (string) Str::uuid();
    $sessionCookie = config('session.cookie');
    $seedSession = function () {
        $sessionId = Str::random(40);
        $session = app('session.store');
        $session->setId($sessionId);
        $session->setExists(false);
        $session->flush();
        $session->save();

        return $sessionId;
    };

    for ($i = 0; $i < 2; $i++) {
        $sessionId = $seedSession();
        $this
            ->withCookie($sessionCookie, $sessionId)
            ->withCookie('lc_fp', $fingerprint)
            ->get(route('rooms.public', $room->slug))
            ->assertOk();
    }

    $rateKey = 'room-participant|' . $room->id . '|fp|' . $fingerprint;
    expect(RateLimiter::attempts($rateKey))->toBe(2);

    $this
        ->withCookie($sessionCookie, $seedSession())
        ->withCookie('lc_fp', $fingerprint)
        ->get(route('rooms.public', $room->slug))
        ->assertStatus(429);
});
