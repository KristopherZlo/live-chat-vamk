<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Auth\EmailVerificationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login-register', ['mode' => 'login']);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, EmailVerificationCodeService $verificationCodes): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();
        if ($user instanceof User && ! $user->hasVerifiedEmail()) {
            $currentCode = $user->emailVerificationCode()->first();
            if (! $currentCode || $currentCode->isExpired() || $verificationCodes->resendCooldownRemaining($user) === 0) {
                $user->sendEmailVerificationNotification();
            }

            return redirect()->route('verification.notice');
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
