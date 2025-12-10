<x-app-layout page-class="page-join">
    <section class="panel join-hero">
        <div class="join-hero__content">
            <div class="eyebrow">Join a room</div>
            <h1 class="join-hero__title">Enter a room code to jump in</h1>
            <p class="panel-subtitle">No account needed — paste the code or link you received from the host.</p>
        </div>
        <div class="join-hero__card">
            @if ($errors->any())
                <div class="form-alert">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('rooms.join.submit') }}" class="join-form">
                @csrf
                <label class="input-field">
                    <span class="input-label">Room code</span>
                    <input
                        type="text"
                        name="code"
                        class="field-control"
                        placeholder="e.g. 4Fh29Kd1 or paste the full link"
                        value="{{ old('code') }}"
                        required
                        autofocus
                    >
                    <span class="input-hint">Tip: you can paste the full room URL — we will extract the code.</span>
                </label>
                <div class="join-actions">
                    <a class="btn btn-ghost" href="{{ route('login') }}">Sign in instead</a>
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="log-in"></i>
                        <span>Enter room</span>
                    </button>
                </div>
            </form>
        </div>
    </section>
    <section
        class="panel fade-up join-visited-panel"
        aria-label="Last visited rooms"
        data-last-visited-panel
        hidden
    >
        <div class="panel-header">
            <div class="panel-title">
                <i data-lucide="clock-3"></i>
                <span>Last visited</span>
            </div>
            <p class="panel-subtitle">Quickly return to rooms you already joined.</p>
        </div>
        <div class="panel-body">
            <div class="visited-rooms-grid" data-last-visited-list></div>
        </div>
    </section>
    @vite('resources/js/join-last-visited.ts')
</x-app-layout>
