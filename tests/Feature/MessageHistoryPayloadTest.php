<?php

use App\Models\Message;
use App\Models\MessagePoll;
use App\Models\MessagePollVote;
use App\Models\MessageReaction;
use App\Models\Participant;
use App\Models\Room;
use App\Models\RoomBan;
use App\Models\User;
use Illuminate\Support\Str;

test('message history returns reaction summaries and my reactions', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Reaction history room',
        'slug' => Str::random(8),
    ]);

    $message = Message::create([
        'room_id' => $room->id,
        'participant_id' => null,
        'user_id' => $owner->id,
        'is_system' => false,
        'content' => 'Hello reactions',
    ]);

    $participantOne = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Guest one',
    ]);

    $participantTwo = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Guest two',
    ]);

    $emojiOne = "\u{1F44D}";
    $emojiTwo = "\u{1F389}";

    MessageReaction::create([
        'message_id' => $message->id,
        'participant_id' => $participantOne->id,
        'emoji' => $emojiOne,
    ]);

    MessageReaction::create([
        'message_id' => $message->id,
        'participant_id' => $participantTwo->id,
        'emoji' => $emojiOne,
    ]);

    MessageReaction::create([
        'message_id' => $message->id,
        'user_id' => $owner->id,
        'emoji' => $emojiTwo,
    ]);

    $response = $this->actingAs($owner)->getJson(route('rooms.messages.history', $room));

    $response
        ->assertOk()
        ->assertJsonPath('data.0.reactions.0.emoji', $emojiOne)
        ->assertJsonPath('data.0.reactions.0.count', 2)
        ->assertJsonPath('data.0.reactions.1.emoji', $emojiTwo)
        ->assertJsonPath('data.0.reactions.1.count', 1)
        ->assertJsonFragment(['myReactions' => [$emojiTwo]]);
});

test('message history includes poll payload and my vote', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Poll history room',
        'slug' => Str::random(8),
    ]);

    $message = Message::create([
        'room_id' => $room->id,
        'participant_id' => null,
        'user_id' => $owner->id,
        'is_system' => false,
        'content' => 'Poll message',
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

    $otherParticipant = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Guest two',
    ]);

    MessagePollVote::create([
        'poll_id' => $poll->id,
        'option_id' => $optionOne->id,
        'participant_id' => $participant->id,
    ]);

    MessagePollVote::create([
        'poll_id' => $poll->id,
        'option_id' => $optionTwo->id,
        'participant_id' => $otherParticipant->id,
    ]);

    $sessionKey = 'room_participant_' . $room->id;
    $response = $this->withSession([$sessionKey => $participant->id])
        ->getJson(route('rooms.messages.history', $room));

    $response
        ->assertOk()
        ->assertJsonPath('data.0.poll.id', $poll->id)
        ->assertJsonPath('data.0.poll.total_votes', 2)
        ->assertJsonPath('data.0.poll.my_vote_id', $optionOne->id)
        ->assertJsonPath('data.0.poll.options.0.id', $optionOne->id)
        ->assertJsonPath('data.0.poll.options.0.votes', 1)
        ->assertJsonPath('data.0.poll.options.1.id', $optionTwo->id)
        ->assertJsonPath('data.0.poll.options.1.votes', 1);
});

test('message history hides banned participant messages for others', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Ban filter room',
        'slug' => Str::random(8),
    ]);

    $participant = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Banned participant',
    ]);

    $viewer = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Viewer',
    ]);

    Message::create([
        'room_id' => $room->id,
        'participant_id' => $participant->id,
        'user_id' => null,
        'is_system' => false,
        'content' => 'Hidden message',
    ]);

    Message::create([
        'room_id' => $room->id,
        'participant_id' => null,
        'user_id' => $owner->id,
        'is_system' => false,
        'content' => 'Visible message',
    ]);

    RoomBan::create([
        'room_id' => $room->id,
        'participant_id' => $participant->id,
        'session_token' => $participant->session_token,
        'display_name' => $participant->display_name,
    ]);

    $sessionKey = 'room_participant_' . $room->id;

    $response = $this->withSession([$sessionKey => $viewer->id])
        ->getJson(route('rooms.messages.history', $room));

    $response
        ->assertOk()
        ->assertJsonMissing(['content' => 'Hidden message'])
        ->assertJsonFragment(['content' => 'Visible message']);

    $selfResponse = $this->withSession([$sessionKey => $participant->id])
        ->getJson(route('rooms.messages.history', $room));

    $selfResponse
        ->assertOk()
        ->assertJsonFragment(['content' => 'Hidden message']);
});
