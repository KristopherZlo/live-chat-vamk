<x-guest-layout>
    <div class="login-background" aria-hidden="true">
        <div class="login-blur login-blur-1"></div>
        <div class="login-blur login-blur-2"></div>
        <div class="login-blur login-blur-3"></div>
    </div>

    <div class="login-modal register-modal">
        <div class="login-modal-split">
            <div class="login-modal-content">
                <div class="login-logo-wrap">
                    <img src="{{ asset('assets/ghostup_logo_white.svg') }}" alt="Ghost Room" class="login-logo">
                </div>

                <div class="login-text">
                    <h2>Create your account</h2>
                    <p>Access Ghost Room with a personal invite code.</p>
                </div>

                <form method="POST" action="{{ route('register') }}" class="login-form">
                    @csrf

                    <div class="login-field">
                        <label for="name">Name</label>
                        <input
                            id="name"
                            type="text"
                            name="name"
                            value="{{ old('name') }}"
                            required
                            autofocus
                            autocomplete="name"
                            placeholder="Your name">
                        <x-input-error :messages="$errors->get('name')" class="login-input-error" />
                    </div>

                    <div class="login-field">
                        <label for="email">Email</label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            autocomplete="username"
                            placeholder="you@example.com">
                        <x-input-error :messages="$errors->get('email')" class="login-input-error" />
                    </div>

                    <div class="login-field">
                        <label for="invite_code">Invite code</label>
                        <input
                            id="invite_code"
                            type="text"
                            name="invite_code"
                            value="{{ old('invite_code') }}"
                            required
                            placeholder="One-time access code">
                        <x-input-error :messages="$errors->get('invite_code')" class="login-input-error" />
                    </div>

                    <div class="login-field">
                        <label for="password">Password</label>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            required
                            autocomplete="new-password"
                            placeholder="Create a password">
                        <x-input-error :messages="$errors->get('password')" class="login-input-error" />
                    </div>

                    <div class="login-field">
                        <label for="password_confirmation">Confirm password</label>
                        <input
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            required
                            autocomplete="new-password"
                            placeholder="Repeat your password">
                        <x-input-error :messages="$errors->get('password_confirmation')" class="login-input-error" />
                    </div>

                    <div class="login-form-meta register-meta">
                        <p class="login-muted">You’ll need a valid one-time invite code to complete signup.</p>
                        <a class="login-link" href="{{ route('login') }}">Already have an account? Log in</a>
                    </div>

                    <button class="login-submit" type="submit">
                        Create account
                    </button>
                </form>
            </div>

            <div class="login-modal-illustration" aria-hidden="true">
                <svg viewBox="0 0 400 500" class="login-illustration"></svg>
            </div>
        </div>
    </div>
</x-guest-layout>
