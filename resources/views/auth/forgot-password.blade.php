<x-guest-layout>
    <div class="auth-page auth-page--forgot">
        <section class="auth-layout" aria-label="Forgot password">
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

                    <h1 class="auth-title">Reset password</h1>
                    <p class="auth-subtitle">Enter your email and we will send a reset link.</p>

                    <x-auth-session-status class="auth-status" :status="session('status')" />

                    <form method="POST" action="{{ route('password.email') }}" class="auth-form">
                        @csrf

                        <label class="auth-field" for="email">
                            <span>Email</span>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="mail@example.com"
                            >
                            <x-input-error :messages="$errors->get('email')" class="auth-input-error" />
                        </label>

                        <button class="auth-submit" type="submit">Send reset link</button>
                    </form>

                    <p class="auth-bottom">
                        <a href="{{ route('login') }}">Back to login</a>
                    </p>
                </div>
            </main>
        </section>
    </div>
</x-guest-layout>
