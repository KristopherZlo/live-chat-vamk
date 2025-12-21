<?php

use App\Models\Participant;
use App\Models\Question;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Str;

test('owner can load the questions panel with filters', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Queue room',
        'slug' => Str::random(8),
    ]);

    $participant = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Guest',
    ]);

    $newQuestion = Question::create([
        'room_id' => $room->id,
        'participant_id' => $participant->id,
        'content' => 'First question',
        'status' => 'new',
    ]);

    $answeredQuestion = Question::create([
        'room_id' => $room->id,
        'participant_id' => $participant->id,
        'content' => 'Second question',
        'status' => 'answered',
        'answered_at' => now(),
    ]);

    $response = $this->actingAs($owner)->get(route('rooms.questionsPanel', [
        'room' => $room,
        'status' => 'new',
    ]));

    $response
        ->assertOk()
        ->assertSee('Question queue', false)
        ->assertSee($newQuestion->content, false)
        ->assertDontSee($answeredQuestion->content, false);
});

test('non-owner cannot access the questions panel', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Queue room',
        'slug' => Str::random(8),
    ]);

    $response = $this->actingAs($intruder)->get(route('rooms.questionsPanel', $room));

    $response->assertStatus(403);
});

test('questions chunk returns html and paging metadata', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Chunked queue room',
        'slug' => Str::random(8),
    ]);

    $participant = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Guest',
    ]);

    $firstQuestion = Question::create([
        'room_id' => $room->id,
        'participant_id' => $participant->id,
        'content' => 'First chunk question',
        'status' => 'new',
    ]);

    Question::create([
        'room_id' => $room->id,
        'participant_id' => $participant->id,
        'content' => 'Second chunk question',
        'status' => 'new',
    ]);

    $response = $this->actingAs($owner)->getJson(route('rooms.questions.chunk', [
        'room' => $room,
        'offset' => 0,
        'limit' => 1,
    ]));

    $response
        ->assertOk()
        ->assertJsonPath('has_more', true)
        ->assertJsonPath('next_offset', 1);

    $html = $response->json('html');
    expect($html)->toContain($firstQuestion->content);
});

test('question items batch returns html for requested ids', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Batch queue room',
        'slug' => Str::random(8),
    ]);

    $participant = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Guest',
    ]);

    $question = Question::create([
        'room_id' => $room->id,
        'participant_id' => $participant->id,
        'content' => 'Batch question',
        'status' => 'new',
    ]);

    $response = $this->actingAs($owner)->getJson(route('rooms.questions.batch', [
        'room' => $room,
        'ids' => [$question->id, 999999],
    ]));

    $response
        ->assertOk()
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.id', $question->id);

    $html = $response->json('items.0.html');
    expect($html)->toContain($question->content);
});

test('question item rejects mismatched rooms', function () {
    $owner = User::factory()->create();
    $roomA = Room::create([
        'user_id' => $owner->id,
        'title' => 'Room A',
        'slug' => Str::random(8),
    ]);
    $roomB = Room::create([
        'user_id' => $owner->id,
        'title' => 'Room B',
        'slug' => Str::random(8),
    ]);

    $participant = Participant::create([
        'room_id' => $roomA->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Guest',
    ]);

    $question = Question::create([
        'room_id' => $roomA->id,
        'participant_id' => $participant->id,
        'content' => 'Mismatched question',
        'status' => 'new',
    ]);

    $response = $this->actingAs($owner)->get(route('rooms.questions.item', [
        'room' => $roomB,
        'question' => $question,
    ]));

    $response->assertStatus(403);
});

test('participants can load the my questions panel', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Participant room',
        'slug' => Str::random(8),
    ]);

    $participant = Participant::create([
        'room_id' => $room->id,
        'session_token' => (string) Str::uuid(),
        'display_name' => 'Guest',
    ]);

    $question = Question::create([
        'room_id' => $room->id,
        'participant_id' => $participant->id,
        'content' => 'My question',
        'status' => 'new',
    ]);

    $sessionKey = 'room_participant_' . $room->id;
    $response = $this->withSession([$sessionKey => $participant->id])
        ->get(route('rooms.myQuestionsPanel', $room));

    $response
        ->assertOk()
        ->assertSee('My questions', false)
        ->assertSee($question->content, false);
});

test('owners cannot access the my questions panel', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Owner room',
        'slug' => Str::random(8),
    ]);

    $response = $this->actingAs($owner)->get(route('rooms.myQuestionsPanel', $room));

    $response->assertStatus(403);
});
