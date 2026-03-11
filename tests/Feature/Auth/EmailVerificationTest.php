<?php

use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\Auth\VerifyEmailCodeNotification;
use App\Services\Auth\EmailVerificationCodeService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get('/verify-email');

    $response->assertStatus(200);
});

test('email can be verified', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('home').'?verified=1');
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')]
    );

    $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('email can be verified by 6-digit code', function () {
    $user = User::factory()->unverified()->create();
    $code = app(EmailVerificationCodeService::class)->send($user);

    Event::fake();

    $response = $this
        ->actingAs($user)
        ->post(route('verification.code.verify'), [
            'code' => $code,
        ]);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    expect(EmailVerificationCode::where('user_id', $user->id)->exists())->toBeFalse();
    $response->assertRedirect(route('dashboard'));
});

test('invalid 6-digit code does not verify email', function () {
    $user = User::factory()->unverified()->create();
    app(EmailVerificationCodeService::class)->send($user);

    $response = $this
        ->actingAs($user)
        ->from(route('verification.notice'))
        ->post(route('verification.code.verify'), [
            'code' => '000000',
        ]);

    $response->assertRedirect(route('verification.notice'));
    $response->assertSessionHasErrors('code');
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('verification code cannot be resent before cooldown ends', function () {
    config()->set('ghostroom.auth.verification_resend_cooldown_seconds', 60);

    $user = User::factory()->unverified()->create();
    app(EmailVerificationCodeService::class)->send($user);

    Notification::fake();
    $resendToken = str_repeat('a', 64);

    $response = $this
        ->actingAs($user)
        ->withSession(['verification_resend_token' => $resendToken])
        ->from(route('verification.notice'))
        ->post(route('verification.send'), [
            'resend_token' => $resendToken,
        ]);

    $response->assertRedirect(route('verification.notice'));
    $response->assertSessionHasErrors('code');
    Notification::assertNothingSent();
});

test('verification code can be resent after cooldown ends', function () {
    config()->set('ghostroom.auth.verification_resend_cooldown_seconds', 60);

    $user = User::factory()->unverified()->create();
    app(EmailVerificationCodeService::class)->send($user);

    Notification::fake();
    $this->travel(61)->seconds();
    $resendToken = str_repeat('b', 64);

    $response = $this
        ->actingAs($user)
        ->withSession(['verification_resend_token' => $resendToken])
        ->from(route('verification.notice'))
        ->post(route('verification.send'), [
            'resend_token' => $resendToken,
        ]);

    $response->assertRedirect(route('verification.notice'));
    $response->assertSessionHas('status', 'verification-code-sent');
    Notification::assertSentTo($user, VerifyEmailCodeNotification::class);
});

test('verification code resend is rejected with invalid resend token', function () {
    $user = User::factory()->unverified()->create();
    app(EmailVerificationCodeService::class)->send($user);

    Notification::fake();

    $response = $this
        ->actingAs($user)
        ->withSession(['verification_resend_token' => str_repeat('c', 64)])
        ->from(route('verification.notice'))
        ->post(route('verification.send'), [
            'resend_token' => str_repeat('d', 64),
        ]);

    $response->assertRedirect(route('verification.notice'));
    $response->assertSessionHasErrors('code');
    Notification::assertNothingSent();
});
