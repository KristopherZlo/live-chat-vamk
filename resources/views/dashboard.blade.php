<x-app-layout>
    @php
        $user = Auth::user();
        $latestRoom = $rooms->first();
        $totalMessages = $rooms->sum('messages_count');
        $totalQuestions = $rooms->sum('questions_count');
    @endphp

    <section class="panel dashboard-hero">
        <div class="dashboard-hero__content">
            <div class="eyebrow">Dashboard</div>
            <h1 class="dashboard-hero__title" id="dashboardGreeting" data-username="{{ $user->name }}">
                Good day, {{ $user->name }}
            </h1>
            <p class="panel-subtitle">Run live rooms in the same sleek style as your chats.</p>
        </div>
    </section>

    @if (session('status'))
        <div class="flash flash-success" data-flash>
            <span>{{ session('status') }}</span>
            <button class="icon-btn flash-close" type="button" data-flash-close aria-label="Close">
                <i data-lucide="x"></i>
            </button>
        </div>
    @endif

    @if ($errors->any())
        <div class="form-alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

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
                            <div class="room-card-meta">
                                <span class="room-code">Code: {{ $room->slug }}</span>
                                <span class="dot-separator">&bull;</span>
                                <span class="message-meta">Updated {{ $room->updated_at->format('d.m H:i') }}</span>
                                <span class="status-pill status-{{ $room->status }}">{{ ucfirst($room->status) }}</span>
                            </div>
                            <div class="room-card-header">
                                <div class="room-card-title">
                                    <div class="inline-editable" data-inline-edit>
                                        <div class="inline-edit-display room-card-title-text">{{ $room->title }}</div>
                                        <button class="icon-btn inline-edit-trigger" type="button" aria-label="Edit title" data-inline-trigger>
                                            <i data-lucide="pencil"></i>
                                        </button>
                                        <form class="inline-edit-form" method="POST" action="{{ route('rooms.update', $room) }}" hidden>
                                            @csrf
                                            @method('PATCH')
                                            <input
                                                type="text"
                                                name="title"
                                                class="field-control inline-edit-input"
                                                value="{{ $room->title }}"
                                                required
                                            >
                                            <div class="inline-edit-actions">
                                                <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                                <button type="button" class="btn btn-sm btn-ghost" data-inline-cancel>Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="inline-editable" data-inline-edit>
                                <p class="inline-edit-display room-card-desc">
                                    {{ $room->description ? \Illuminate\Support\Str::limit($room->description, 140) : 'Add a description' }}
                                </p>
                                <button class="icon-btn inline-edit-trigger" type="button" aria-label="Edit description" data-inline-trigger>
                                    <i data-lucide="pencil"></i>
                                </button>
                                <form class="inline-edit-form" method="POST" action="{{ route('rooms.update', $room) }}" hidden>
                                    @csrf
                                    @method('PATCH')
                                    <textarea
                                        name="description"
                                        rows="2"
                                        class="field-control inline-edit-input"
                                        placeholder="Add a short agenda or note"
                                    >{{ $room->description }}</textarea>
                                    <div class="inline-edit-actions">
                                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                        <button type="button" class="btn btn-sm btn-ghost" data-inline-cancel>Cancel</button>
                                    </div>
                                </form>
                            </div>

                            <div class="room-card-stats">
                                <div class="room-stat">
                                    <div class="room-stat-icon neutral">
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
                            </div>

                            <div class="room-card-actions">
                                <a href="{{ $publicLink }}" class="btn btn-sm btn-primary" target="_blank" rel="noreferrer">
                                    <i data-lucide="messages-square"></i>
                                    <span>Open live room</span>
                                </a>
                                <form method="POST" action="{{ route('rooms.update', $room) }}" class="inline-status-form">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $room->status === 'active' ? 'finished' : 'active' }}">
                                    <button type="submit" class="btn btn-sm {{ $room->status === 'active' ? 'btn-danger' : 'btn-primary' }}">
                                        <i data-lucide="{{ $room->status === 'active' ? 'lock' : 'refresh-cw' }}"></i>
                                        <span>{{ $room->status === 'active' ? 'Close room' : 'Reopen room' }}</span>
                                    </button>
                                </form>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-danger room-delete-btn"
                                    data-room-delete-trigger="{{ $room->id }}"
                                >
                                    <i data-lucide="trash-2"></i>
                                    <span>Delete room</span>
                                </button>
                            </div>

                            <div
                                class="modal-overlay room-delete-modal"
                                data-room-delete-modal="{{ $room->id }}"
                                hidden
                            >
                                <div
                                    class="modal-dialog"
                                    role="dialog"
                                    aria-modal="true"
                                    aria-labelledby="deleteRoomTitle{{ $room->id }}"
                                >
                                    <div class="modal-header">
                                        <div class="modal-title-group">
                                            <div class="modal-eyebrow">Delete room</div>
                                            <h3 class="modal-title" id="deleteRoomTitle{{ $room->id }}">
                                                Type "{{ $room->title }}" to confirm
                                            </h3>
                                        </div>
                                        <button
                                            type="button"
                                            class="icon-btn modal-close"
                                            aria-label="Close"
                                            data-room-delete-close
                                        >
                                            <i data-lucide="x"></i>
                                        </button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="modal-text">
                                            Deleting this room will permanently remove <strong>{{ $room->title }}</strong>,
                                            all questions, and all messages inside it.
                                        </p>
                                        <div class="modal-alert danger">
                                            <i data-lucide="shield-alert"></i>
                                            <span>This action cannot be undone.</span>
                                        </div>
                                        <form
                                            method="POST"
                                            action="{{ route('rooms.destroy', $room) }}"
                                            class="modal-form"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <label class="modal-label" for="confirmTitle{{ $room->id }}">
                                                Enter the room name to delete
                                            </label>
                                            <input
                                                type="text"
                                                id="confirmTitle{{ $room->id }}"
                                                name="confirm_title"
                                                class="field-control modal-input"
                                                placeholder="{{ $room->title }}"
                                                autocomplete="off"
                                                data-room-delete-input
                                                data-room-title="{{ $room->title }}"
                                                required
                                            >
                                            <div class="modal-actions">
                                                <button type="button" class="btn btn-ghost" data-room-delete-close>
                                                    Cancel
                                                </button>
                                                <button
                                                    type="submit"
                                                    class="btn btn-danger"
                                                    data-room-delete-submit
                                                    disabled
                                                >
                                                    <i data-lucide="trash-2"></i>
                                                    <span>Delete room</span>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-app-layout>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const greetingEl = document.getElementById('dashboardGreeting');
            if (!greetingEl) return;
            const name = greetingEl.dataset.username || '';
            const hour = new Date().getHours();
            let greeting = 'Good day';
            if (hour >= 5 && hour < 12) greeting = 'Good morning';
            else if (hour >= 12 && hour < 17) greeting = 'Good afternoon';
            else if (hour >= 17 && hour < 22) greeting = 'Good evening';
            else greeting = 'Good night';
            greetingEl.textContent = name ? `${greeting}, ${name}` : greeting;
        });
    </script>
@endpush
