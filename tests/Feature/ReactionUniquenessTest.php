<?php

use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\Participant;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

test('users keep a single reaction per message', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Reaction room',
        'slug' => Str::random(10),
    ]);

    $message = Message::create([
        'room_id' => $room->id,
        'participant_id' => null,
        'user_id' => $owner->id,
        'is_system' => false,
        'content' => 'React here',
    ]);

    $route = route('rooms.messages.reactions.toggle', [$room, $message]);
    $emojiOne = "\u{1F44D}";
    $emojiTwo = "\u{1F525}";

    $csrfToken = 'csrf-token';

    $this->withSession(['_token' => $csrfToken])
        ->actingAs($owner)
        ->postJson($route, ['emoji' => $emojiOne, '_token' => $csrfToken])
        ->assertOk();
    $this->withSession(['_token' => $csrfToken])
        ->actingAs($owner)
        ->postJson($route, ['emoji' => $emojiTwo, '_token' => $csrfToken])
        ->assertOk();

    expect(MessageReaction::where('message_id', $message->id)->where('user_id', $owner->id)->count())->toBe(1);
    expect(MessageReaction::where('message_id', $message->id)->where('user_id', $owner->id)->value('emoji'))->toBe($emojiTwo);
});

test('reaction table enforces one reaction per actor', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Uniq reactions',
        'slug' => Str::random(10),
    ]);

    $message = Message::create([
        'room_id' => $room->id,
        'participant_id' => null,
        'user_id' => $owner->id,
        'is_system' => false,
        'content' => 'Unique',
    ]);

    MessageReaction::create([
        'message_id' => $message->id,
        'user_id' => $owner->id,
        'participant_id' => null,
        'emoji' => "\u{1F389}",
    ]);

    $this->expectException(QueryException::class);

    MessageReaction::create([
        'message_id' => $message->id,
        'user_id' => $owner->id,
        'participant_id' => null,
        'emoji' => "\u{1F4A5}",
    ]);
});

test('participants keep a single reaction per message', function () {
    $room = Room::create([
        'user_id' => User::factory()->create()->id,
        'title' => 'Participant reactions',
        'slug' => Str::random(10),
    ]);

    $participant = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Guest',
    ]);

    $message = Message::create([
        'room_id' => $room->id,
        'participant_id' => $participant->id,
        'user_id' => null,
        'is_system' => false,
        'content' => 'Participant reacts',
    ]);

    MessageReaction::create([
        'message_id' => $message->id,
        'user_id' => null,
        'participant_id' => $participant->id,
        'emoji' => "\u{1F44F}",
    ]);

    $this->expectException(QueryException::class);

    MessageReaction::create([
        'message_id' => $message->id,
        'user_id' => null,
        'participant_id' => $participant->id,
        'emoji' => "\u{1F64C}",
    ]);
});
