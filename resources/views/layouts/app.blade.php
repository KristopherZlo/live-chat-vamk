<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Ghost Room') }}</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/logo_white.svg') }}">
    <link rel="shortcut icon" href="{{ asset('icons/logo_white.svg') }}">

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap">
    <script>
        (() => {
            const KEY = 'lc-theme';
            let theme = 'light';
            try {
                const stored = localStorage.getItem(KEY);
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                theme = stored === 'dark' || (!stored && prefersDark) ? 'dark' : 'light';
            } catch (e) {
                theme = 'light';
            }
            const apply = () => {
                document.documentElement.dataset.theme = theme;
                document.documentElement.style.backgroundColor = theme === 'dark' ? '#000000' : '#ffffff';
                if (document.body) {
                    document.body.dataset.theme = theme;
                }
            };
            apply();
            document.addEventListener('DOMContentLoaded', apply);
            document.documentElement.style.backgroundColor = theme === 'dark' ? '#000000' : '#ffffff';
        })();
    </script>
    @php
        $reverbConnection = config('broadcasting.connections.reverb', []);
        $reverbOptions = $reverbConnection['options'] ?? [];
        $reverbKey = $reverbConnection['key'] ?? '';
        $reverbHost = $reverbOptions['host'] ?? request()->getHost();
        $reverbPort = $reverbOptions['port'] ?? 443;
        $reverbScheme = $reverbOptions['scheme'] ?? 'https';
    @endphp
    <script>
        window.__reverbConfig = {
            key: @json($reverbKey),
            host: @json($reverbHost),
            port: @json($reverbPort),
            scheme: @json($reverbScheme),
        };
    </script>
    @vite([
        'resources/js/lucide.js',
        'resources/css/app.css',
        'resources/css/design.css',
        'resources/css/onboarding.css',
        'resources/js/app.js',
        'resources/js/design.js',
        'resources/js/onboarding.js'
    ])
</head>
@php
    $authUser = Auth::user();
    $hasRooms = $authUser ? $authUser->rooms()->exists() : false;
    $onboardingNewUser = $authUser
        ? (session('onboarding_new_user') || !$hasRooms)
        : false;
    $routeName = \Illuminate\Support\Facades\Route::currentRouteName();
@endphp
@php($pageClass = $pageClass ?? $attributes->get('page-class'))
<body
    class="app{{ $pageClass ? ' ' . $pageClass : '' }}"
    data-route-name="{{ $routeName }}"
    data-onboarding-new-user="{{ $onboardingNewUser ? '1' : '0' }}"
    data-onboarding-has-rooms="{{ $hasRooms ? '1' : '0' }}"
    @if($authUser)
        data-user-name="{{ $authUser->name }}"
    @endif
>
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
            <div class="footer-column">
                <div class="footer-heading">Support</div>
                <div class="footer-links-list">
                    <a href="{{ route('privacy') }}">Privacy & terms</a>
                    <a href="mailto:zloydeveloper.info@gmail.com">Contact</a>
                </div>
            </div>
            <div class="footer-column">
                <div class="footer-heading">Product</div>
                <div class="footer-links-list">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <a href="{{ route('rooms.create') }}">New room</a>
                    <a href="{{ route('profile.edit') }}">Profile</a>
                </div>
            </div>
            <div class="footer-column">
                <div class="footer-heading">About</div>
                <div class="footer-links-list">
                    <span class="footer-muted">Ghost Room - Anonymous live chat for lectures. Send questions without interrupting the class.</span>
                    <span class="footer-muted">Made with 💜 by Zloy</span>
                </div>
            </div>
        </div>
    </footer>
</div>
@stack('scripts')
</body>
</html>
