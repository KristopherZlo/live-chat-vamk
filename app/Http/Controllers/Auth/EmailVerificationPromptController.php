<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
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

        $currentCode = $user->emailVerificationCode()->first();
        if (! $currentCode || $currentCode->isExpired()) {
            $verificationCodes->send($user);
        }

        $resendToken = Str::random(64);
        $request->session()->put('verification_resend_token', $resendToken);

        return view('auth.verify-email', [
            'resendCooldownSeconds' => $verificationCodes->resendCooldownRemaining($user),
            'resendToken' => $resendToken,
        ]);
    }
}
