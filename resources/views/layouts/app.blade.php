<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-base-url" content="{{ url('/') }}">

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
    @php
        use App\Models\Setting;
        use App\Models\UpdatePost;
        use Illuminate\Support\Str;

        $appVersion = Setting::getValue('app_version', config('app.version'));
        $whatsNewEntry = null;
        $whatsNewContentHtml = null;
        $whatsNewSections = [];
        $whatsNewImageUrl = null;
        $whatsNewDate = null;
        $whatsNewTitle = null;
        $configRelease = null;

        try {
            $whatsNewEntry = UpdatePost::query()
                ->type(UpdatePost::TYPE_WHATS_NEW)
                ->published()
                ->when($appVersion, fn ($query) => $query->where('version', $appVersion))
                ->latestPublished()
                ->first();

            if (! $whatsNewEntry) {
                $whatsNewEntry = UpdatePost::query()
                    ->type(UpdatePost::TYPE_WHATS_NEW)
                    ->published()
                    ->latestPublished()
                    ->first();
            }
        } catch (\Throwable $e) {
            $whatsNewEntry = null;
        }

        $whatsNewVersion = $whatsNewEntry->version ?? $appVersion ?? config('app.version');

        if (! $whatsNewEntry && $whatsNewVersion) {
            $configRelease = config('whatsnew.releases')[$whatsNewVersion] ?? null;
        }

        $whatsNewRelease = $whatsNewEntry ?: $configRelease;
        $whatsNewImageUrl = $whatsNewEntry?->cover_url
            ?? ($configRelease['image'] ?? false ? asset($configRelease['image']) : null);
        $whatsNewContentHtml = $whatsNewEntry?->body
            ? Str::markdown($whatsNewEntry->body, ['html_input' => 'strip'])
            : null;
        $whatsNewSections = is_array($configRelease['sections'] ?? null) ? $configRelease['sections'] : [];
        $whatsNewDate = $whatsNewEntry?->published_at?->format('Y-m-d') ?? ($configRelease['date'] ?? null);
        $whatsNewTitle = $whatsNewEntry?->title ?? ($configRelease['title'] ?? null);
        if (! $whatsNewVersion) {
            $whatsNewVersion = config('app.version', '1.0.0');
        }
    @endphp
    @vite([
        'resources/js/lucide.js',
        'resources/css/app.css',
        'resources/css/design.css',
        'resources/js/app.js',
        'resources/js/design.js'
    ])
    @stack('styles')
</head>
@php
    $authUser = Auth::user();
    $routeName = \Illuminate\Support\Facades\Route::currentRouteName();
@endphp
@php($pageClass = $pageClass ?? $attributes->get('page-class'))
<body
    class="app{{ $pageClass ? ' ' . $pageClass : '' }}"
    data-route-name="{{ $routeName }}"
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
                    <a href="{{ route('updates.index') }}">Updates</a>
                    <a href="{{ route('rooms.create') }}">New room</a>
                    <a href="{{ route('profile.edit') }}">Profile</a>
                </div>
            </div>
            <div class="footer-column">
                <div class="footer-heading">About</div>
                <div class="footer-links-list">
                    <span class="footer-muted">Ghost Room - Anonymous live chat for lectures. Send questions without interrupting the class.</span>
                    <span class="footer-muted">Made with 💜 by Zloy</span>
                    <a class="footer-muted" href="https://github.com/KristopherZlo/live-chat-vamk" target="_blank" rel="noreferrer">
                        GitHub repository
                    </a>
                    <span class="footer-muted footer-version">
                        v{{ $appVersion ?? config('app.version', '1.0.0') }}
                    </span>
                </div>
            </div>
        </div>
    </footer>
</div>

<div
    x-data="{
        open: false,
        init() {
            const KEY = 'gr_welcome_seen';
            try {
                const seen = localStorage.getItem(KEY);
                this.open = !seen;
            } catch (e) {
                this.open = true;
            }
        },
        close() {
            this.open = false;
            try {
                localStorage.setItem('gr_welcome_seen', '1');
            } catch (e) {}
        }
    }"
    x-init="init()"
    x-show="open"
    x-cloak
    class="modal-overlay"
    x-bind:class="{ 'show': open }"
    style="display: none; z-index: 130;"
    x-transition.opacity
    x-on:keydown.escape.window="close()"
    data-welcome-modal
