<?php

use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'website' => '',
        'form_started_at' => now()->subSeconds(3)->timestamp,
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('verification.notice'));

    $user = User::where('email', 'test@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user?->hasVerifiedEmail())->toBeFalse();
});

test('new users can register via async flow', function () {
    $response = $this->postJson('/register', [
        'name' => 'Async User',
        'email' => 'async@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'website' => '',
        'form_started_at' => now()->subSeconds(3)->timestamp,
    ]);

    $response->assertOk();
    $response->assertJsonPath('redirect', route('verification.notice'));
    $this->assertAuthenticated();

    $user = User::where('email', 'async@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user?->hasVerifiedEmail())->toBeFalse();
});

test('registration is blocked when too many unverified accounts exist for one ip', function () {
    config()->set('ghostroom.auth.max_pending_unverified_per_ip', 2);

    User::factory()->unverified()->count(2)->create([
        'registration_ip' => '127.0.0.1',
    ]);

    $response = $this
        ->from(route('register'))
        ->post('/register', [
            'name' => 'Blocked User',
            'email' => 'blocked@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'website' => '',
            'form_started_at' => now()->subSeconds(3)->timestamp,
        ]);

    $response->assertRedirect(route('register'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('stale unverified users are pruned before registration', function () {
    config()->set('ghostroom.auth.max_pending_unverified_per_ip', 1);
    config()->set('ghostroom.auth.unverified_user_ttl_hours', 24);

    $staleUser = User::factory()->unverified()->create([
        'registration_ip' => '127.0.0.1',
        'created_at' => now()->subHours(25),
        'updated_at' => now()->subHours(25),
    ]);

    $response = $this->post('/register', [
        'name' => 'Fresh User',
        'email' => 'fresh@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'website' => '',
        'form_started_at' => now()->subSeconds(3)->timestamp,
    ]);

    $response->assertRedirect(route('verification.notice'));
    expect(User::query()->whereKey($staleUser->id)->exists())->toBeFalse();
    $this->assertAuthenticated();
});

test('registration endpoint is throttled per ip', function () {
    config()->set('ghostroom.limits.auth.register_per_minute_ip', 1);
    config()->set('ghostroom.limits.auth.register_per_hour_ip', 1000);
    config()->set('ghostroom.limits.auth.register_per_hour_subnet', 1000);

    $firstResponse = $this
        ->from(route('register'))
        ->post('/register', []);

    $firstResponse->assertStatus(302);

    $secondResponse = $this
        ->from(route('register'))
        ->post('/register', []);

    $secondResponse->assertStatus(429);
});
