@php($isAuthPage = request()->routeIs('login') || request()->routeIs('register'))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Ghost Room') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @if($isAuthPage)
        @vite(['resources/css/login.css', 'resources/js/login.js'])
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
    console.log('%cSTOP! DO NOT TYPE ANYTHING IN THIS CONSOLE. DOING SO CAN EXPOSE YOUR ACCOUNT AND LEAD TO DATA LOSS.', style);
    console.log('%cÄLÄ KOSKAAN KIRJOITA MITÄÄN TÄHÄN KONSOLIIN. SE VOI PALJASTAA TILISI JA JOHTAA TIETOJEN MENETYKSEEN.', style);
})();
</script>
</body>
</html>
