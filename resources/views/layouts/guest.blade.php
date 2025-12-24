<?php $isAuthPage = request()->routeIs('login') || request()->routeIs('register'); ?>
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <?php
        $appName = config('app.name', 'Ghost Room');
        $defaultDescription = 'Ghost Room is an anonymous live Q&A chat for lectures so attendees can send questions without interrupting the class.';
        $metaTitle = $attributes->get('meta-title') ?? null;
        $metaDescription = $attributes->get('meta-description') ?? $defaultDescription;
        $metaImage = $attributes->get('meta-image') ?? asset('icons/logo_black.svg');
        $fullTitle = $metaTitle ? $metaTitle.' | '.$appName : $appName;
        $currentUrl = url()->current();
    ?>

    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ $currentUrl }}">
    <meta name="application-name" content="{{ $appName }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $currentUrl }}">
    <meta property="og:site_name" content="{{ $appName }}">
    <meta property="og:image" content="{{ $metaImage }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $fullTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <meta name="twitter:image" content="{{ $metaImage }}">
    @stack('meta')

    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/logo_white.svg') }}">
    <link rel="shortcut icon" href="{{ asset('icons/logo_white.svg') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @if($isAuthPage)
        @vite(['resources/css/login.css', 'resources/js/login.ts'])
    @else
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @endif
</head>
<body class="{{ $isAuthPage ? 'login-page' : 'font-sans text-gray-900 antialiased' }}">
@if($isAuthPage)
    {{ $slot }}
@else
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
        <div>
            <a href="/">
                <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
            </a>
        </div>

        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
            {{ $slot }}
        </div>
    </div>
@endif
<script>
(() => {
    const style = 'color: red; font-size: 24px; font-weight: 900;';
    const warn = () => {
        console.log('%cSTOP! DO NOT TYPE ANYTHING IN THIS CONSOLE. DOING SO CAN EXPOSE YOUR ACCOUNT AND LEAD TO DATA LOSS.', style);
        console.log('%cÄLÄ KOSKAAN KIRJOITA MITÄÄN TÄHÄN KONSOLIIN. SE VOI PALJASTAA TILISI JA JOHTAA TIETOJEN MENETYKSEEN.', style);
    };
    const threshold = 160;
    let devtoolsOpen = false;
    const detectDevtools = () => {
        const widthGap = Math.abs(window.outerWidth - window.innerWidth);
        const heightGap = Math.abs(window.outerHeight - window.innerHeight);
        const opened = widthGap > threshold || heightGap > threshold;
        if (opened !== devtoolsOpen) {
            devtoolsOpen = opened;
            window.dispatchEvent(new CustomEvent('devtoolschange', { detail: { open: devtoolsOpen } }));
        }
    };
    window.addEventListener('resize', detectDevtools);
    window.addEventListener('keydown', (event) => {
        if (event.key === 'F12' || (event.ctrlKey && event.shiftKey && event.key?.toLowerCase?.() === 'i')) {
            setTimeout(detectDevtools, 50);
        }
    });
    window.addEventListener('devtoolschange', (event) => {
        if (event.detail?.open) {
            warn();
        }
    });
    warn();
    detectDevtools();
})();
</script>
</body>
</html>
