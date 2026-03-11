<?php

use App\Models\User;
use App\Notifications\Auth\VerifyEmailCodeNotification;
use Illuminate\Support\Facades\Notification;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200)
        ->assertSee('rel="icon"', false)
        ->assertSee('icons/logo_white.svg', false);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
        'website' => '',
        'form_started_at' => now()->subSeconds(3)->timestamp,
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard'));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
        'website' => '',
        'form_started_at' => now()->subSeconds(3)->timestamp,
    ]);

    $this->assertGuest();
});

test('unverified users are redirected to verification after login', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
        'website' => '',
        'form_started_at' => now()->subSeconds(3)->timestamp,
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('verification.notice'));
    Notification::assertSentTo($user, VerifyEmailCodeNotification::class);
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
