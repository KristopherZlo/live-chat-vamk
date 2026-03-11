<x-guest-layout>
    <div class="auth-page auth-page--confirm">
        <section class="auth-layout" aria-label="Confirm password">
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

                    <h1 class="auth-title">Confirm your password</h1>
                    <p class="auth-subtitle">This is a secure area. Enter your current password to continue.</p>

                    <form method="POST" action="{{ route('password.confirm') }}" class="auth-form">
                        @csrf

                        <label class="auth-field" for="password">
                            <span>Password</span>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                placeholder="Enter your current password"
                            >
                            <x-input-error :messages="$errors->get('password')" class="auth-input-error" />
                        </label>

                        <button class="auth-submit" type="submit">Confirm password</button>
                    </form>

                    <p class="auth-bottom">
                        <a href="{{ route('home') }}">Back to homepage</a>
                    </p>
                </div>
            </main>
        </section>
    </div>
</x-guest-layout>
