@php
    $mode = ($mode ?? 'login') === 'register' ? 'register' : 'login';
    $isLogin = $mode === 'login';
    $title = $isLogin ? 'Login to your Account' : 'Create your Account';
    $subtitle = $isLogin
        ? 'See what is going on with your rooms and audience.'
        : 'Use your email and confirm your account after signup.';
@endphp

<x-guest-layout>
    <div class="auth-page {{ $isLogin ? 'auth-page--login' : 'auth-page--register' }}">
        <section class="auth-layout" aria-label="Account access">
            <aside class="auth-visual">
                <img
                    src="{{ asset('assets/auth/ghostroom-login-page.webp') }}"
                    alt="Illustration placeholder"
                    loading="lazy"
                >
            </aside>

            <main class="auth-panel">
                <div class="auth-panel-inner">
                    <a class="auth-logo" href="{{ url('/') }}" aria-label="Ghost Room home">
                        <img src="{{ asset('assets/ghostup_logo.svg') }}" alt="Ghost Room logo">
                    </a>

                    <h1 class="auth-title">{{ $title }}</h1>
                    <p class="auth-subtitle">{{ $subtitle }}</p>

                    @if($isLogin)
                        <button class="auth-google" type="button" aria-disabled="true" disabled>
                            <span class="auth-google-mark" aria-hidden="true">G</span>
                            <span>Continue with Google</span>
                        </button>
                        <p class="auth-divider"><span>or sign in with email</span></p>
                    @endif

                    <x-auth-session-status class="auth-status" :status="session('status')" />

                    @if($isLogin)
                        <form method="POST" action="{{ route('login') }}" class="auth-form">
                            @csrf
                            <div class="auth-honeypot" aria-hidden="true">
                                <label for="login_website">Website</label>
                                <input id="login_website" type="text" name="website" tabindex="-1" autocomplete="off">
                            </div>
                            <input type="hidden" name="form_started_at" value="{{ now()->timestamp }}">

                            <label class="auth-field" for="login_email">
                                <span>Email</span>
                                <input
                                    id="login_email"
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

                            <label class="auth-field" for="login_password">
                                <span>Password</span>
                                <input
                                    id="login_password"
                                    type="password"
                                    name="password"
                                    required
                                    autocomplete="current-password"
                                    placeholder="Enter your password"
                                >
                                <x-input-error :messages="$errors->get('password')" class="auth-input-error" />
                            </label>

                            <div class="auth-meta">
                                <label class="auth-remember" for="remember_me">
                                    <input id="remember_me" type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                                    <span>Remember me</span>
                                </label>
                                @if (Route::has('password.request'))
                                    <a class="auth-link" href="{{ route('password.request') }}">Forgot Password?</a>
                                @endif
                            </div>

                            <button class="auth-submit" type="submit">Login</button>
                        </form>

                        <p class="auth-bottom">
                            <span>Not Registered Yet?</span>
                            <a href="{{ route('register') }}">Create an account</a>
                        </p>
                    @else
                        <form method="POST" action="{{ route('register') }}" class="auth-form">
                            @csrf
                            <div class="auth-honeypot" aria-hidden="true">
                                <label for="register_website">Website</label>
                                <input id="register_website" type="text" name="website" tabindex="-1" autocomplete="off">
                            </div>
                            <input type="hidden" name="form_started_at" value="{{ now()->timestamp }}">

                            <label class="auth-field" for="register_name">
                                <span>Name</span>
                                <input
                                    id="register_name"
                                    type="text"
                                    name="name"
                                    value="{{ old('name') }}"
                                    required
                                    autofocus
                                    autocomplete="name"
                                    placeholder="Nickname"
                                >
                                <x-input-error :messages="$errors->get('name')" class="auth-input-error" />
                            </label>

                            <label class="auth-field" for="register_email">
                                <span>Email</span>
                                <input
                                    id="register_email"
                                    type="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    required
                                    autocomplete="username"
                                    placeholder="mail@example.com"
                                >
                                <x-input-error :messages="$errors->get('email')" class="auth-input-error" />
                            </label>

                            <label class="auth-field" for="register_password">
                                <span>Password</span>
                                <input
                                    id="register_password"
                                    type="password"
                                    name="password"
                                    required
                                    autocomplete="new-password"
                                    placeholder="Create password"
                                >
                                <x-input-error :messages="$errors->get('password')" class="auth-input-error" />
                            </label>

                            <label class="auth-field" for="register_password_confirmation">
                                <span>Confirm password</span>
                                <input
                                    id="register_password_confirmation"
                                    type="password"
                                    name="password_confirmation"
                                    required
                                    autocomplete="new-password"
                                    placeholder="Repeat password"
                                >
                                <x-input-error :messages="$errors->get('password_confirmation')" class="auth-input-error" />
                            </label>

                            <button class="auth-submit" type="submit">Create account</button>
                        </form>

                        <p class="auth-bottom">
                            <span>Already have an account?</span>
                            <a href="{{ route('login') }}">Login</a>
                        </p>
                    @endif
                </div>
            </main>
        </section>
    </div>
</x-guest-layout>
