<?php

use App\Models\Message;
use App\Models\Participant;
use App\Models\Question;
use App\Models\Room;
use App\Models\RoomBan;
use App\Models\User;
use Illuminate\Support\Str;

test('audit log records ban and unban actions', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Audit room',
        'slug' => Str::random(8),
    ]);
    $participant = Participant::create([
        'room_id' => $room->id,
        'session_token' => 'session-token',
        'display_name' => 'Guest',
    ]);

    $response = $this
        ->actingAs($owner)
        ->post(route('rooms.bans.store', $room), [
            'participant_id' => $participant->id,
        ]);

    $response->assertStatus(302);
    $ban = RoomBan::first();
    expect($ban)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'room.ban.create',
        'actor_user_id' => $owner->id,
        'room_id' => $room->id,
        'target_type' => 'room_ban',
        'target_id' => $ban->id,
    ]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('rooms.bans.destroy', [$room, $ban->id]));

    $response->assertStatus(302);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'room.ban.delete',
        'actor_user_id' => $owner->id,
        'room_id' => $room->id,
        'target_type' => 'room_ban',
        'target_id' => $ban->id,
    ]);
});

test('audit log records room lifecycle actions', function () {
    $owner = User::factory()->create();

    $response = $this
        ->actingAs($owner)
        ->post(route('rooms.store'), [
            'title' => 'New room',
            'description' => 'Description',
        ]);

    $response->assertStatus(302);
    $room = Room::first();
    expect($room)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'room.create',
        'actor_user_id' => $owner->id,
        'target_type' => 'room',
        'target_id' => $room->id,
    ]);

    $response = $this
        ->actingAs($owner)
        ->patch(route('rooms.update', $room), [
            'title' => 'Updated room',
        ]);

    $response->assertStatus(302);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'room.update',
        'actor_user_id' => $owner->id,
        'target_type' => 'room',
        'target_id' => $room->id,
    ]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('rooms.destroy', $room), [
            'confirm_title' => 'Updated room',
        ]);

    $response->assertStatus(302);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'room.delete',
        'actor_user_id' => $owner->id,
        'target_type' => 'room',
        'target_id' => $room->id,
    ]);
});

test('audit log records message deletions', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Message audit room',
        'slug' => Str::random(8),
    ]);
    $message = Message::create([
        'room_id' => $room->id,
        'user_id' => $owner->id,
        'content' => 'Delete me',
        'is_system' => false,
    ]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('rooms.messages.destroy', [$room, $message]));

    $response->assertStatus(302);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'message.delete',
        'actor_user_id' => $owner->id,
        'room_id' => $room->id,
        'target_type' => 'message',
        'target_id' => $message->id,
    ]);
});

test('audit log records question status updates', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Question audit room',
        'slug' => Str::random(8),
    ]);
    $question = Question::create([
        'room_id' => $room->id,
        'user_id' => $owner->id,
        'content' => 'Status audit',
        'status' => 'new',
    ]);

    $response = $this
        ->actingAs($owner)
        ->post(route('questions.updateStatus', $question), [
            'status' => 'answered',
        ]);

    $response->assertStatus(302);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'question.status.update',
        'actor_user_id' => $owner->id,
        'room_id' => $room->id,
        'target_type' => 'question',
        'target_id' => $question->id,
    ]);
});

test('audit log records question deletions by owner and participant', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Question delete room',
        'slug' => Str::random(8),
    ]);
    $participant = Participant::create([
        'room_id' => $room->id,
        'session_token' => 'participant-token',
        'display_name' => 'Guest',
    ]);

    $ownerQuestion = Question::create([
        'room_id' => $room->id,
        'user_id' => $owner->id,
        'content' => 'Owner delete',
        'status' => 'new',
    ]);

    $participantQuestion = Question::create([
        'room_id' => $room->id,
        'participant_id' => $participant->id,
        'content' => 'Participant delete',
        'status' => 'new',
    ]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('questions.ownerDelete', $ownerQuestion));

    $response->assertStatus(302);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'question.owner_delete',
        'actor_user_id' => $owner->id,
        'target_type' => 'question',
        'target_id' => $ownerQuestion->id,
    ]);

    $response = $this
        ->withSession(['room_participant_'.$room->id => $participant->id])
        ->delete(route('questions.participantDelete', $participantQuestion));

    $response->assertStatus(302);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'question.participant_delete',
        'actor_participant_id' => $participant->id,
        'target_type' => 'question',
        'target_id' => $participantQuestion->id,
    ]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('questions.destroy', $ownerQuestion));

    $response->assertStatus(302);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'question.delete',
        'actor_user_id' => $owner->id,
        'target_type' => 'question',
        'target_id' => $ownerQuestion->id,
    ]);
});
