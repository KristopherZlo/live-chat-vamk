<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->to(route('home').'?verified=1');
        }

        if ($user->markEmailAsVerified()) {
            $user->emailVerificationCode()->delete();
            event(new Verified($user));
        }

        return redirect()->to(route('home').'?verified=1');
    }
}
