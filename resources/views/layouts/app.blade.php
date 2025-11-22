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

    <footer class="app-footer">
        <div class="app-footer-left">
            <a href="#">GDPR</a>
            <a href="#">Contact</a>
        </div>
        <div class="app-footer-right">
            <span class="footer-label">Language:</span>
            <button class="footer-lang active" type="button">FI</button>
            <button class="footer-lang" type="button">RU</button>
            <button class="footer-lang" type="button">EN</button>
        </div>
    </footer>
</div>
@stack('scripts')
</body>
</html>
