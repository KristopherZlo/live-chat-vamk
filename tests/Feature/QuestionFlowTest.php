<?php

use App\Models\Message;
use App\Models\Participant;
use App\Models\Question;
use App\Models\QuestionRating;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Str;

test('owner can update question status', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Queue room',
        'slug' => Str::random(8),
    ]);

    $question = Question::create([
        'room_id' => $room->id,
        'message_id' => null,
        'participant_id' => null,
        'user_id' => $owner->id,
        'content' => 'Status check',
        'status' => 'new',
    ]);

    $this->actingAs($owner)
        ->post(route('questions.updateStatus', $question), ['status' => 'answered'])
        ->assertRedirect();

    $question->refresh();
    expect($question->status)->toBe('answered');
    expect($question->answered_at)->not->toBeNull();
    expect($question->ignored_at)->toBeNull();
});

test('participants cannot update question status', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Queue room',
        'slug' => Str::random(8),
    ]);

    $question = Question::create([
        'room_id' => $room->id,
        'message_id' => null,
        'participant_id' => null,
        'user_id' => $owner->id,
        'content' => 'No edit',
        'status' => 'new',
    ]);

    $this->actingAs($intruder)
        ->post(route('questions.updateStatus', $question), ['status' => 'answered'])
        ->assertStatus(403);
});

test('participant can rate answered questions', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Ratings room',
        'slug' => Str::random(8),
    ]);

    $participant = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Rater',
    ]);

    $message = Message::create([
        'room_id' => $room->id,
        'participant_id' => $participant->id,
        'user_id' => null,
        'is_system' => false,
        'content' => 'Question content',
    ]);

    $question = Question::create([
        'room_id' => $room->id,
        'message_id' => $message->id,
        'participant_id' => $participant->id,
        'user_id' => null,
        'content' => 'Question content',
        'status' => 'answered',
        'answered_at' => now(),
    ]);

    $sessionKey = 'room_participant_' . $room->id;

    $this->withSession([$sessionKey => $participant->id])
        ->post(route('questions.rate', $question), ['rating' => 1])
        ->assertRedirect();

    $this->assertDatabaseHas('question_ratings', [
        'question_id' => $question->id,
        'participant_id' => $participant->id,
        'rating' => 1,
    ]);
});

test('participant rating requires answered status', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Ratings room',
        'slug' => Str::random(8),
    ]);

    $participant = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Rater',
    ]);

    $question = Question::create([
        'room_id' => $room->id,
        'message_id' => null,
        'participant_id' => $participant->id,
        'user_id' => null,
        'content' => 'Question content',
        'status' => 'new',
    ]);

    $sessionKey = 'room_participant_' . $room->id;

    $this->withSession([$sessionKey => $participant->id])
        ->post(route('questions.rate', $question), ['rating' => -1])
        ->assertStatus(403);
});

test('participants can delete their own questions', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Delete room',
        'slug' => Str::random(8),
    ]);

    $participant = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Author',
    ]);

    $question = Question::create([
        'room_id' => $room->id,
        'message_id' => null,
        'participant_id' => $participant->id,
        'user_id' => null,
        'content' => 'Delete me',
        'status' => 'new',
    ]);

    $sessionKey = 'room_participant_' . $room->id;

    $this->withSession([$sessionKey => $participant->id])
        ->delete(route('questions.participantDelete', $question))
        ->assertRedirect();

    $question->refresh();
    expect($question->deleted_by_participant_at)->not->toBeNull();
});

test('owners can delete questions', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Delete room',
        'slug' => Str::random(8),
    ]);

    $question = Question::create([
        'room_id' => $room->id,
        'message_id' => null,
        'participant_id' => null,
        'user_id' => $owner->id,
        'content' => 'Owner delete',
        'status' => 'new',
    ]);

    $this->actingAs($owner)
        ->deleteJson(route('questions.ownerDelete', $question))
        ->assertOk()
        ->assertJson(['deleted' => true]);

    $question->refresh();
    expect($question->deleted_by_owner_at)->not->toBeNull();
});
