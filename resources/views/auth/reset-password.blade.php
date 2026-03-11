<x-guest-layout>
    <div class="auth-page auth-page--reset">
        <section class="auth-layout" aria-label="Reset password">
            <aside class="auth-visual">
                <img
                    src="{{ asset('assets/auth/ghostroom-login-page.webp') }}"
                    alt="Ghost Room illustration"
                    loading="lazy"
                >
            </aside>

            <main class="auth-panel">
                <div class="auth-panel-inner auth-panel-inner--utility">
                    <a class="auth-logo" href="{{ url('/') }}" aria-label="Ghost Room home">
                        <img src="{{ asset('assets/ghostup_logo.svg') }}" alt="Ghost Room logo">
                    </a>

                    <h1 class="auth-title">Set a new password</h1>
                    <p class="auth-subtitle">Create a new password for your account.</p>

                    <form method="POST" action="{{ route('password.store') }}" class="auth-form">
                        @csrf
                        <input type="hidden" name="token" value="{{ $request->route('token') }}">

                        <label class="auth-field" for="email">
                            <span>Email</span>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email', $request->email) }}"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="mail@example.com"
                            >
                            <x-input-error :messages="$errors->get('email')" class="auth-input-error" />
                        </label>

                        <label class="auth-field" for="password">
                            <span>Password</span>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                required
                                autocomplete="new-password"
                                placeholder="Create password"
                            >
                            <x-input-error :messages="$errors->get('password')" class="auth-input-error" />
                        </label>

                        <label class="auth-field" for="password_confirmation">
                            <span>Confirm password</span>
                            <input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                required
                                autocomplete="new-password"
                                placeholder="Repeat password"
                            >
                            <x-input-error :messages="$errors->get('password_confirmation')" class="auth-input-error" />
                        </label>

                        <button class="auth-submit" type="submit">Reset password</button>
                    </form>

                    <p class="auth-bottom">
                        <a href="{{ route('login') }}">Back to login</a>
                    </p>
                </div>
            </main>
        </section>
    </div>
</x-guest-layout>
