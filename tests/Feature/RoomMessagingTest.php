<?php

use App\Models\Message;
use App\Models\Participant;
use App\Models\Question;
use App\Models\Room;
use App\Models\RoomBan;
use App\Models\User;
use Illuminate\Support\Str;

test('room owner can post a message and mark it as question', function () {
    $user = User::factory()->create();
    $room = Room::create([
        'user_id' => $user->id,
        'title' => 'Test room',
        'slug' => Str::random(8),
    ]);

    $response = $this->actingAs($user)->post(route('rooms.messages.store', $room), [
        'content' => 'Hello students',
        'as_question' => 1,
    ]);

    $response->assertRedirect(route('rooms.public', $room->slug));

    $this->assertDatabaseHas('messages', [
        'room_id' => $room->id,
        'content' => 'Hello students',
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseHas('questions', [
        'room_id' => $room->id,
        'content' => 'Hello students',
        'user_id' => $user->id,
        'status' => 'new',
    ]);
});

test('banned participant cannot send messages', function () {
    $roomOwner = User::factory()->create();
    $room = Room::create([
        'user_id' => $roomOwner->id,
        'title' => 'Lecture 101',
        'slug' => Str::random(8),
    ]);

    $participant = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Anon',
    ]);

    RoomBan::create([
        'room_id' => $room->id,
        'participant_id' => $participant->id,
        'session_token' => $participant->session_token,
        'display_name' => $participant->display_name,
    ]);

    $response = $this->withSession(['room_participant_' . $room->id => $participant->id])
        ->post(route('rooms.messages.store', $room), [
            'content' => 'Let me in',
        ]);

    $response->assertStatus(302)->assertSessionHasErrors();
    $this->assertDatabaseMissing('messages', ['content' => 'Let me in']);
    $this->assertDatabaseMissing('questions', ['content' => 'Let me in']);
});
