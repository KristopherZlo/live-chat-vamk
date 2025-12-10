<?php

use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\Participant;
use App\Models\Room;
use App\Models\RoomBan;
use App\Models\User;
use Illuminate\Support\Str;

test('participants with banned fingerprint or ip cannot post messages', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Identity-ban room',
        'slug' => Str::random(8),
    ]);

    $bannedParticipant = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'First visitor',
        'ip_address' => '203.0.113.10',
        'fingerprint' => 'fp-blocked',
    ]);

    RoomBan::create([
        'room_id' => $room->id,
        'participant_id' => $bannedParticipant->id,
        'session_token' => $bannedParticipant->session_token,
        'display_name' => $bannedParticipant->display_name,
        'ip_address' => '203.0.113.10',
        'fingerprint' => 'fp-blocked',
    ]);

    $newParticipant = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'New visitor',
        'ip_address' => '203.0.113.10',
        'fingerprint' => 'fp-blocked',
    ]);

    $sessionKey = 'room_participant_' . $room->id;

    $response = $this
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withCookie('lc_fp', 'fp-blocked')
        ->withSession([$sessionKey => $newParticipant->id])
        ->postJson(route('rooms.messages.store', $room), [
            'content' => 'Should be blocked',
        ]);

    $response->assertStatus(403);
    $this->assertDatabaseMissing('messages', ['content' => 'Should be blocked']);
});

test('reactions toggle and update for the same actor', function () {
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
        'content' => 'Hello world',
    ]);

    $route = route('rooms.messages.reactions.toggle', [$room, $message]);

    $added = $this->actingAs($owner)->postJson($route, ['emoji' => '👍']);
    $added->assertOk()->assertJsonPath('status', 'added');
    $this->assertDatabaseHas('message_reactions', [
        'message_id' => $message->id,
        'user_id' => $owner->id,
        'emoji' => '👍',
    ]);

    $removed = $this->actingAs($owner)->postJson($route, ['emoji' => '👍']);
    $removed->assertOk()->assertJsonPath('status', 'removed');
    $this->assertDatabaseMissing('message_reactions', [
        'message_id' => $message->id,
        'user_id' => $owner->id,
    ]);

    $readded = $this->actingAs($owner)->postJson($route, ['emoji' => '🔥']);
    $readded->assertOk()->assertJsonPath('status', 'added');

    $updated = $this->actingAs($owner)->postJson($route, ['emoji' => '🙏']);
    $updated->assertOk()->assertJsonPath('status', 'updated');

    $this->assertDatabaseHas('message_reactions', [
        'message_id' => $message->id,
        'user_id' => $owner->id,
        'emoji' => '🙏',
    ]);
    expect(MessageReaction::where('message_id', $message->id)->where('user_id', $owner->id)->count())->toBe(1);
});

