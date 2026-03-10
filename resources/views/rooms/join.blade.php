<x-app-layout
    page-class="page-join"
    meta-title="Join a Ghost Room"
    meta-description="Enter a room code to join anonymous live Q&A rooms in Ghost Room."
>
    <div class="join-shell">
        <div class="join-hero">
            <h1 class="join-hero__title">Join a room</h1>
            <p class="join-hero__subtitle">Enter a room code to get started.</p>

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
                <input
                    type="text"
                    name="code"
                    class="field-control join-input"
                    placeholder="e.g. 4Fh29Kd1 or paste the full link"
                    value="{{ old('code') }}"
                    required
                    autofocus
                    aria-label="Room code"
                >
                <button type="submit" class="btn btn-primary join-submit">
                    <i data-lucide="log-in"></i>
                    <span>Join</span>
                </button>
            </form>
        </div>

        <section
            class="join-recent"
            aria-label="Recent meetings"
            data-last-visited-panel
            hidden
        >
            <div class="join-recent-title">
                <i data-lucide="clock-3"></i>
                <span>Last visited</span>
            </div>
            <div class="visited-rooms-grid" data-last-visited-list></div>
        </section>
    </div>
    @vite('resources/js/join-last-visited.ts')
</x-app-layout>
