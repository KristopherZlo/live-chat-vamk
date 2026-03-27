<?php

namespace App\Services\Auth;

use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\Auth\VerifyEmailCodeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class EmailVerificationCodeService
{
    private const CODE_LENGTH = 6;

    public function send(User $user): string
    {
        $code = $this->generateCode();
        $now = now();
        $existingRecord = EmailVerificationCode::query()
            ->where('user_id', $user->id)
            ->first();
        $previousState = $existingRecord?->getAttributes();

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

        try {
            $user->notify(new VerifyEmailCodeNotification($code, $this->ttlMinutes()));
        } catch (TransportExceptionInterface $e) {
            $this->restorePreviousCodeState($user, $previousState);
            report($e);

            throw EmailVerificationDeliveryException::fromTransport($e);
        }

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

        return (int) now()->diffInSeconds($nextSendAt);
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

    /**
     * @param  array<string, mixed>|null  $previousState
     */
    private function restorePreviousCodeState(User $user, ?array $previousState): void
    {
        $table = (new EmailVerificationCode)->getTable();

        if ($previousState === null) {
            DB::table($table)
                ->where('user_id', $user->id)
                ->delete();

            return;
        }

        DB::table($table)->updateOrInsert(
            ['user_id' => $user->id],
            [
                'code_hash' => $previousState['code_hash'],
                'expires_at' => $previousState['expires_at'],
                'created_at' => $previousState['created_at'] ?? now(),
                'updated_at' => $previousState['updated_at'] ?? now(),
            ],
        );
    }
}
