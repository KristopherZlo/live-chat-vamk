<?php

use App\Events\ParticipantBanned;
use App\Events\ParticipantUnbanned;
use App\Events\PollUpdated;
use App\Events\QuestionCreated;
use App\Events\QuestionUpdated;
use App\Events\ReactionUpdated;
use App\Models\Participant;
use App\Models\Question;
use App\Models\Room;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Str;

test('question and moderation events broadcast on the private host channel', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Host-only room',
        'slug' => 'room-'.Str::random(8),
    ]);

    $participant = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Guest',
    ]);

    $question = Question::create([
        'room_id' => $room->id,
        'participant_id' => $participant->id,
        'content' => 'Host-only question',
        'status' => 'new',
    ]);

    $questionCreated = new QuestionCreated($question);
    $questionUpdated = new QuestionUpdated($question);
    $participantBanned = new ParticipantBanned($room->id, $room->slug, 1, $participant->id, 'Guest');
    $participantUnbanned = new ParticipantUnbanned($room->id, $room->slug, 1, $participant->id);

    expect($questionCreated->broadcastOn())->toBeInstanceOf(PrivateChannel::class);
    expect($questionCreated->broadcastOn()->name)->toBe('private-room.host.'.$room->slug);
    expect($questionUpdated->broadcastOn())->toBeInstanceOf(PrivateChannel::class);
    expect($questionUpdated->broadcastOn()->name)->toBe('private-room.host.'.$room->slug);
    expect($participantBanned->broadcastOn())->toBeInstanceOf(PrivateChannel::class);
    expect($participantBanned->broadcastOn()->name)->toBe('private-room.host.'.$room->slug);
    expect($participantUnbanned->broadcastOn())->toBeInstanceOf(PrivateChannel::class);
    expect($participantUnbanned->broadcastOn()->name)->toBe('private-room.host.'.$room->slug);
});

test('public reaction events no longer leak actor or viewer-specific state', function () {
    $event = new ReactionUpdated(1, 'room-slug', 55, [
        ['emoji' => "\u{1F44D}", 'count' => 3],
    ]);

    expect($event->broadcastOn()->name)->toBe('room.room-slug');
    expect($event->broadcastWith())->toBe([
        'room_id' => 1,
        'message_id' => 55,
        'reactions' => [
            ['emoji' => "\u{1F44D}", 'count' => 3],
        ],
    ]);
});

test('public poll events no longer leak actor or viewer-specific state', function () {
    $pollPayload = [
        'id' => 9,
        'question' => 'Pick one',
        'options' => [
            ['id' => 1, 'label' => 'A', 'votes' => 2, 'percent' => 100],
        ],
        'total_votes' => 2,
        'my_vote_id' => null,
        'is_closed' => false,
    ];

    $event = new PollUpdated(1, 'room-slug', 77, 9, $pollPayload);

    expect($event->broadcastOn()->name)->toBe('room.room-slug');
    expect($event->broadcastWith())->toBe([
        'room_id' => 1,
        'message_id' => 77,
        'poll_id' => 9,
        'poll' => $pollPayload,
    ]);
});
