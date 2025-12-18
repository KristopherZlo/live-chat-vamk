<?php

use App\Models\Message;
use App\Models\Room;
use App\Models\RoomBan;
use App\Models\User;
use Illuminate\Support\Str;

test('message history resolves rooms by slug instead of id', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Public room',
        'slug' => 'room-' . Str::random(8),
    ]);

    Message::create([
        'room_id' => $room->id,
        'participant_id' => null,
        'user_id' => $owner->id,
        'is_system' => false,
        'content' => 'Welcome',
    ]);

    $this->getJson(route('rooms.messages.history', $room))
        ->assertOk()
        ->assertJsonStructure(['data']);

    $this->getJson('/rooms/' . $room->id . '/messages')
        ->assertStatus(404);
});

test('banned identities can still read message history', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Read-only ban room',
        'slug' => 'room-' . Str::random(8),
    ]);

    Message::create([
        'room_id' => $room->id,
        'participant_id' => null,
        'user_id' => $owner->id,
        'is_system' => false,
        'content' => 'Hello banned reader',
    ]);

    RoomBan::create([
        'room_id' => $room->id,
        'session_token' => 'banned-token',
        'display_name' => 'Blocked',
        'ip_address' => '203.0.113.50',
        'fingerprint' => 'fp-ban',
    ]);

    $this
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
        ->withCookie('lc_fp', 'fp-ban')
        ->getJson(route('rooms.messages.history', $room))
        ->assertOk()
        ->assertJsonPath('data.0.content', 'Hello banned reader');
});
