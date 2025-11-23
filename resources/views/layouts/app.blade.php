<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Live Chat') }}</title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap">
    <script src="https://unpkg.com/lucide@latest" defer></script>

    @vite(['resources/css/app.css', 'resources/css/design.css', 'resources/js/app.js', 'resources/js/design.js'])
</head>
<body class="app" data-theme="light">
<div class="app-shell">
    @include('layouts.navigation')

    <main>
        @isset($header)
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">
                        {{ $header }}
                    </div>
                </div>
            </div>
        @endisset

        {{ $slot }}
    </main>

    @unless(request()->routeIs('rooms.*'))
        <nav class="mobile-tabs app-mobile-tabs" aria-label="Quick navigation">
            <a class="mobile-tab-btn {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                <i data-lucide="home"></i>
                <span>Dashboard</span>
            </a>
            <a class="mobile-tab-btn {{ request()->routeIs('rooms.create') ? 'active' : '' }}" href="{{ route('rooms.create') }}">
                <i data-lucide="plus"></i>
                <span>New room</span>
            </a>
            <a class="mobile-tab-btn {{ request()->routeIs('profile.edit') ? 'active' : '' }}" href="{{ route('profile.edit') }}">
                <i data-lucide="user"></i>
                <span>Profile</span>
            </a>
        </nav>
    @endunless

    <footer class="app-footer">
            <div class="footer-grid">
                <div class="footer-brand">
                    <div class="footer-logo">{{ config('app.name', 'Live Chat') }}</div>
                    <div class="footer-note">VAMK Instant feedback</div>
                    <a class="footer-contact" href="mailto:zloydeveloper.info@gmail.com">Contact: zloydeveloper.info@gmail.com</a>
                </div>
            <div class="footer-links">
                <a href="#">GDPR</a>
                <a href="#">Contact</a>
                <a href="#">Help</a>
            </div>
            <div class="footer-meta">
                <div class="footer-lang-group">
                    <button class="footer-lang active" type="button">FI</button>
                    <button class="footer-lang" type="button">RU</button>
                    <button class="footer-lang" type="button">EN</button>
                </div>
                <div class="footer-copy">made with 💗 by Zlo</div>
            </div>
        </div>
    </footer>
</div>
@stack('scripts')
</body>
</html>
