<?php

use App\Models\Message;
use App\Models\Participant;
use App\Models\Room;
use App\Models\RoomBan;
use App\Models\User;
use Illuminate\Support\Str;

test('owner can send a plain message', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Message room',
        'slug' => Str::random(8),
    ]);

    $response = $this->actingAs($owner)->postJson(route('rooms.messages.store', $room), [
        'content' => 'Hello students',
    ]);

    $response->assertStatus(201)->assertJsonPath('poll', null);
    $this->assertDatabaseHas('messages', [
        'room_id' => $room->id,
        'content' => 'Hello students',
        'user_id' => $owner->id,
    ]);
});

test('participant can send a question message', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Questions room',
        'slug' => Str::random(8),
    ]);

    $participant = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Guest',
    ]);

    $sessionKey = 'room_participant_' . $room->id;

    $response = $this
        ->withSession([$sessionKey => $participant->id])
        ->postJson(route('rooms.messages.store', $room), [
            'content' => 'Can you repeat that?',
            'as_question' => 1,
        ]);

    $response->assertStatus(201)->assertJsonPath('as_question', true);
    $this->assertDatabaseHas('questions', [
        'room_id' => $room->id,
        'content' => 'Can you repeat that?',
        'participant_id' => $participant->id,
    ]);
});

test('closed rooms reject new messages', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Closed room',
        'slug' => Str::random(8),
        'status' => 'finished',
    ]);

    $response = $this->actingAs($owner)->postJson(route('rooms.messages.store', $room), [
        'content' => 'Should fail',
    ]);

    $response->assertStatus(403);
    $this->assertDatabaseMissing('messages', ['content' => 'Should fail']);
});

test('owner can create a poll message', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Poll room',
        'slug' => Str::random(8),
    ]);

    $response = $this->actingAs($owner)->postJson(route('rooms.messages.store', $room), [
        'content' => 'Choose one',
        'poll_mode' => 1,
        'poll_options' => ['Alpha', 'Beta'],
        'as_question' => 1,
    ]);

    $response->assertStatus(201)->assertJsonPath('poll.question', 'Choose one');
    $this->assertDatabaseHas('message_polls', [
        'question' => 'Choose one',
    ]);
    $this->assertDatabaseMissing('questions', [
        'content' => 'Choose one',
    ]);
});

test('participants cannot create polls', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Poll room',
        'slug' => Str::random(8),
    ]);

    $participant = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Guest',
    ]);

    $sessionKey = 'room_participant_' . $room->id;

    $response = $this
        ->withSession([$sessionKey => $participant->id])
        ->postJson(route('rooms.messages.store', $room), [
            'content' => 'Not allowed',
            'poll_mode' => 1,
            'poll_options' => ['One', 'Two'],
        ]);

    $response->assertStatus(403);
    $this->assertDatabaseMissing('message_polls', ['question' => 'Not allowed']);
});

test('polls enforce option and question limits', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Poll room',
        'slug' => Str::random(8),
    ]);

    $this->actingAs($owner)
        ->postJson(route('rooms.messages.store', $room), [
            'content' => 'Too few',
            'poll_mode' => 1,
            'poll_options' => ['Only one'],
        ])
        ->assertStatus(422);

    $this->actingAs($owner)
        ->postJson(route('rooms.messages.store', $room), [
            'content' => Str::random(256),
            'poll_mode' => 1,
            'poll_options' => ['Alpha', 'Beta'],
        ])
        ->assertStatus(422);
});

test('reply target must belong to the same room', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Room A',
        'slug' => Str::random(8),
    ]);
    $otherRoom = Room::create([
        'user_id' => $owner->id,
        'title' => 'Room B',
        'slug' => Str::random(8),
    ]);

    $foreignMessage = Message::create([
        'room_id' => $otherRoom->id,
        'participant_id' => null,
        'user_id' => $owner->id,
        'is_system' => false,
        'content' => 'Not here',
    ]);

    $this->actingAs($owner)
        ->post(route('rooms.messages.store', $room), [
            'content' => 'Reply',
            'reply_to_id' => $foreignMessage->id,
        ])
        ->assertSessionHasErrors();
});

test('banned participants cannot send messages', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Ban room',
        'slug' => Str::random(8),
    ]);

    $participant = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Blocked',
    ]);

    RoomBan::create([
        'room_id' => $room->id,
        'participant_id' => $participant->id,
        'session_token' => $participant->session_token,
        'display_name' => $participant->display_name,
    ]);

    $sessionKey = 'room_participant_' . $room->id;

    $this->withSession([$sessionKey => $participant->id])
        ->postJson(route('rooms.messages.store', $room), [
            'content' => 'Nope',
        ])
        ->assertStatus(403);

    $this->assertDatabaseMissing('messages', ['content' => 'Nope']);
});
