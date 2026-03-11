<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.login-register', ['mode' => 'register']);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->pruneStaleUnverifiedUsers();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:' . config('ghostroom.limits.user.name_max', 255)],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:' . config('ghostroom.limits.user.email_max', 255),
                'unique:' . User::class,
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'website' => ['nullable', 'string', 'max:0'],
            'form_started_at' => ['required', 'integer', 'min:1'],
        ]);

        $this->ensureHoneypotIsValid((int) $validated['form_started_at']);
        $registrationIp = $this->resolveClientIp($request);
        $this->ensurePendingUnverifiedLimitNotReached($registrationIp);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'registration_ip' => $registrationIp,
        ]);

        event(new Registered($user));

        Auth::login($user);

        $request->session()->flash('onboarding_new_user', true);

        $redirectTo = route('verification.notice');

        if ($request->expectsJson()) {
            return response()->json([
                'redirect' => $redirectTo,
            ]);
        }

        return redirect($redirectTo);
    }

    private function ensureHoneypotIsValid(int $startedAt): void
    {
        $elapsed = now()->timestamp - $startedAt;

        if ($elapsed < 2) {
            throw ValidationException::withMessages([
                'email' => ['Please try submitting the form again.'],
            ]);
        }
    }

    private function pruneStaleUnverifiedUsers(): void
    {
        $ttlHours = max(1, (int) config('ghostroom.auth.unverified_user_ttl_hours', 24));

        User::query()
            ->whereNull('email_verified_at')
            ->where('created_at', '<', now()->subHours($ttlHours))
            ->delete();
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    private function ensurePendingUnverifiedLimitNotReached(string $registrationIp): void
    {
        $maxPending = max(1, (int) config('ghostroom.auth.max_pending_unverified_per_ip', 3));

        $pendingCount = User::query()
            ->whereNull('email_verified_at')
            ->where('registration_ip', $registrationIp)
            ->count();

        if ($pendingCount >= $maxPending) {
            throw ValidationException::withMessages([
                'email' => ['Too many unverified accounts were created from this network. Verify one of them before creating a new account.'],
            ]);
        }
    }

    private function resolveClientIp(Request $request): string
    {
        return (string) ($request->ip() ?: '0.0.0.0');
    }
}
