@php
    $email = (string) optional(auth()->user())->email;
    $resendSeconds = max(0, (int) ($resendCooldownSeconds ?? 0));
    $resendToken = (string) ($resendToken ?? '');
    $atPos = strpos($email, '@');
    if ($atPos === false) {
        $maskedEmail = $email;
    } else {
        $local = substr($email, 0, $atPos);
        $domain = substr($email, $atPos);
        $maskedEmail = (strlen($local) > 0 ? substr($local, 0, 1) : '*')
            . str_repeat('*', max(3, strlen($local) - 1))
            . $domain;
    }
@endphp

<x-guest-layout>
    <div class="auth-page auth-page--verify">
        <section class="auth-layout" aria-label="Verify email">
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

                    <h1 class="auth-title auth-title--verify">Enter the 6-digit code</h1>
                    <p class="auth-subtitle auth-subtitle--verify">We sent a code to {{ $maskedEmail }}.</p>

                    @if (session('status') == 'verification-code-sent')
                        <p class="auth-status">New verification code sent.</p>
                    @endif

                    <form method="POST" action="{{ route('verification.code.verify') }}" class="auth-form auth-form--verify" data-verification-code-form>
                        @csrf

                        <input type="hidden" name="code" value="{{ preg_replace('/\D/', '', old('code', '')) }}" data-verification-code>

                        <div class="auth-code-grid" data-verification-code-grid>
                            @for ($i = 0; $i < 6; $i++)
                                <input
                                    type="text"
                                    class="auth-code-digit"
                                    inputmode="numeric"
                                    pattern="[0-9]*"
                                    maxlength="1"
                                    autocomplete="one-time-code"
                                    aria-label="Code digit {{ $i + 1 }}"
                                    data-code-digit
                                >
                            @endfor
                        </div>

                        <x-input-error :messages="$errors->get('code')" class="auth-input-error auth-input-error--code" />

                        <button class="auth-submit" type="submit">Verify code</button>
                    </form>

                    <p class="auth-note auth-note--verify">
                        If you don't see the email in your inbox, check your spam folder.
                    </p>

                    <div class="auth-actions auth-actions--verify">
                        <form
                            method="POST"
                            action="{{ route('verification.send') }}"
                            class="auth-action-form auth-action-form--compact auth-action-form--resend"
                        >
                            @csrf
                            <input type="hidden" name="resend_token" value="{{ $resendToken }}">
                            <button
                                type="submit"
                                class="auth-secondary"
                                data-resend-button
                                data-resend-seconds="{{ $resendSeconds }}"
                                @disabled($resendSeconds > 0)
                            >
                                Resend code
                            </button>
                            <span
                                class="auth-resend-timer"
                                data-resend-timer
                                @if ($resendSeconds <= 0) hidden @endif
                            >
                                available in {{ $resendSeconds }}s
                            </span>
                        </form>
                        <form method="POST" action="{{ route('logout') }}" class="auth-action-form auth-action-form--compact">
                            @csrf
                            <button type="submit" class="auth-secondary">Cancel</button>
                        </form>
                    </div>
                </div>
            </main>
        </section>
    </div>
</x-guest-layout>
