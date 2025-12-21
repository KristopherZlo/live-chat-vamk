<?php

use App\Models\Message;
use App\Models\Participant;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Str;

test('reaction rejects invalid emoji', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Reaction room',
        'slug' => Str::random(8),
    ]);

    $message = Message::create([
        'room_id' => $room->id,
        'participant_id' => null,
        'user_id' => $owner->id,
        'is_system' => false,
        'content' => 'React',
    ]);

    $route = route('rooms.messages.reactions.toggle', [$room, $message]);

    $this->actingAs($owner)
        ->postJson($route, ['emoji' => 'abc'])
        ->assertStatus(422);
});

test('reactions are blocked when the room is closed', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Closed room',
        'slug' => Str::random(8),
        'status' => 'finished',
    ]);

    $message = Message::create([
        'room_id' => $room->id,
        'participant_id' => null,
        'user_id' => $owner->id,
        'is_system' => false,
        'content' => 'Closed',
    ]);

    $route = route('rooms.messages.reactions.toggle', [$room, $message]);

    $this->actingAs($owner)
        ->postJson($route, ['emoji' => "\u{1F44D}"])
        ->assertStatus(403);
});

test('participants need a session to react', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Session room',
        'slug' => Str::random(8),
    ]);

    $message = Message::create([
        'room_id' => $room->id,
        'participant_id' => null,
        'user_id' => $owner->id,
        'is_system' => false,
        'content' => 'React here',
    ]);

    $participant = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Guest',
    ]);

    $route = route('rooms.messages.reactions.toggle', [$room, $message]);

    $this->postJson($route, ['emoji' => "\u{1F44D}"])
        ->assertStatus(403);

    $sessionKey = 'room_participant_' . $room->id;
    $this->withSession([$sessionKey => $participant->id])
        ->postJson($route, ['emoji' => "\u{1F44D}"])
        ->assertOk();
});
