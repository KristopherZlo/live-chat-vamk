<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\InviteCode;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
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
            'invite_code' => [
                'required',
                'string',
                Rule::exists('invite_codes', 'code')->whereNull('used_at'),
            ],
        ]);

        $user = null;

        DB::transaction(function () use (&$user, $validated) {
            $invite = InviteCode::where('code', $validated['invite_code'])
                ->whereNull('used_at')
                ->lockForUpdate()
                ->first();

            if (! $invite) {
                throw ValidationException::withMessages([
                    'invite_code' => ['This invite code is invalid or has already been used.'],
                ]);
            }

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $invite->forceFill([
                'used_by' => $user->id,
                'used_at' => now(),
            ])->save();
        });

        event(new Registered($user));

        Auth::login($user);

        $request->session()->flash('onboarding_new_user', true);

        return redirect(route('dashboard', absolute: false));
    }
}
