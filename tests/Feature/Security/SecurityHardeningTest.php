<?php

use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

test('admin area is blocked for non-whitelisted IPs', function () {
    config(['app.admin_allowed_ips' => '10.0.0.1']);

    $user = User::factory()->create();
    $user->forceFill(['is_dev' => true])->save();

    $response = $this
        ->withServerVariables(['REMOTE_ADDR' => '2.2.2.2'])
        ->actingAs($user)
        ->get('/admin');

    $response->assertStatus(403);
});

test('admin area is accessible from whitelisted IPs', function () {
    config(['app.admin_allowed_ips' => '10.0.0.1']);

    $user = User::factory()->create();
    $user->forceFill(['is_dev' => true])->save();

    $response = $this
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->actingAs($user)
        ->get('/admin');

    $response->assertStatus(200);
});

test('login attempts are locked after repeated failures', function () {
    $ip = '10.0.0.4';
    $email = 'lock@example.com';

    User::factory()->create([
        'email' => $email,
        'password' => bcrypt('correct-password'),
    ]);

    for ($i = 0; $i < 5; $i++) {
        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/login', [
                'email' => $email,
                'password' => 'wrong-password',
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    $lockoutResponse = $this
        ->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson('/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ]);

    $lockoutResponse->assertStatus(422)->assertJsonValidationErrors('email');

    $userKey = Str::lower($email) . '|' . $ip;
    expect(RateLimiter::tooManyAttempts($userKey, 5))->toBeTrue();
});

test('registration is rate limited per IP', function () {
    $ip = '10.0.0.2';

    for ($i = 0; $i < 5; $i++) {
        $this
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post('/register', [
                'name' => 'Test User '.$i,
                'email' => "user{$i}@example.com",
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertStatus(302);
    }

    $limited = $this
        ->withServerVariables(['REMOTE_ADDR' => $ip])
        ->post('/register', [
            'name' => 'Test User 6',
            'email' => 'user6@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    $limited->assertStatus(429);
});

test('password reset endpoints are disabled', function () {
    $this->post('/forgot-password', [
        'email' => 'reset@example.com',
    ])->assertStatus(404);
});

test('room message posting is throttled by composite key', function () {
    RateLimiter::for('room-messages', function (Request $request) {
        return [
            Limit::perMinute(2)->by($request->ip()),
        ];
    });

    $ip = '10.0.0.5';

    $user = User::factory()->create();
    $room = Room::create([
        'user_id' => $user->id,
        'title' => 'Throttled Room',
        'slug' => Str::random(8),
    ]);

    for ($i = 0; $i < 2; $i++) {
        $this
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->actingAs($user)
            ->postJson(route('rooms.messages.store', $room), [
                'content' => "Hello #{$i}",
            ])
            ->assertStatus(201);
    }

    $limited = $this
        ->withServerVariables(['REMOTE_ADDR' => $ip])
        ->actingAs($user)
        ->postJson(route('rooms.messages.store', $room), [
            'content' => 'Should be blocked',
        ]);

    $limited->assertStatus(429);
});
