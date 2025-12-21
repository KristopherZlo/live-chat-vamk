<?php

use App\Models\Participant;
use App\Models\Room;
use App\Models\RoomBan;
use App\Models\User;
use Illuminate\Support\Str;

test('owner chat view includes host tools', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Host room',
        'slug' => Str::random(8),
    ]);

    $this->actingAs($owner)
        ->get(route('rooms.public', $room->slug))
        ->assertOk()
        ->assertSee('class="quick-responses"', false)
        ->assertSee('class="quick-responses__poll"', false)
        ->assertSee('class="quick-responses__settings"', false)
        ->assertSee('class="poll-composer"', false)
        ->assertSee('data-chat-tab="bans"', false);
});

test('participant chat view includes question toggle and hides host tools', function () {
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

    $sessionKey = 'room_participant_' . $room->id;

    $this->withSession([$sessionKey => $participant->id])
        ->get(route('rooms.public', $room->slug))
        ->assertOk()
        ->assertSee('id="sendToTeacher"', false)
        ->assertDontSee('class="quick-responses"', false)
        ->assertDontSee('class="poll-composer"', false);
});

test('banned identities cannot open the room page', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Ban room',
        'slug' => Str::random(8),
    ]);

    RoomBan::create([
        'room_id' => $room->id,
        'session_token' => 'banned-session',
        'display_name' => 'Banned',
        'ip_address' => '203.0.113.55',
        'fingerprint' => 'fp-banned',
    ]);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.55'])
        ->withCookie('lc_fp', 'fp-banned')
        ->get(route('rooms.public', $room->slug))
        ->assertStatus(403);
});

test('closed private rooms are blocked for participants but visible to owners', function () {
    $owner = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Closed room',
        'slug' => Str::random(8),
        'status' => 'finished',
        'is_public_read' => false,
    ]);

    $this->get(route('rooms.public', $room->slug))
        ->assertStatus(403);

    $this->actingAs($owner)
        ->get(route('rooms.public', $room->slug))
        ->assertOk()
        ->assertSee('data-room-slug="' . $room->slug . '"', false);
});
