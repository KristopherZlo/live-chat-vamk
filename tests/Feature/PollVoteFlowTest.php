<?php

use App\Models\Message;
use App\Models\MessagePoll;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Str;

test('host can vote in a poll', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Vote room',
        'slug' => Str::random(8),
    ]);

    $message = Message::create([
        'room_id' => $room->id,
        'participant_id' => null,
        'user_id' => $owner->id,
        'is_system' => false,
        'content' => 'Pick one',
    ]);

    $poll = MessagePoll::create([
        'message_id' => $message->id,
        'question' => 'Pick one',
        'is_closed' => false,
    ]);

    $option = $poll->options()->create([
        'label' => 'Alpha',
        'position' => 0,
    ]);

    $this->actingAs($owner)
        ->postJson(route('rooms.polls.vote', [$room, $poll]), [
            'option_id' => $option->id,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'voted');
});

test('poll vote validates option belongs to poll', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Vote room',
        'slug' => Str::random(8),
    ]);

    $message = Message::create([
        'room_id' => $room->id,
        'participant_id' => null,
        'user_id' => $owner->id,
        'is_system' => false,
        'content' => 'Pick',
    ]);

    $poll = MessagePoll::create([
        'message_id' => $message->id,
        'question' => 'Pick',
        'is_closed' => false,
    ]);

    $otherPoll = MessagePoll::create([
        'message_id' => Message::create([
            'room_id' => $room->id,
            'participant_id' => null,
            'user_id' => $owner->id,
            'is_system' => false,
            'content' => 'Other poll',
        ])->id,
        'question' => 'Other poll',
        'is_closed' => false,
    ]);

    $option = $otherPoll->options()->create([
        'label' => 'Other',
        'position' => 0,
    ]);

    $this->actingAs($owner)
        ->postJson(route('rooms.polls.vote', [$room, $poll]), [
            'option_id' => $option->id,
        ])
        ->assertStatus(422);
});

test('poll votes are blocked when room is closed', function () {
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
        'content' => 'Pick',
    ]);

    $poll = MessagePoll::create([
        'message_id' => $message->id,
        'question' => 'Pick',
        'is_closed' => false,
    ]);

    $option = $poll->options()->create([
        'label' => 'Alpha',
        'position' => 0,
    ]);

    $this->actingAs($owner)
        ->postJson(route('rooms.polls.vote', [$room, $poll]), [
            'option_id' => $option->id,
        ])
        ->assertStatus(403);
});
