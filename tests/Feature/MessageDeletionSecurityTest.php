<?php

use App\Models\Message;
use App\Models\Participant;
use App\Models\Room;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

test('participant cannot delete another participant message', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Secure room',
        'slug' => Str::random(8),
    ]);

    $author = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Author',
    ]);

    $other = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Other',
    ]);

    $message = Message::create([
        'room_id' => $room->id,
        'participant_id' => $author->id,
        'user_id' => null,
        'is_system' => false,
        'content' => 'Hello world',
    ]);

    $sessionKey = 'room_participant_' . $room->id;

    $response = $this
        ->withSession([$sessionKey => $other->id])
        ->deleteJson(route('rooms.messages.destroy', [$room, $message]));

    $response->assertStatus(403);
    expect($message->fresh())->not->toBeNull();
    expect($message->fresh()->trashed())->toBeFalse();
});

test('participant can delete their own message using session binding', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Owned room',
        'slug' => Str::random(8),
    ]);

    $author = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Author',
    ]);

    $message = Message::create([
        'room_id' => $room->id,
        'participant_id' => $author->id,
        'user_id' => null,
        'is_system' => false,
        'content' => 'Deletable content',
    ]);

    $sessionKey = 'room_participant_' . $room->id;

    $response = $this
        ->withSession([$sessionKey => $author->id])
        ->deleteJson(route('rooms.messages.destroy', [$room, $message]));

    $response->assertOk();
    expect($message->fresh()->trashed())->toBeTrue();
});

test('message deletion is throttled', function () {
    RateLimiter::for('room-messages', function (Request $request) {
        return [Limit::perMinute(1)->by($request->ip())];
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
        'content' => 'To be deleted',
    ]);

    $ip = '203.0.113.5';

    $first = $this
        ->withServerVariables(['REMOTE_ADDR' => $ip])
        ->actingAs($owner)
        ->deleteJson(route('rooms.messages.destroy', [$room, $message]));

    $first->assertOk();

    $second = $this
        ->withServerVariables(['REMOTE_ADDR' => $ip])
        ->actingAs($owner)
        ->deleteJson(route('rooms.messages.destroy', [$room, $message]));

    $second->assertStatus(429);

    RateLimiter::clear($ip);

    RateLimiter::for('room-messages', function (Request $request) {
        $room = $request->route('room');
        $roomId = is_object($room) && method_exists($room, 'getKey') ? $room->getKey() : $room;
        $userId = optional($request->user())->getAuthIdentifier();
        $sessionId = $request->session()?->getId();
        $ipAddress = $request->ip();
        $compositeKey = implode('|', array_filter([$roomId, $userId, $sessionId, $ipAddress]));

        return [
            Limit::perMinute(20)->by($compositeKey),
            Limit::perMinute(40)->by($ipAddress),
        ];
    });
});
