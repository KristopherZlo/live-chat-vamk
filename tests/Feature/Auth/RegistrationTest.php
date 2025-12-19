<?php

use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $invite = \App\Models\InviteCode::create([
        'code' => 'TESTCODE123',
    ]);

    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'invite_code' => $invite->code,
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'test@example.com')->first();
    expect($user)->not->toBeNull();
    $invite->refresh();
    expect($invite->used_by)->toBe($user->id);
    expect($invite->used_at)->not->toBeNull();
});
