<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\EmailVerificationDeliveryException;
use App\Services\Auth\EmailVerificationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EmailVerificationPromptController extends Controller
{
    /**
     * Display the email verification prompt.
     */
    public function __invoke(Request $request, EmailVerificationCodeService $verificationCodes): RedirectResponse|View
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->to(route('home'));
        }

        $mailDeliveryError = null;
        $skipAutoSend = (bool) $request->session()->pull('verification_mail_failed', false);
        $currentCode = $user->emailVerificationCode()->first();
        if ((! $currentCode || $currentCode->isExpired()) && ! $skipAutoSend) {
            try {
                $verificationCodes->send($user);
            } catch (EmailVerificationDeliveryException $e) {
                $mailDeliveryError = $e->getMessage();
            }
        }

        $resendToken = Str::random(64);
        $request->session()->put('verification_resend_token', $resendToken);

        return view('auth.verify-email', [
            'mailDeliveryError' => $mailDeliveryError,
            'resendCooldownSeconds' => $verificationCodes->resendCooldownRemaining($user),
            'resendToken' => $resendToken,
        ]);
    }
}
