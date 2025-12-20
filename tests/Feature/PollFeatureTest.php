<?php

use App\Models\Message;
use App\Models\MessagePoll;
use App\Models\MessagePollVote;
use App\Models\Participant;
use App\Models\Room;
use App\Models\RoomBan;
use App\Models\User;
use Illuminate\Support\Str;

test('room owner can create polls and receives option ids', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Poll room',
        'slug' => Str::random(8),
    ]);

    $response = $this
        ->actingAs($owner)
        ->postJson(route('rooms.messages.store', $room), [
            'content' => 'Pick one',
            'poll_mode' => 1,
            'poll_options' => ['Alpha', 'Beta'],
        ]);

    $response
        ->assertStatus(201)
        ->assertJsonStructure([
            'message_id',
            'poll' => [
                'id',
                'question',
                'options' => [
                    ['id', 'label', 'votes', 'percent'],
                ],
                'total_votes',
                'my_vote_id',
                'is_closed',
            ],
        ])
        ->assertJsonPath('poll.question', 'Pick one');

    $this->assertDatabaseHas('messages', [
        'room_id' => $room->id,
        'content' => 'Pick one',
        'user_id' => $owner->id,
    ]);

    $poll = MessagePoll::where('question', 'Pick one')->first();
    expect($poll)->not->toBeNull();
    expect($poll->options()->count())->toBe(2);

    $pollOptions = $response->json('poll.options');
    expect($pollOptions)->toHaveCount(2);
    foreach ($pollOptions as $option) {
        $this->assertDatabaseHas('message_poll_options', [
            'id' => $option['id'],
            'poll_id' => $poll->id,
            'label' => $option['label'],
        ]);
    }

    $this->assertDatabaseMissing('questions', [
        'message_id' => $response->json('message_id'),
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
            'content' => 'Should fail',
            'poll_mode' => 1,
            'poll_options' => ['One', 'Two'],
        ]);

    $response->assertStatus(403);
    $this->assertDatabaseMissing('message_polls', ['question' => 'Should fail']);
});

test('polls require at least two options', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Poll room',
        'slug' => Str::random(8),
    ]);

    $response = $this
        ->actingAs($owner)
        ->postJson(route('rooms.messages.store', $room), [
            'content' => 'Too few',
            'poll_mode' => 1,
            'poll_options' => ['Only one'],
        ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('message', 'Add at least two poll options.');
});

test('polls are limited to six options', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Poll room',
        'slug' => Str::random(8),
    ]);

    $response = $this
        ->actingAs($owner)
        ->postJson(route('rooms.messages.store', $room), [
            'content' => 'Too many',
            'poll_mode' => 1,
            'poll_options' => ['1', '2', '3', '4', '5', '6', '7'],
        ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('message', 'Polls can have up to 6 options.');
});

test('poll question length is limited', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Poll room',
        'slug' => Str::random(8),
    ]);

    $response = $this
        ->actingAs($owner)
        ->postJson(route('rooms.messages.store', $room), [
            'content' => Str::random(256),
            'poll_mode' => 1,
            'poll_options' => ['Alpha', 'Beta'],
        ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('message', 'Poll question cannot exceed 255 characters.');
});

test('participants can vote and update their vote', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Poll room',
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

    $optionOne = $poll->options()->create([
        'label' => 'Alpha',
        'position' => 0,
    ]);
    $optionTwo = $poll->options()->create([
        'label' => 'Beta',
        'position' => 1,
    ]);

    $participant = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Guest',
    ]);

    $sessionKey = 'room_participant_' . $room->id;
    $voteRoute = route('rooms.polls.vote', [$room, $poll]);

    $first = $this
        ->withSession([$sessionKey => $participant->id])
        ->postJson($voteRoute, ['option_id' => $optionOne->id]);

    $first
        ->assertOk()
        ->assertJsonPath('status', 'voted')
        ->assertJsonPath('your_vote_id', $optionOne->id);

    $second = $this
        ->withSession([$sessionKey => $participant->id])
        ->postJson($voteRoute, ['option_id' => $optionTwo->id]);

    $second
        ->assertOk()
        ->assertJsonPath('your_vote_id', $optionTwo->id);

    expect(MessagePollVote::where('poll_id', $poll->id)->where('participant_id', $participant->id)->count())->toBe(1);
    expect((int) MessagePollVote::where('poll_id', $poll->id)->where('participant_id', $participant->id)->value('option_id'))
        ->toBe($optionTwo->id);
});

test('banned participants cannot vote in polls', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Poll room',
        'slug' => Str::random(8),
    ]);

    $message = Message::create([
        'room_id' => $room->id,
        'participant_id' => null,
        'user_id' => $owner->id,
        'is_system' => false,
        'content' => 'Vote here',
    ]);

    $poll = MessagePoll::create([
        'message_id' => $message->id,
        'question' => 'Vote here',
        'is_closed' => false,
    ]);

    $option = $poll->options()->create([
        'label' => 'Alpha',
        'position' => 0,
    ]);

    $participant = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Banned',
        'ip_address' => '203.0.113.99',
        'fingerprint' => 'fp-blocked',
    ]);

    RoomBan::create([
        'room_id' => $room->id,
        'participant_id' => $participant->id,
        'session_token' => $participant->session_token,
        'display_name' => $participant->display_name,
        'ip_address' => $participant->ip_address,
        'fingerprint' => $participant->fingerprint,
    ]);

    $sessionKey = 'room_participant_' . $room->id;

    $response = $this
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
        ->withCookie('lc_fp', 'fp-blocked')
        ->withSession([$sessionKey => $participant->id])
        ->postJson(route('rooms.polls.vote', [$room, $poll]), [
            'option_id' => $option->id,
        ]);

    $response->assertStatus(403);
    $this->assertDatabaseMissing('message_poll_votes', [
        'poll_id' => $poll->id,
        'participant_id' => $participant->id,
    ]);
});

test('closed polls reject votes', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Poll room',
        'slug' => Str::random(8),
    ]);

    $message = Message::create([
        'room_id' => $room->id,
        'participant_id' => null,
        'user_id' => $owner->id,
        'is_system' => false,
        'content' => 'Closed poll',
    ]);

    $poll = MessagePoll::create([
        'message_id' => $message->id,
        'question' => 'Closed poll',
        'is_closed' => true,
    ]);

    $option = $poll->options()->create([
        'label' => 'Alpha',
        'position' => 0,
    ]);

    $response = $this
        ->actingAs($owner)
        ->postJson(route('rooms.polls.vote', [$room, $poll]), [
            'option_id' => $option->id,
        ]);

    $response
        ->assertStatus(403)
        ->assertJsonPath('message', 'This poll is closed.');
});
