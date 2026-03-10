@php
    $mode = ($mode ?? 'login') === 'register' ? 'register' : 'login';
    $isLogin = $mode === 'login';
@endphp

<x-guest-layout>
    <div class="auth-shell" data-auth-shell data-auth-mode="{{ $mode }}">
        <section class="auth-card" aria-label="Account access">
            <aside class="auth-brand">
                <a class="auth-brand-logo" href="{{ url('/') }}" aria-label="Ghost Room home">
                    <img src="{{ asset('assets/ghostup_logo_white.svg') }}" alt="Ghost Room logo">
                </a>
                <p class="auth-brand-kicker">Ghost Room account</p>
                <h1>Access your rooms and moderation tools.</h1>
                <p class="auth-brand-copy">
                    Sign in to continue or register with an invite code.
                    Account access is limited to invited users.
                </p>
                <ul class="auth-brand-points">
                    <li>Join and manage live Q&amp;A rooms</li>
                    <li>Track moderation and queue activity</li>
                    <li>Use invite-only registration</li>
                </ul>
            </aside>

            <main class="auth-panel">
                <div class="auth-panel-head">
                    <p class="auth-eyebrow">Authentication</p>
                    <h2 data-auth-title>{{ $isLogin ? 'Sign in' : 'Create account' }}</h2>
                    <p class="auth-subtitle" data-auth-subtitle>
                        {{ $isLogin ? 'Use your existing account credentials.' : 'A valid invite code is required to create an account.' }}
                    </p>
                </div>

                <nav class="auth-switch" aria-label="Authentication mode" role="tablist">
                    <a
                        href="{{ route('login') }}"
                        class="auth-switch-tab {{ $isLogin ? 'is-active' : '' }}"
                        data-auth-switch="login"
                        role="tab"
                        aria-selected="{{ $isLogin ? 'true' : 'false' }}"
                    >
                        Log in
                    </a>
                    <a
                        href="{{ route('register') }}"
                        class="auth-switch-tab {{ $isLogin ? '' : 'is-active' }}"
                        data-auth-switch="register"
                        role="tab"
                        aria-selected="{{ $isLogin ? 'false' : 'true' }}"
                    >
                        Register
                    </a>
                </nav>

                <x-auth-session-status class="auth-status" :status="session('status')" />

                <div class="auth-forms" data-auth-forms>
                    <form
                        method="POST"
                        action="{{ route('login') }}"
                        class="auth-form {{ $isLogin ? 'is-active' : '' }}"
                        data-auth-form="login"
                        @if(!$isLogin) hidden @endif
                    >
                        @csrf

                        <label class="auth-field" for="login_email">
                            <span>Email</span>
                            <input
                                id="login_email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                @if($isLogin) autofocus @endif
                                autocomplete="username"
                                placeholder="you@example.com"
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
                                <span>Keep me signed in</span>
                            </label>
                            <a class="auth-link" href="{{ route('register') }}" data-auth-switch="register">Create account</a>
                        </div>

                        <button class="auth-submit" type="submit">
                            Log in
                        </button>
                    </form>

                    <form
                        method="POST"
                        action="{{ route('register') }}"
                        class="auth-form {{ $isLogin ? '' : 'is-active' }}"
                        data-auth-form="register"
                        @if($isLogin) hidden @endif
                    >
                        @csrf

                        <label class="auth-field" for="register_name">
                            <span>Name</span>
                            <input
                                id="register_name"
                                type="text"
                                name="name"
                                value="{{ old('name') }}"
                                required
                                @if(!$isLogin) autofocus @endif
                                autocomplete="name"
                                placeholder="Your full name"
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
                                placeholder="you@example.com"
                            >
                            <x-input-error :messages="$errors->get('email')" class="auth-input-error" />
                        </label>

                        <label class="auth-field" for="register_invite_code">
                            <span>Invite code</span>
                            <input
                                id="register_invite_code"
                                type="text"
                                name="invite_code"
                                value="{{ old('invite_code') }}"
                                required
                                autocomplete="off"
                                placeholder="One-time access code"
                            >
                            <x-input-error :messages="$errors->get('invite_code')" class="auth-input-error" />
                        </label>

                        <label class="auth-field" for="register_password">
                            <span>Password</span>
                            <input
                                id="register_password"
                                type="password"
                                name="password"
                                required
                                autocomplete="new-password"
                                placeholder="Create a password"
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
                                placeholder="Repeat your password"
                            >
                            <x-input-error :messages="$errors->get('password_confirmation')" class="auth-input-error" />
                        </label>

                        <div class="auth-meta auth-meta--single">
                            <p class="auth-note">Registration is available only with an active invite code.</p>
                            <a class="auth-link" href="{{ route('login') }}" data-auth-switch="login">Already have an account?</a>
                        </div>

                        <button class="auth-submit" type="submit">
                            Create account
                        </button>
                    </form>
                </div>
            </main>
        </section>
    </div>
</x-guest-layout>