>
    <div class="modal-dialog" style="z-index: 140; max-width: 520px;" x-on:click.stop>
        <div class="modal-header">
            <div class="modal-title-group">
                <div class="modal-eyebrow">Welcome</div>
                <div class="modal-title">Hi, welcome to Ghost Room 👋</div>
            </div>
            <button class="modal-close" type="button" x-on:click="close()" data-welcome-close>
                <i data-lucide="x" aria-hidden="true"></i>
            </button>
        </div>
        <div class="modal-body modal-text">
            <p>This service was created by a TT2025 student from VAMK and launched in test mode. This is a <span class="text-beta">beta</span> version, and it may have bugs.</p>
            <p>You can send bug reports, feedback, and other things to my email: <a href="mailto:zloydeveloper.info@gmail.com">zloydeveloper.info@gmail.com</a></p>
            <p>GitHub repository of the project: <a href="https://github.com/KristopherZlo/live-chat-vamk" target="_blank" rel="noreferrer">https://github.com/KristopherZlo/live-chat-vamk</a></p>
        </div>
        <div class="modal-actions" style="justify-content: flex-end; gap: 0.5rem;">
            <button class="btn btn-ghost" type="button" x-on:click="close()">Got it</button>
        </div>
    </div>
</div>
<script>
(() => {
    const modal = document.querySelector('[data-welcome-modal]');
    if (!modal) return;
    // Fallback if Alpine is not available or JS bundle fails.
    if (window.Alpine) return;
    const KEY = 'gr_welcome_seen';
    let seen = false;
    try {
        seen = localStorage.getItem(KEY);
    } catch (e) {}
    if (seen) return;
    modal.style.display = 'flex';
    modal.classList.add('show');
    const closeBtn = modal.querySelector('[data-welcome-close]');
    const close = () => {
        modal.style.display = 'none';
        modal.classList.remove('show');
        try { localStorage.setItem(KEY, '1'); } catch (e) {}
    };
    closeBtn?.addEventListener('click', close);
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') close();
    });
})();
</script>
@if ($whatsNewRelease)
    <div
        class="modal-overlay"
        data-whats-new-modal
        data-whats-new-version="{{ $whatsNewVersion }}"
        hidden
        tabindex="-1"
    >
        <div
            class="modal-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="whatsNewTitle"
        >
            <div class="modal-header">
                <div class="modal-title-group">
                    <span class="modal-eyebrow">what's new?</span>
                    <h2 id="whatsNewTitle" class="modal-title">
                        {{ $whatsNewTitle ?? 'Version '.$whatsNewVersion }}
                    </h2>
                    @if ($whatsNewDate)
                        <p class="modal-text">Released {{ $whatsNewDate }}</p>
                    @endif
                </div>
            </div>
            <div class="modal-body whats-new-body">
                @if ($whatsNewImageUrl)
                    <div class="whats-new-media">
                        <img
                            src="{{ $whatsNewImageUrl }}"
                            alt="{{ is_array($whatsNewRelease) ? ($whatsNewRelease['image_alt'] ?? ($whatsNewTitle ?? 'Update preview')) : ($whatsNewTitle ?? 'Update preview') }}"
                            loading="lazy"
                        >
                    </div>
                @endif
                @if (!empty($whatsNewContentHtml))
                    <div class="whats-new-content markdown-body">
                        {!! $whatsNewContentHtml !!}
                    </div>
                @elseif (!empty($whatsNewSections))
                    <div class="whats-new-sections">
                        @foreach ($whatsNewSections as $section)
                            <div class="whats-new-section">
                                <h3 class="whats-new-section-title">{{ $section['title'] }}</h3>
                                @if (!empty($section['items']))
                                    <ul class="whats-new-items">
                                        @foreach ($section['items'] as $item)
                                            <li>{{ $item }}</li>
                                        @endforeach
                                    </ul>
                                @elseif (!empty($section['text']))
                                    <p class="whats-new-section-text">{{ $section['text'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="modal-text">No details yet.</p>
                @endif
            </div>
            <div class="modal-actions">
                <button class="btn btn-primary" type="button" data-whats-new-close>Got it!</button>
            </div>
        </div>
    </div>
@endif
@stack('scripts')
</body>
</html>
