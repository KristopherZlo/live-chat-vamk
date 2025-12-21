<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Access denied - {{ config('app.name', 'Ghost Room') }}</title>

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
    @vite(['resources/css/app.css', 'resources/css/design.css', 'resources/js/lucide.ts', 'resources/js/app.ts', 'resources/js/design.ts'])
</head>
<body class="app error-page-shell">
<div class="app-shell">
    @include('layouts.navigation')

    <main class="error-page">
        <section class="panel error-panel">
            <div class="error-sheen"></div>
            <div class="error-glow"></div>
            <div class="eyebrow error-eyebrow">403 / Forbidden</div>
            <div class="error-code">403</div>
            <h1 class="error-title">Access denied</h1>
            <p class="panel-subtitle error-lead">
                You do not have permission to view this page. If you think this is a mistake, try signing in
                with a different account or ask the room owner for access.
            </p>
            <div class="error-actions">
                <button class="btn btn-primary" type="button" onclick="history.back()">
                    <i data-lucide="arrow-left"></i>
                    <span>Go back</span>
                </button>
                <a class="btn btn-ghost" href="{{ route('home') }}">
                    <i data-lucide="home"></i>
                    <span>Go to home</span>
                </a>
            </div>
        </section>
    </main>

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
                    <span class="footer-muted">Made with love by Zloy</span>
                </div>
            </div>
        </div>
    </footer>
</div>
@stack('scripts')
</body>
</html>
