<?php

use App\Models\Room;
use App\Models\RoomBan;
use App\Models\User;
use Illuminate\Support\Str;

test('non-dev users cannot access admin even from whitelisted IP', function () {
    config(['app.admin_allowed_ips' => '10.0.0.1']);

    $user = User::factory()->create([
        'is_dev' => false,
    ]);

    $response = $this
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->actingAs($user)
        ->get('/admin');

    $response->assertStatus(404);
});

test('room updates are blocked for non-owners', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Secure room',
        'slug' => Str::random(8),
    ]);

    $response = $this
        ->actingAs($intruder)
        ->patch(route('rooms.update', $room), [
            'title' => 'Hacked title',
        ]);

    $response->assertStatus(403);
    $this->assertDatabaseHas('rooms', [
        'id' => $room->id,
        'title' => 'Secure room',
    ]);
});

test('finished private room is not publicly viewable', function () {
    $owner = User::factory()->create();

    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Closed room',
        'slug' => 'closed-room',
        'status' => 'finished',
        'is_public_read' => false,
    ]);

    $response = $this->get(route('rooms.public', $room->slug));

    $response->assertStatus(403);
});

test('banned identity cannot view room', function () {
    $owner = User::factory()->create();

    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Identity protected room',
        'slug' => 'identity-protected',
    ]);

    RoomBan::create([
        'room_id' => $room->id,
        'session_token' => 'banned-token',
        'display_name' => 'Blocked visitor',
        'ip_address' => '203.0.113.50',
        'fingerprint' => 'fp-ban',
    ]);

    $response = $this
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
        ->withCookie('lc_fp', 'fp-ban')
        ->get(route('rooms.public', $room->slug));

    $response->assertStatus(403);
});

test('registration without invite code is rejected', function () {
    $response = $this->post('/register', [
        'name' => 'No Invite',
        'email' => 'noinvite@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'invite_code' => '',
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors('invite_code');
    $this->assertDatabaseMissing('users', ['email' => 'noinvite@example.com']);
});

test('participant cannot post without a bound session', function () {
    $owner = User::factory()->create();

    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Session required room',
        'slug' => Str::random(8),
    ]);

    $response = $this->post(route('rooms.messages.store', $room), [
        'content' => 'Should not be stored',
    ]);

    $response->assertStatus(302)->assertSessionHasErrors();
    $this->assertDatabaseMissing('messages', [
        'room_id' => $room->id,
        'content' => 'Should not be stored',
    ]);
});
