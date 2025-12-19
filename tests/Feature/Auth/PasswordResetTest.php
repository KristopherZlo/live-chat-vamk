<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;

test('password reset routes are disabled', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->get('/forgot-password')->assertStatus(404);
    $this->post('/forgot-password', ['email' => $user->email])->assertStatus(404);
    $this->get('/reset-password/test-token')->assertStatus(404);
    $this->post('/reset-password', [
        'token' => 'test-token',
        'email' => $user->email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertStatus(404);

    Notification::assertNothingSent();
});
