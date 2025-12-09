<x-guest-layout>
    <div class="login-background" aria-hidden="true">
        <div class="login-blur login-blur-1"></div>
        <div class="login-blur login-blur-2"></div>
        <div class="login-blur login-blur-3"></div>
    </div>

    <div class="login-modal">
        <div class="login-modal-split">
            <div class="login-modal-content">
                <div class="login-logo-wrap">
                    <img src="{{ asset('assets/ghostup_logo_white.svg') }}" alt="Ghost Room" class="login-logo">
                </div>

                <div class="login-text">
                    <h2>Welcome back</h2>
                    <p>Sign in to continue your journey</p>
                </div>

                <x-auth-session-status class="login-status" :status="session('status')" />

                <form method="POST" action="{{ route('login') }}" class="login-form">
                    @csrf

                    <div class="login-field">
                        <label for="email">Email</label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="you@example.com">
                        <x-input-error :messages="$errors->get('email')" class="login-input-error" />
                    </div>

                    <div class="login-field">
                        <label for="password">Password</label>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="********">
                        <x-input-error :messages="$errors->get('password')" class="login-input-error" />
                    </div>

                    <div class="login-form-meta">
                        <label for="remember_me" class="login-remember">
                            <input id="remember_me" type="checkbox" name="remember">
                            <span class="login-checkmark" aria-hidden="true"></span>
                            <span>Stay signed in</span>
                        </label>

                    </div>

                    <button class="login-submit" type="submit">
                        Log in
                    </button>
                </form>
            </div>

            <div class="login-modal-illustration" aria-hidden="true">
                <svg viewBox="0 0 400 500" class="login-illustration"></svg>
            </div>
        </div>
    </div>
</x-guest-layout>
