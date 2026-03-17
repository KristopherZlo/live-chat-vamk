<?php

use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Str;

test('room host private channel authorizes owner and dev users', function () {
    $owner = User::factory()->create();
    $dev = User::factory()->create(['is_dev' => true]);
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Realtime room',
        'slug' => 'room-'.Str::random(8),
    ]);

    expect(Room::canAccessHostChannel($owner, $room->slug))->toBeTrue();
    expect(Room::canAccessHostChannel($dev, $room->slug))->toBeTrue();
});

test('room host private channel rejects other owners and requires auth on the endpoint', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $room = Room::create([
        'user_id' => $owner->id,
        'title' => 'Protected realtime room',
        'slug' => 'room-'.Str::random(8),
    ]);

    $payload = [
        'channel_name' => 'private-room.host.'.$room->slug,
        'socket_id' => '1234.5678',
    ];
    $headers = ['X-Requested-With' => 'XMLHttpRequest'];

    expect(Room::canAccessHostChannel($intruder, $room->slug))->toBeFalse();

    $this->post('/broadcasting/auth', $payload, $headers)
        ->assertRedirect(route('login'));
});
