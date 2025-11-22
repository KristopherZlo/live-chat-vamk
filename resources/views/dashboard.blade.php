<x-app-layout>
    @php
        $user = Auth::user();
        $activeCount = $rooms->where('status', 'active')->count();
        $finishedCount = $rooms->where('status', 'finished')->count();
        $archivedCount = $rooms->where('status', 'archived')->count();
        $latestRoom = $rooms->first();
        $totalMessages = $rooms->sum('messages_count');
        $totalQuestions = $rooms->sum('questions_count');
    @endphp

    <section class="panel dashboard-hero">
        <div class="dashboard-hero__content">
            <div class="eyebrow">Dashboard</div>
            <h1 class="dashboard-hero__title">Welcome back, {{ $user->name }}</h1>
            <p class="panel-subtitle">Run live rooms in the same sleek style as your chats.</p>
            <div class="dashboard-hero__actions">
                <a href="{{ route('rooms.create') }}" class="btn btn-primary">Create room</a>
                @if($latestRoom)
                    <a href="{{ route('rooms.public', $latestRoom->slug) }}" class="btn btn-ghost">
                        <i data-lucide="play-circle"></i>
                        <span>Open latest room</span>
                    </a>
                @endif
            </div>
        </div>
        <div class="dashboard-hero__stats">
            <div class="hero-stat">
                <div class="hero-icon">
                    <i data-lucide="radio"></i>
                </div>
                <div>
                    <div class="hero-stat-label">Active</div>
                    <div class="hero-stat-value">{{ $activeCount }}</div>
                </div>
            </div>
            <div class="hero-stat">
                <div class="hero-icon success">
                    <i data-lucide="check-circle-2"></i>
                </div>
                <div>
                    <div class="hero-stat-label">Finished</div>
                    <div class="hero-stat-value">{{ $finishedCount }}</div>
                </div>
            </div>
            <div class="hero-stat">
                <div class="hero-icon muted">
                    <i data-lucide="archive"></i>
                </div>
                <div>
                    <div class="hero-stat-label">Archived</div>
                    <div class="hero-stat-value">{{ $archivedCount }}</div>
                </div>
            </div>
        </div>
    </section>

    <section class="panel rooms-panel">
        <div class="panel-header">
            <div class="panel-title">
                <i data-lucide="layout-dashboard"></i>
                <span>Your rooms</span>
            </div>
            <div class="panel-actions">
                <span class="panel-subtitle">{{ $rooms->count() }} total | {{ $totalMessages }} messages | {{ $totalQuestions }} questions</span>
                <a href="{{ route('rooms.create') }}" class="btn btn-sm btn-primary">Create room</a>
            </div>
        </div>

        <div class="panel-body">
            @if($rooms->isEmpty())
                <div class="empty-state">
                    <div>No rooms yet.</div>
                    <div class="empty-actions">
                        <a class="btn btn-primary" href="{{ route('rooms.create') }}">Create the first room</a>
                    </div>
                </div>
            @else
                <div class="rooms-grid">
                    @foreach($rooms as $room)
                        @php
                            $publicLink = route('rooms.public', $room->slug);
                        @endphp
                        <article class="room-card panel">
                            <div class="room-card-header">
                                <div class="room-card-title">
                                    <span>{{ $room->title }}</span>
                                    <span class="status-pill status-{{ $room->status }}">{{ ucfirst($room->status) }}</span>
                                </div>
                                <button class="icon-btn room-copy" type="button" data-copy="{{ $publicLink }}" title="Copy public link">
                                    <i data-lucide="copy"></i>
                                </button>
                            </div>
                            <div class="room-card-meta">
                                <span class="room-code">Code: {{ $room->slug }}</span>
                                <span class="dot-separator">&bull;</span>
                                <span class="message-meta">Updated {{ $room->updated_at->format('d.m H:i') }}</span>
                            </div>
                            @if($room->description)
                                <p class="room-card-desc">{{ \Illuminate\Support\Str::limit($room->description, 140) }}</p>
                            @else
                                <p class="room-card-desc text-muted">No description yet.</p>
                            @endif

                            <div class="room-card-stats">
                                <div class="room-stat">
                                    <div class="room-stat-icon accent">
                                        <i data-lucide="message-square"></i>
                                    </div>
                                    <div>
                                        <div class="room-stat-label">Messages</div>
                                        <div class="room-stat-value">{{ $room->messages_count }}</div>
                                    </div>
                                </div>
                                <div class="room-stat">
                                    <div class="room-stat-icon">
                                        <i data-lucide="help-circle"></i>
                                    </div>
                                    <div>
                                        <div class="room-stat-label">Questions</div>
                                        <div class="room-stat-value">{{ $room->questions_count }}</div>
                                    </div>
                                </div>
                                <div class="room-stat">
                                    <div class="room-stat-icon neutral">
                                        <i data-lucide="shield-check"></i>
                                    </div>
                                    <div>
                                        <div class="room-stat-label">Public read</div>
                                        <div class="room-stat-value">{{ $room->is_public_read ? 'Enabled' : 'Owner only' }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="room-card-actions">
                                <a href="{{ $publicLink }}" class="btn btn-sm btn-ghost" target="_blank" rel="noreferrer">
                                    <i data-lucide="external-link"></i>
                                    <span>Public view</span>
                                </a>
                                <a href="{{ $publicLink }}" class="btn btn-sm btn-primary" target="_blank" rel="noreferrer">
                                    <i data-lucide="messages-square"></i>
                                    <span>Open live room</span>
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-app-layout>
