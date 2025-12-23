<?php

use App\Models\AuditLog;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Str;

test('admin page lists recent audit logs', function () {
    config(['app.admin_allowed_ips' => '10.0.0.1']);

    $user = User::factory()->create();
    $user->forceFill(['is_dev' => true])->save();

    $room = Room::create([
        'user_id' => $user->id,
        'title' => 'Audit display room',
        'slug' => Str::random(8),
    ]);

    AuditLog::create([
        'action' => 'room.delete',
        'actor_user_id' => $user->id,
        'room_id' => $room->id,
        'target_type' => 'room',
        'target_id' => $room->id,
        'ip_address' => '10.0.0.1',
    ]);

    $response = $this
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->actingAs($user)
        ->get('/admin');

    $response->assertStatus(200);
    $response->assertSee('room.delete');
    $response->assertSee('Audit display room');
});
