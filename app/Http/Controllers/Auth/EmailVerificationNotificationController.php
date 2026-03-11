<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\EmailVerificationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request, EmailVerificationCodeService $verificationCodes): RedirectResponse
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        $validated = $request->validate([
            'resend_token' => ['required', 'string', 'size:64'],
        ]);

        $sessionToken = (string) $request->session()->pull('verification_resend_token', '');
        if ($sessionToken === '' || ! hash_equals($sessionToken, (string) $validated['resend_token'])) {
            throw ValidationException::withMessages([
                'code' => ['Verification session expired. Reload the page and try again.'],
            ]);
        }

        $waitSeconds = $verificationCodes->resendCooldownRemaining($user);
        if ($waitSeconds > 0) {
            return back()
                ->withErrors(['code' => ["Please wait {$waitSeconds}s before requesting a new code."]])
                ->withInput();
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'verification-code-sent');
    }
}
