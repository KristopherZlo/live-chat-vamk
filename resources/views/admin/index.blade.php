@push('styles')
    @vite('resources/css/admin.css')
@endpush

<x-app-layout page-class="page-admin admin-shell">
    @php
        $status = session('status');
        $latestInviteUsage = $recentUsedInvites->first();
        $avgMessagesPerRoom = $stats['rooms'] ? round($stats['messages'] / max($stats['rooms'], 1), 1) : 0;
        $avgQuestionsPerRoom = $stats['rooms'] ? round($stats['questions'] / max($stats['rooms'], 1), 1) : 0;
    @endphp

    <div class="admin-layout">
        <div class="admin-sidebar-backdrop" data-sidebar-backdrop></div>
        <aside class="admin-sidebar" data-admin-sidebar>
            <div class="admin-sidebar__brand">
                <div class="admin-avatar">ADM</div>
                <div class="admin-brand-text">
                    <div class="admin-brand-title">Admin panel</div>
                    <div class="admin-brand-subtitle">Visible only to developers</div>
                </div>
                <button class="admin-icon-btn admin-sidebar__close" type="button" aria-label="Close navigation" data-sidebar-close>
                    <i data-lucide="x"></i>
                </button>
            </div>

            <nav class="admin-nav">
                <div class="admin-nav-group">
                    <div class="admin-nav-label">Overview</div>
                    <button class="admin-nav-btn is-active" type="button" data-section-target="overview">
                        <span class="admin-dot admin-dot--success"></span>
                        Overview
                    </button>
                </div>

                <div class="admin-nav-group">
                    <div class="admin-nav-label">Users & access</div>
                    <button class="admin-nav-btn" type="button" data-section-target="sessions">
                        <span class="admin-dot admin-dot--warn"></span>
                        Sessions & security
                    </button>
                    <button class="admin-nav-btn" type="button" data-section-target="invites">
                        <span class="admin-dot admin-dot--accent"></span>
                        Invite codes
                    </button>
                    <button class="admin-nav-btn" type="button" data-section-target="users">
                        <span class="admin-dot"></span>
                        Users & participants
                    </button>
                </div>

                <div class="admin-nav-group">
                    <div class="admin-nav-label">Content</div>
                    <button class="admin-nav-btn" type="button" data-section-target="rooms">
                        <span class="admin-dot admin-dot--muted"></span>
                        Rooms
                    </button>
                    <button class="admin-nav-btn" type="button" data-section-target="content">
                        <span class="admin-dot admin-dot--accent"></span>
                        Content & moderation
                    </button>
                    <button class="admin-nav-btn" type="button" data-section-target="bans">
                        <span class="admin-dot admin-dot--danger"></span>
                        Ban controls
                    </button>
                </div>

                <div class="admin-nav-group">
                    <div class="admin-nav-label">Support</div>
                    <button class="admin-nav-btn" type="button" data-section-target="support">
                        <span class="admin-dot admin-dot--info"></span>
                        Support & communication
                    </button>
                </div>

                <div class="admin-nav-group">
                    <div class="admin-nav-label">Monitoring</div>
                    <button class="admin-nav-btn" type="button" data-section-target="logs">
                        <span class="admin-dot admin-dot--warn"></span>
                        Logs & audit
                    </button>
                    <button class="admin-nav-btn" type="button" data-section-target="metrics">
                        <span class="admin-dot admin-dot--success"></span>
                        Metrics & health
                    </button>
                </div>
            </nav>

            <div class="admin-sidebar__foot">
                <span class="admin-chip admin-chip--success">is_dev: true</span>
                <span class="admin-muted">v{{ config('app.version', '1.0.0') }}</span>
            </div>
        </aside>

        <div class="admin-main">
            <header class="admin-topbar">
                <div class="admin-topbar__left">
                    <button class="admin-icon-btn admin-nav-toggle" type="button" aria-label="Open navigation" data-mobile-nav-toggle>
                        <i data-lucide="menu"></i>
                    </button>
                    <div>
                        <div class="admin-eyebrow">Ghost Room admin</div>
                        <div class="admin-topbar__title">Control panel</div>
                    </div>
                </div>
                <div class="admin-topbar__actions">
                    <a class="admin-chip" href="{{ route('dashboard') }}">
                        <i data-lucide="arrow-left"></i>
                        <span>Dashboard</span>
                    </a>
                    <div class="admin-chip admin-chip--ghost">
                        <i data-lucide="shield-check"></i>
                        <span>Dev only</span>
                    </div>
                </div>
            </header>

            @if ($status)
                <div class="flash flash-success admin-flash" data-flash>
                    <span>{{ $status }}</span>
                    <button class="icon-btn flash-close" type="button" data-flash-close aria-label="Close">
                        <i data-lucide="x"></i>
                    </button>
                </div>
            @endif

            <div class="admin-sections">
                <section class="admin-section is-active" data-section="overview">
                    <div class="admin-stats-grid">
                        <div class="admin-stat">
                            <div class="admin-stat__label">Users</div>
                            <div class="admin-stat__value">{{ $stats['users'] }}</div>
                        </div>
                        <div class="admin-stat">
                            <div class="admin-stat__label">Active users</div>
                            <div class="admin-stat__value">{{ $stats['active_users'] }}</div>
                        </div>
                        <div class="admin-stat">
                            <div class="admin-stat__label">Rooms</div>
                            <div class="admin-stat__value">{{ $stats['rooms'] }}</div>
                        </div>
                        <div class="admin-stat">
                            <div class="admin-stat__label">Messages</div>
                            <div class="admin-stat__value">{{ $stats['messages'] }}</div>
                        </div>
                        <div class="admin-stat">
                            <div class="admin-stat__label">Questions</div>
                            <div class="admin-stat__value">{{ $stats['questions'] }}</div>
                        </div>
                        <div class="admin-stat">
                            <div class="admin-stat__label">Participants</div>
                            <div class="admin-stat__value">{{ $stats['participants'] }}</div>
                        </div>
                    </div>

                    <div class="admin-grid admin-grid--offset-top">
                        <div class="admin-card admin-card--stretch">
                            <div class="admin-card__header">
                                <div>
                                    <h2 class="admin-card__title">Top rooms by messages</h2>
                                    <p class="admin-card__subtitle">Live snapshot</p>
                                </div>
                            </div>
                            <div class="admin-card__body admin-card__body--spaced">
                                <div class="admin-table-wrapper">
                                    <table class="admin-table">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Owner</th>
                                                <th class="text-right">Messages</th>
                                                <th class="text-right">Questions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($topRooms as $room)
                                                <tr>
                                                    <td>{{ $room->title }}</td>
                                                    <td class="admin-muted">{{ $room->owner?->name ?? '-' }}</td>
                                                    <td class="text-right">{{ $room->messages_count }}</td>
                                                    <td class="text-right">{{ $room->questions_count }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="admin-muted">No rooms yet.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="admin-column">
                            <div class="admin-card">
                                <div class="admin-card__header">
                                    <h2 class="admin-card__title">Last invite usage</h2>
                                    <p class="admin-card__subtitle">Last successful redemption</p>
                                </div>
                                <div class="admin-card__body">
                                    @if ($latestInviteUsage)
                                        <div class="admin-grid admin-grid--cols-2">
                                            <div class="admin-info">
                                                <span class="admin-info__label">Code</span>
                                                <span class="admin-info__value">{{ $latestInviteUsage->code }}</span>
                                            </div>
                                            <div class="admin-info">
                                                <span class="admin-info__label">Status</span>
                                                <span class="admin-chip admin-chip--muted">Used</span>
                                            </div>
                                            <div class="admin-info">
                                                <span class="admin-info__label">User</span>
                                                <span class="admin-info__value">{{ $latestInviteUsage->usedBy?->name ?? '-' }}</span>
                                            </div>
                                            <div class="admin-info">
                                                <span class="admin-info__label">Used at</span>
                                                <span class="admin-info__value">{{ $latestInviteUsage->used_at?->format('Y-m-d H:i') }}</span>
                                            </div>
                                            <div class="admin-info">
                                                <span class="admin-info__label">Created</span>
                                                <span class="admin-info__value">{{ $latestInviteUsage->created_at?->format('Y-m-d H:i') }}</span>
                                            </div>
                                        </div>
                                        <div class="admin-card__footer">
                                            <button class="admin-link-btn" type="button" data-section-target="invites">Manage invite codes</button>
                                        </div>
                                    @else
                                        <div class="admin-empty">No invite usage yet.</div>
                                    @endif
                                </div>
                            </div>

                            <div class="admin-card">
                                <div class="admin-card__header">
                                    <h2 class="admin-card__title">System health</h2>
                                </div>
                                <div class="admin-card__body">
                                    <div class="admin-health">
                                        @foreach($health as $item)
                                            <div class="admin-health__row">
                                                <span>{{ $item['label'] }}</span>
                                                <span class="admin-chip {{ $item['ok'] ? 'admin-chip--success' : 'admin-chip--muted' }}">
                                                    {{ $item['ok'] ? 'OK' : 'Check' }} - {{ $item['details'] }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="admin-section" data-section="sessions">
                    <div class="admin-grid">
                        <div class="admin-card">
                            <div class="admin-card__header">
                                <div>
                                    <h2 class="admin-card__title">Sessions & security</h2>
                                    <p class="admin-card__subtitle">Quick notes for handling sessions.</p>
                                </div>
                            </div>
                            <div class="admin-card__body">
                                <div class="admin-list">
                                    <div class="admin-list__item">
                                        <span>Password reset</span>
                                        <span class="admin-muted">Only via email flow</span>
                                    </div>
                                    <div class="admin-list__item">
                                        <span>Mass operations</span>
                                        <span class="admin-muted">Restricted and logged</span>
                                    </div>
                                    <div class="admin-list__item">
                                        <span>Active users</span>
                                        <span class="admin-chip admin-chip--ghost">{{ $stats['active_users'] }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="admin-card">
                            <div class="admin-card__header">
                                <h2 class="admin-card__title">Impersonation</h2>
                            </div>
                            <div class="admin-card__body">
                                <p class="admin-muted">Impersonation & forced logout tools are disabled in this build. Use database/session management manually if needed.</p>
                                <div class="admin-chip admin-chip--muted">Disabled</div>
                            </div>
                        </div>
                    </div>
                </section>
                <section class="admin-section" data-section="invites">
                    <div class="admin-card">
                        <div class="admin-card__header">
                            <div>
                                <h2 class="admin-card__title">Invite codes</h2>
                                <p class="admin-card__subtitle">Generate, track and revoke invite codes.</p>
                            </div>
                            <div class="admin-actions">
                                <form method="POST" action="{{ route('admin.invites.store') }}">
                                    @csrf
                                    <button class="admin-btn admin-btn--ghost" type="submit">Generate random</button>
                                </form>
                                <form class="admin-inline-form" method="POST" action="{{ route('admin.invites.store') }}">
                                    @csrf
                                    <input class="admin-input" type="text" name="code" placeholder="Custom code">
                                    <button class="admin-btn admin-btn--primary" type="submit">Save</button>
                                </form>
                            </div>
                        </div>

                        <div class="admin-table-wrapper">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Status</th>
                                        <th>Used by</th>
                                        <th>Created</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($inviteCodes as $invite)
                                        <tr>
                                            <td class="admin-code">
                                                <span class="admin-mono">{{ $invite->code }}</span>
                                                <button class="admin-copy-btn" type="button" data-copy="{{ $invite->code }}" title="Copy code">
                                                    <i data-lucide="copy"></i>
                                                </button>
                                            </td>
                                            <td>
                                                @if($invite->used_at)
                                                    <span class="admin-chip admin-chip--muted">Used</span>
                                                @else
                                                    <span class="admin-chip admin-chip--success">Unused</span>
                                                @endif
                                            </td>
                                            <td class="admin-muted">
                                                @if($invite->used_at)
                                                    Used by <span class="admin-strong">{{ $invite->usedBy?->name ?? '-' }}</span> at {{ $invite->used_at->format('Y-m-d H:i') }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="admin-muted">{{ $invite->created_at?->format('Y-m-d H:i') }}</td>
                                            <td class="text-right">
                                                <form method="POST" action="{{ route('admin.invites.destroy', $invite) }}" onsubmit="return confirm('Delete this invite code?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="admin-btn admin-btn--ghost" type="submit">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="admin-muted">No invite codes yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="admin-block">
                            <h3 class="admin-block__title">Recently used</h3>
                            <div class="admin-table-wrapper">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>User</th>
                                            <th>Used at</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($recentUsedInvites as $invite)
                                            <tr>
                                                <td class="admin-mono">{{ $invite->code }}</td>
                                                <td class="admin-muted">{{ $invite->usedBy?->name ?? '-' }}</td>
                                                <td>{{ $invite->used_at?->format('Y-m-d H:i') }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="admin-muted">No usage yet.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>
                <section class="admin-section" data-section="rooms">
                    <div class="admin-card">
                        <div class="admin-card__header admin-card__header--between">
                            <div>
                                <h2 class="admin-card__title">Rooms</h2>
                                <p class="admin-card__subtitle">Browse, inspect and jump into rooms.</p>
                            </div>
                            <div class="admin-tabs" data-rooms-tabs>
                                <button class="admin-tab is-active" type="button" data-rooms-tab="all">All rooms</button>
                                <button class="admin-tab" type="button" data-rooms-tab="top">Top by messages</button>
                            </div>
                        </div>
                        <div class="admin-card__body admin-scope" data-scope="rooms">
                            <div class="rooms-view" data-rooms-view="all">
                                <div class="admin-table-wrapper">
                                    <table class="admin-table">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Slug</th>
                                                <th>Owner</th>
                                                <th class="text-right">Messages</th>
                                                <th class="text-right">Questions</th>
                                                <th class="text-right">Bans</th>
                                                <th>Updated</th>
                                                <th class="text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($rooms as $room)
                                                <tr>
                                                    <td>{{ $room->title }}</td>
                                                    <td class="admin-code">
                                                        <span class="admin-mono">{{ $room->slug }}</span>
                                                        <button class="admin-copy-btn" type="button" data-copy="{{ $room->slug }}" title="Copy slug">
                                                            <i data-lucide="copy"></i>
                                                        </button>
                                                    </td>
                                                    <td class="admin-muted">{{ $room->owner?->name ?? '-' }}</td>
                                                    <td class="text-right">{{ $room->messages_count }}</td>
                                                    <td class="text-right">{{ $room->questions_count }}</td>
                                                    <td class="text-right">{{ $room->bans_count }}</td>
                                                    <td class="admin-muted">{{ $room->updated_at?->format('Y-m-d H:i') }}</td>
                                                    <td class="text-right admin-actions">
                                                        <a class="admin-btn admin-btn--ghost" href="{{ route('rooms.public', $room->slug) }}" target="_blank">Open</a>
                                                        <a class="admin-btn admin-btn--ghost" href="{{ route('rooms.questionsPanel', $room) }}">Questions</a>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="8" class="admin-muted">No rooms found.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="admin-pagination">
                                    {{ $rooms->links() }}
                                </div>
                            </div>

                            <div class="rooms-view rooms-view--hidden" data-rooms-view="top">
                                <div class="admin-table-wrapper">
                                    <table class="admin-table">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Owner</th>
                                                <th class="text-right">Messages</th>
                                                <th class="text-right">Questions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($topRooms as $room)
                                                <tr>
                                                    <td>{{ $room->title }}</td>
                                                    <td class="admin-muted">{{ $room->owner?->name ?? '-' }}</td>
                                                    <td class="text-right">{{ $room->messages_count }}</td>
                                                    <td class="text-right">{{ $room->questions_count }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="admin-muted">No rooms yet.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                <section class="admin-section" data-section="bans">
                    <div class="admin-card">
                        <div class="admin-card__header">
                            <div>
                                <h2 class="admin-card__title">Ban controls</h2>
                                <p class="admin-card__subtitle">Create a ban entry or review recent bans.</p>
                            </div>
                        </div>

                        <form class="admin-form-grid" method="POST" action="{{ route('admin.bans.store') }}">
                            @csrf
                            <label class="admin-field">
                                <span class="admin-label">Room</span>
                                <select class="admin-select" name="room_id" required>
                                    <option value="" disabled selected>Select room</option>
                                    @foreach($allRooms as $room)
                                        <option value="{{ $room->id }}">{{ $room->title }} ({{ $room->slug }})</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="admin-field">
                                <span class="admin-label">Participant ID (optional)</span>
                                <input class="admin-input" type="number" name="participant_id" placeholder="participant id">
                            </label>
                            <label class="admin-field">
                                <span class="admin-label">Session token</span>
                                <input class="admin-input" type="text" name="session_token" placeholder="session token">
                            </label>
                            <label class="admin-field">
                                <span class="admin-label">Display name</span>
                                <input class="admin-input" type="text" name="display_name" placeholder="Name to record">
                            </label>
                            <label class="admin-field">
                                <span class="admin-label">IP address</span>
                                <input class="admin-input" type="text" name="ip_address" placeholder="optional">
                            </label>
                            <label class="admin-field">
                                <span class="admin-label">Fingerprint</span>
                                <input class="admin-input" type="text" name="fingerprint" placeholder="optional">
                            </label>
                            <div class="admin-form-actions">
                                <button class="admin-btn admin-btn--primary" type="submit">Add ban</button>
                            </div>
                        </form>

                        <div class="admin-table-wrapper">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Room</th>
                                        <th>Participant</th>
                                        <th>Session</th>
                                        <th>IP</th>
                                        <th>Fingerprint</th>
                                        <th>Created</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentBans as $ban)
                                        <tr>
                                            <td>{{ $ban->room?->title ?? '-' }}</td>
                                            <td>{{ $ban->participant?->display_name ?? $ban->display_name ?? '-' }}</td>
                                            <td class="admin-mono">{{ \Illuminate\Support\Str::limit($ban->session_token, 16) }}</td>
                                            <td>{{ $ban->ip_address ?? '-' }}</td>
                                            <td class="admin-mono">{{ $ban->fingerprint ? \Illuminate\Support\Str::limit($ban->fingerprint, 12) : '-' }}</td>
                                            <td class="admin-muted">{{ $ban->created_at?->format('Y-m-d H:i') }}</td>
                                            <td class="text-right">
                                                <form method="POST" action="{{ route('admin.bans.destroy', $ban) }}" onsubmit="return confirm('Remove this ban?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="admin-btn admin-btn--ghost" type="submit">Remove</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="admin-muted">No bans yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
                <section class="admin-section" data-section="users">
                    <div class="admin-grid">
                        <div class="admin-card">
                            <div class="admin-card__header">
                                <div>
                                    <h2 class="admin-card__title">Recent users</h2>
                                    <p class="admin-card__subtitle">Last registered users</p>
                                </div>
                            </div>
                            <div class="admin-card__body admin-scope" data-scope="users">
                                <div class="admin-table-wrapper">
                                    <table class="admin-table">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Role</th>
                                                <th>Joined</th>
                                                <th class="text-right">Ban</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($recentUsers as $user)
                                                <tr>
                                                    <td>{{ $user->name }}</td>
                                                    <td class="admin-muted">{{ $user->email }}</td>
                                                    <td class="admin-muted">{{ $user->is_dev ? 'Dev' : 'User' }}</td>
                                                    <td class="admin-muted">{{ $user->created_at?->format('Y-m-d H:i') }}</td>
                                                    <td class="text-right">
                                                        <form class="admin-inline-form" method="POST" action="{{ route('admin.bans.store') }}">
                                                            @csrf
                                                            <input type="hidden" name="display_name" value="{{ $user->name }}">
                                                            <select class="admin-select" name="room_id" required>
                                                                <option value="" disabled selected>Room</option>
                                                                @foreach($allRooms as $room)
                                                                    <option value="{{ $room->id }}">{{ $room->title }}</option>
                                                                @endforeach
                                                            </select>
                                                            <button class="admin-btn admin-btn--ghost" type="submit">Ban</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="5" class="admin-muted">No users.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="admin-pagination">
                                    {{ $recentUsers->links() }}
                                </div>
                            </div>
                        </div>

                        <div class="admin-card">
                            <div class="admin-card__header">
                                <div>
                                    <h2 class="admin-card__title">Participants & fingerprints</h2>
                                    <p class="admin-card__subtitle">Recent participants with fingerprints/IP for quick bans</p>
                                </div>
                            </div>
                            <div class="admin-card__body admin-scope" data-scope="participants">
                                <div class="admin-table-wrapper">
                                    <table class="admin-table">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Room</th>
                                                <th>Fingerprint</th>
                                                <th>IP</th>
                                                <th>Session</th>
                                                <th class="text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($participants as $p)
                                                <tr>
                                                    <td>{{ $p->display_name ?? 'Guest' }}</td>
                                                    <td class="admin-muted">{{ $p->room?->title ?? '-' }}</td>
                                                    <td class="admin-code">
                                                        <span class="admin-mono">{{ \Illuminate\Support\Str::limit($p->fingerprint, 24) }}</span>
                                                        @if($p->fingerprint)
                                                            <button class="admin-copy-btn" type="button" data-copy="{{ $p->fingerprint }}" title="Copy fingerprint">
                                                                <i data-lucide="copy"></i>
                                                            </button>
                                                        @endif
                                                    </td>
                                                    <td class="admin-muted">{{ $p->ip_address ?? '-' }}</td>
                                                    <td class="admin-mono">{{ \Illuminate\Support\Str::limit($p->session_token, 24) }}</td>
                                                    <td class="text-right">
                                                        <form class="admin-inline-form" method="POST" action="{{ route('admin.bans.store') }}">
                                                            @csrf
                                                            <input type="hidden" name="participant_id" value="{{ $p->id }}">
                                                            <input type="hidden" name="session_token" value="{{ $p->session_token }}">
                                                            <input type="hidden" name="fingerprint" value="{{ $p->fingerprint }}">
                                                            <input type="hidden" name="ip_address" value="{{ $p->ip_address }}">
                                                            <input type="hidden" name="display_name" value="{{ $p->display_name }}">
                                                            <select class="admin-select" name="room_id" required>
                                                                <option value="" disabled selected>Room</option>
                                                                @foreach($allRooms as $room)
                                                                    <option value="{{ $room->id }}" @selected($room->id === $p->room_id)>{{ $room->title }}</option>
                                                                @endforeach
                                                            </select>
                                                            <button class="admin-btn admin-btn--ghost" type="submit">Ban</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="6" class="admin-muted">No participants.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="admin-pagination">
                                    {{ $participants->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                <section class="admin-section" data-section="content">
                    <div class="admin-card">
                        <div class="admin-card__header">
                            <div>
                                <h2 class="admin-card__title">Content & moderation</h2>
                                <p class="admin-card__subtitle">Rooms, messages, questions, centralized bans.</p>
                            </div>
                        </div>
                        <div class="admin-card__body">
                            <div class="admin-stats-grid admin-stats-grid--compact">
                                <div class="admin-stat">
                                    <div class="admin-stat__label">Messages per room</div>
                                    <div class="admin-stat__value">{{ $avgMessagesPerRoom }}</div>
                                </div>
                                <div class="admin-stat">
                                    <div class="admin-stat__label">Questions per room</div>
                                    <div class="admin-stat__value">{{ $avgQuestionsPerRoom }}</div>
                                </div>
                                <div class="admin-stat">
                                    <div class="admin-stat__label">Total bans</div>
                                    <div class="admin-stat__value">{{ $recentBans->count() }}</div>
                                </div>
                            </div>
                            <p class="admin-muted">Use “Rooms” and “Ban controls” tabs for detailed actions. Automated moderation is manual for now.</p>
                        </div>
                    </div>
                </section>

                <section class="admin-section" data-section="support">
                    <div class="admin-card">
                        <div class="admin-card__header">
                            <h2 class="admin-card__title">Support & communication</h2>
                        </div>
                        <div class="admin-card__body">
                            <div class="admin-list">
                                <div class="admin-list__item">
                                    <span>Contact</span>
                                    <a class="admin-link" href="mailto:zloydeveloper.info@gmail.com">zloydeveloper.info@gmail.com</a>
                                </div>
                                <div class="admin-list__item">
                                    <span>Repository</span>
                                    <a class="admin-link" href="https://github.com/KristopherZlo/live-chat-vamk" target="_blank" rel="noreferrer">GitHub link</a>
                                </div>
                                <div class="admin-list__item">
                                    <span>Privacy</span>
                                    <a class="admin-link" href="{{ route('privacy') }}" target="_blank">Privacy & terms</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="admin-section" data-section="logs">
                    <div class="admin-card">
                        <div class="admin-card__header">
                            <h2 class="admin-card__title">Logs & audit</h2>
                        </div>
                        <div class="admin-card__body">
                            <p class="admin-muted">Server logs live in <span class="admin-mono">storage/logs/laravel.log</span>. For production, forward logs to your platform or tail locally (e.g. <span class="admin-mono">php artisan tail</span>).</p>
                        </div>
                    </div>
                </section>

                <section class="admin-section" data-section="metrics">
                    <div class="admin-card">
                        <div class="admin-card__header">
                            <h2 class="admin-card__title">Metrics & health</h2>
                        </div>
                        <div class="admin-card__body">
                            <div class="admin-stats-grid admin-stats-grid--compact">
                                <div class="admin-stat">
                                    <div class="admin-stat__label">Rooms total</div>
                                    <div class="admin-stat__value">{{ $stats['rooms'] }}</div>
                                </div>
                                <div class="admin-stat">
                                    <div class="admin-stat__label">Messages total</div>
                                    <div class="admin-stat__value">{{ $stats['messages'] }}</div>
                                </div>
                                <div class="admin-stat">
                                    <div class="admin-stat__label">Participants total</div>
                                    <div class="admin-stat__value">{{ $stats['participants'] }}</div>
                                </div>
                                <div class="admin-stat">
                                    <div class="admin-stat__label">Active users</div>
                                    <div class="admin-stat__value">{{ $stats['active_users'] }}</div>
                                </div>
                            </div>
                            <p class="admin-muted">For deeper visibility plug your APM/logging provider here. This view mirrors the design mockup for quick health checks.</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sections = document.querySelectorAll('[data-section]');
            const navButtons = document.querySelectorAll('[data-section-target]');
            const sidebar = document.querySelector('[data-admin-sidebar]');
            const backdrop = document.querySelector('[data-sidebar-backdrop]');
            const closeSidebarBtn = document.querySelector('[data-sidebar-close]');
            const mobileToggle = document.querySelector('[data-mobile-nav-toggle]');
            let currentRoomsView = 'all';

            const closeSidebar = () => {
                sidebar?.classList.remove('is-open');
                backdrop?.classList.remove('is-visible');
            };
            const openSidebar = () => {
                sidebar?.classList.add('is-open');
                backdrop?.classList.add('is-visible');
            };

            const setSection = (target) => {
                sections.forEach((section) => {
                    const match = section.dataset.section === target;
                    section.classList.toggle('is-active', match);
                });
                navButtons.forEach((btn) => {
                    btn.classList.toggle('is-active', btn.dataset.sectionTarget === target);
                });
                if (sidebar && sidebar.classList.contains('is-open')) {
                    closeSidebar();
                }
            };

            const applyRoomsView = (view) => {
                currentRoomsView = view;
                const tabs = document.querySelectorAll('[data-rooms-tab]');
                const views = document.querySelectorAll('[data-rooms-view]');
                tabs.forEach((tab) => tab.classList.toggle('is-active', tab.dataset.roomsTab === view));
                views.forEach((panel) => panel.classList.toggle('rooms-view--hidden', panel.dataset.roomsView !== view));
            };

            const bindRoomsTabs = () => {
                document.querySelectorAll('[data-rooms-tab]').forEach((tab) => {
                    tab.addEventListener('click', () => applyRoomsView(tab.dataset.roomsTab || 'all'));
                });
            };

            const bindNavButtons = () => {
                navButtons.forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const target = btn.dataset.sectionTarget;
                        if (!target) return;
                        setSection(target);
                    });
                });
            };

            const hydrateScope = (scopeName, url) => {
                const scope = document.querySelector(`[data-scope=\"${scopeName}\"]`);
                if (!scope) return;
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then((res) => res.text())
                    .then((html) => {
                        const doc = new DOMParser().parseFromString(html, 'text/html');
                        const fresh = doc.querySelector(`[data-scope=\"${scopeName}\"]`);
                        if (!fresh) return;
                        scope.replaceWith(fresh);
                        bindAjaxPagination();
                        bindRoomsTabs();
                        applyRoomsView(currentRoomsView);
                    })
                    .catch(() => {});
            };

            const bindAjaxPagination = () => {
                document.querySelectorAll('[data-scope]').forEach((scope) => {
                    const scopeName = scope.dataset.scope;
                    scope.querySelectorAll('.admin-pagination a').forEach((link) => {
                        link.addEventListener('click', (event) => {
                            event.preventDefault();
                            hydrateScope(scopeName, link.href);
                        });
                    });
                });
            };

            if (mobileToggle && sidebar) {
                mobileToggle.addEventListener('click', openSidebar);
            }
            if (closeSidebarBtn) {
                closeSidebarBtn.addEventListener('click', closeSidebar);
            }
            if (backdrop) {
                backdrop.addEventListener('click', closeSidebar);
            }
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeSidebar();
                }
            });

            document.addEventListener('click', (event) => {
                const btn = event.target.closest('[data-copy]');
                if (!btn) return;
                const value = btn.dataset.copy;
                if (!value) return;
                navigator.clipboard?.writeText(value).then(() => {
                    btn.classList.add('is-copied');
                    setTimeout(() => btn.classList.remove('is-copied'), 800);
                });
            });

            bindNavButtons();
            bindRoomsTabs();
            bindAjaxPagination();
            setSection('overview');
            applyRoomsView('all');
        });
    </script>
</x-app-layout>

