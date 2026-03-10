<x-app-layout>
    @php
        $user = Auth::user();
        $latestRoom = $rooms->first();
        $totalMessages = $rooms->sum('messages_count');
        $totalQuestions = $rooms->sum('questions_count');
        $roomCardPalette = [
            'default' => 'Default',
            'ocean' => 'Ocean',
            'mint' => 'Mint',
            'amber' => 'Amber',
            'rose' => 'Rose',
            'violet' => 'Violet',
            'teal' => 'Teal',
            'slate' => 'Slate',
            'coral' => 'Coral',
        ];
        $roomCardColorKeys = \App\Models\Room::CARD_COLORS;
    @endphp

    <section class="dashboard-title">
        <h1 class="dashboard-title__text" id="dashboardGreeting" data-username="{{ $user->name }}">
            Hello, {{ $user->name }}
        </h1>
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
                <a href="{{ route('rooms.create') }}" class="btn btn-sm btn-primary" data-onboarding-target="dashboard-create-room">Create room</a>
            </div>
        </div>

        <div class="panel-body">
            @if($rooms->isEmpty())
                <div class="empty-state">
                    <div>No rooms yet.</div>
                    <div class="empty-actions">
                        <a class="btn btn-primary" href="{{ route('rooms.create') }}" data-onboarding-target="dashboard-create-room">Create the first room</a>
                    </div>
                </div>
            @else
                <div class="rooms-grid" data-rooms-grid data-rooms-reorder-url="{{ route('rooms.reorder') }}">
                    @foreach($rooms as $room)
                        @php
                            $publicLink = route('rooms.public', $room->slug);
                            $cardColor = in_array($room->card_color, $roomCardColorKeys, true) ? $room->card_color : null;
                            $cardColorKey = $cardColor ?? 'default';
                            $cardColorMenuId = 'roomColorMenu' . $room->id;
                        @endphp
                        <article
                            class="room-card panel{{ $cardColor ? ' room-card--color-' . $cardColor : '' }}"
                            data-room-card
                            data-room-id="{{ $room->id }}"
                            draggable="false"
                        >
                            <div class="room-card-meta">
                                <div class="room-card-meta-info">
                                    <span class="room-code">Code: <span class="room-code-value">{{ $room->slug }}</span></span>
                                    <span class="dot-separator">&bull;</span>
                                    <span class="message-meta">Updated {{ $room->updated_at->format('d.m H:i') }}</span>
                                    <span class="status-pill status-{{ $room->status }}">{{ ucfirst($room->status) }}</span>
                                </div>
                                <div class="room-card-meta-controls">
                                    <button
                                        type="button"
                                        class="icon-btn room-card-sort-handle"
                                        data-room-sort-handle
                                        aria-label="Drag to reorder room"
                                        title="Drag to reorder room"
                                    >
                                        <i data-lucide="grip-vertical"></i>
                                    </button>
                                    <div class="room-card-color-picker" data-room-color-picker>
                                        <button
                                            type="button"
                                            class="icon-btn room-card-color-trigger"
                                            data-room-color-trigger
                                            aria-label="Change room card color"
                                            aria-haspopup="true"
                                            aria-expanded="false"
                                            aria-controls="{{ $cardColorMenuId }}"
                                        >
                                            <span class="room-card-color-dot room-card-color-dot--{{ $cardColorKey }}"></span>
                                            <span class="visually-hidden">Change room card color</span>
                                        </button>
                                        <form
                                            method="POST"
                                            action="{{ route('rooms.update', $room) }}"
                                            class="room-card-color-menu"
                                            id="{{ $cardColorMenuId }}"
                                            data-room-color-menu
                                            hidden
                                        >
                                            @csrf
                                            @method('PATCH')
                                            @foreach($roomCardPalette as $colorKey => $colorLabel)
                                                <button
                                                    type="submit"
                                                    class="room-card-color-option{{ $cardColorKey === $colorKey ? ' is-active' : '' }}"
                                                    name="card_color"
                                                    value="{{ $colorKey }}"
                                                    aria-label="Use {{ $colorLabel }} color"
                                                >
                                                    <span class="room-card-color-dot room-card-color-dot--{{ $colorKey }}"></span>
                                                </button>
                                            @endforeach
                                        </form>
                                    </div>
                                </div>
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
