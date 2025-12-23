<?php

use App\Models\InviteCode;
use App\Models\Participant;
use App\Models\Room;
use App\Models\RoomBan;
use App\Models\Setting;
use App\Models\UpdatePost;
use App\Models\User;
use Illuminate\Support\Str;

test('admin invite actions are audited', function () {
    config(['app.admin_allowed_ips' => '10.0.0.1']);

    $user = User::factory()->create();
    $user->forceFill(['is_dev' => true])->save();

    $response = $this
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->actingAs($user)
        ->post(route('admin.invites.store'), [
            'code' => 'INVITE-ABC123',
        ]);

    $response->assertStatus(302);
    $invite = InviteCode::where('code', 'INVITE-ABC123')->first();
    expect($invite)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'admin.invite.create',
        'actor_user_id' => $user->id,
        'target_type' => 'invite_code',
        'target_id' => $invite->id,
    ]);

    $response = $this
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->actingAs($user)
        ->delete(route('admin.invites.destroy', $invite));

    $response->assertStatus(302);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'admin.invite.delete',
        'actor_user_id' => $user->id,
        'target_type' => 'invite_code',
        'target_id' => $invite->id,
    ]);
});

test('admin ban actions are audited', function () {
    config(['app.admin_allowed_ips' => '10.0.0.1']);

    $user = User::factory()->create();
    $user->forceFill(['is_dev' => true])->save();

    $room = Room::create([
        'user_id' => $user->id,
        'title' => 'Admin ban room',
        'slug' => Str::random(8),
    ]);
    $participant = Participant::create([
        'room_id' => $room->id,
        'session_token' => 'ban-session',
        'display_name' => 'Guest',
    ]);

    $response = $this
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->actingAs($user)
        ->post(route('admin.bans.store'), [
            'room_id' => $room->id,
            'participant_id' => $participant->id,
            'display_name' => $participant->display_name,
        ]);

    $response->assertStatus(302);
    $ban = RoomBan::first();
    expect($ban)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'admin.ban.create',
        'actor_user_id' => $user->id,
        'target_type' => 'room_ban',
        'target_id' => $ban->id,
    ]);

    $response = $this
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->actingAs($user)
        ->delete(route('admin.bans.destroy', $ban));

    $response->assertStatus(302);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'admin.ban.delete',
        'actor_user_id' => $user->id,
        'target_type' => 'room_ban',
        'target_id' => $ban->id,
    ]);
});

test('admin update posts and version changes are audited', function () {
    config(['app.admin_allowed_ips' => '10.0.0.1']);

    $user = User::factory()->create();
    $user->forceFill(['is_dev' => true])->save();

    $response = $this
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->actingAs($user)
        ->post(route('admin.updates.posts.store'), [
            'title' => 'Blog post',
            'excerpt' => 'Excerpt',
            'body' => 'Blog body',
            'is_published' => true,
        ]);

    $response->assertStatus(302);
    $blog = UpdatePost::where('type', UpdatePost::TYPE_BLOG)->first();
    expect($blog)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'admin.blog.create',
        'actor_user_id' => $user->id,
        'target_type' => 'update_post',
        'target_id' => $blog->id,
    ]);

    $response = $this
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->actingAs($user)
        ->patch(route('admin.updates.posts.update', $blog), [
            'title' => 'Blog post updated',
            'excerpt' => 'Updated excerpt',
            'body' => 'Updated body',
            'is_published' => true,
        ]);

    $response->assertStatus(302);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'admin.blog.update',
        'actor_user_id' => $user->id,
        'target_type' => 'update_post',
        'target_id' => $blog->id,
    ]);

    $response = $this
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->actingAs($user)
        ->delete(route('admin.updates.posts.destroy', $blog));

    $response->assertStatus(302);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'admin.blog.delete',
        'actor_user_id' => $user->id,
        'target_type' => 'update_post',
        'target_id' => $blog->id,
    ]);

    $response = $this
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->actingAs($user)
        ->post(route('admin.updates.releases.store'), [
            'title' => 'Release 1.0',
            'body' => 'Release body',
            'version' => '1.0.0',
            'is_published' => true,
        ]);

    $response->assertStatus(302);
    $release = UpdatePost::where('type', UpdatePost::TYPE_WHATS_NEW)->first();
    expect($release)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'admin.release.create',
        'actor_user_id' => $user->id,
        'target_type' => 'update_post',
        'target_id' => $release->id,
    ]);

    $response = $this
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->actingAs($user)
        ->patch(route('admin.updates.releases.update', $release), [
            'title' => 'Release 1.0.1',
            'body' => 'Release body updated',
            'version' => '1.0.1',
            'is_published' => true,
        ]);

    $response->assertStatus(302);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'admin.release.update',
        'actor_user_id' => $user->id,
        'target_type' => 'update_post',
        'target_id' => $release->id,
    ]);

    $response = $this
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->actingAs($user)
        ->delete(route('admin.updates.releases.destroy', $release));

    $response->assertStatus(302);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'admin.release.delete',
        'actor_user_id' => $user->id,
        'target_type' => 'update_post',
        'target_id' => $release->id,
    ]);

    $response = $this
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->actingAs($user)
        ->post(route('admin.updates.version'), [
            'version' => '2.0.0',
        ]);

    $response->assertStatus(302);
    $setting = Setting::where('key', 'app_version')->first();
    expect($setting)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'admin.version.update',
        'actor_user_id' => $user->id,
        'target_type' => 'setting',
        'target_id' => $setting->id,
    ]);
});
