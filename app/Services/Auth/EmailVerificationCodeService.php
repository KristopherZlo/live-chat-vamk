<?php

namespace App\Services\Auth;

use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\Auth\VerifyEmailCodeNotification;
use Illuminate\Support\Facades\Hash;

class EmailVerificationCodeService
{
    private const CODE_LENGTH = 6;

    public function send(User $user): string
    {
        $code = $this->generateCode();
        $now = now();

        EmailVerificationCode::query()->upsert(
            [[
                'user_id' => $user->id,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes($this->ttlMinutes()),
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['user_id'],
            ['code_hash', 'expires_at', 'updated_at'],
        );

        $user->notify(new VerifyEmailCodeNotification($code, $this->ttlMinutes()));

        return $code;
    }

    public function verify(User $user, string $code): bool
    {
        $verification = EmailVerificationCode::where('user_id', $user->id)->first();

        if (! $verification) {
            return false;
        }

        if ($verification->isExpired()) {
            $verification->delete();

            return false;
        }

        if (! Hash::check($code, $verification->code_hash)) {
            return false;
        }

        $verification->delete();

        return true;
    }

    public function resendCooldownRemaining(User $user): int
    {
        $verification = EmailVerificationCode::query()
            ->where('user_id', $user->id)
            ->first();

        if (! $verification || ! $verification->updated_at) {
            return 0;
        }

        $nextSendAt = $verification->updated_at->copy()->addSeconds($this->resendCooldownSeconds());
        if ($nextSendAt->isPast()) {
            return 0;
        }

        return now()->diffInSeconds($nextSendAt);
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    private function ttlMinutes(): int
    {
        return max(1, (int) config('ghostroom.auth.email_verification_code_ttl', 15));
    }

    private function resendCooldownSeconds(): int
    {
        return max(1, (int) config('ghostroom.auth.verification_resend_cooldown_seconds', 60));
    }
}
