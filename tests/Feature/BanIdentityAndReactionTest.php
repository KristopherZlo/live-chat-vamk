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

test('banned participant cannot react to messages', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Reaction ban room',
        'slug' => Str::random(8),
    ]);

    $participant = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Guest',
        'ip_address' => '203.0.113.10',
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

    $message = Message::create([
        'room_id' => $room->id,
        'participant_id' => null,
        'user_id' => $owner->id,
        'is_system' => false,
        'content' => 'React here',
    ]);

    $sessionKey = 'room_participant_' . $room->id;
    $route = route('rooms.messages.reactions.toggle', [$room, $message]);

    $response = $this
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withCookie('lc_fp', 'fp-blocked')
        ->withSession([$sessionKey => $participant->id])
        ->postJson($route, ['emoji' => "\u{1F44D}"]);

    $response->assertStatus(403);
    $this->assertDatabaseMissing('message_reactions', [
        'message_id' => $message->id,
        'participant_id' => $participant->id,
    ]);
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
