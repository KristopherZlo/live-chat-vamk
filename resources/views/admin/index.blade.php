@push('styles')
    @vite('resources/css/admin.css')
@endpush

<x-app-layout page-class="page-admin admin-shell">
    @php
        $status = session('status');
        $latestInviteUsage = $recentUsedInvites->first();
        $avgMessagesPerRoom = $stats['rooms'] ? round($stats['messages'] / max($stats['rooms'], 1), 1) : 0;
        $avgQuestionsPerRoom = $stats['rooms'] ? round($stats['questions'] / max($stats['rooms'], 1), 1) : 0;
        $supportEmail = config('ghostroom.links.support_email');
        $githubRepoUrl = config('ghostroom.links.github_repository');
        $authUser = auth()->user();
        $userInitials = 'AD';
        if ($authUser && $authUser->name) {
            $initials = \Illuminate\Support\Str::of($authUser->name)
                ->trim()
                ->explode(' ')
                ->map(fn ($part) => \Illuminate\Support\Str::substr($part, 0, 1))
                ->implode('');
            $userInitials = strtoupper($initials ?: 'AD');
        }
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
                    <div class="admin-nav-label">Product</div>
                    <button class="admin-nav-btn" type="button" data-section-target="updates">
                        <span class="admin-dot admin-dot--info"></span>
                        Updates & releases
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
                <span class="admin-muted">v{{ $appVersion ?? config('app.version', '1.0.0') }}</span>
            </div>
        </aside>

        <div class="admin-main">
            <header class="admin-topbar">
                <div class="admin-topbar__left">
                    <div class="admin-topbar__brand admin-topbar__brand--mobile">
                        <button class="admin-icon-btn admin-nav-toggle admin-nav-toggle--mobile" type="button" aria-label="Open navigation" data-nav-toggle>
                            <i data-lucide="menu"></i>
                        </button>
                        <div>
                            <div class="admin-eyebrow">Admin panel</div>
                            <div class="admin-topbar__title">Visible only to developers</div>
                        </div>
                    </div>
                    <div class="admin-topbar__brand admin-topbar__brand--desktop">
                        <span class="admin-eyebrow">Realtime admin overview</span>
                        <span class="admin-topbar__meta">
                            Users: <span class="admin-strong">{{ $stats['users'] }}</span> |
                            Active: <span class="admin-topbar__accent">{{ $stats['active_users'] }}</span> |
                            Rooms: <span class="admin-muted">{{ $stats['rooms'] }}</span>
                        </span>
                    </div>
                </div>
                <div class="admin-topbar__actions">
                    <div class="admin-topbar__meta admin-topbar__meta--compact">
                        Users: <span class="admin-strong">{{ $stats['users'] }}</span> |
                        Active: <span class="admin-topbar__accent">{{ $stats['active_users'] }}</span>
                    </div>
                    <div class="admin-avatar admin-avatar--small">{{ $userInitials }}</div>
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

                    <div class="admin-grid admin-grid--offset-top admin-grid--overview">
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
                                            <td>
                                                <div class="admin-code-chip">
                                                    <span class="admin-mono">{{ $invite->code }}</span>
                                                    <button class="admin-copy-btn admin-copy-btn--tiny" type="button" data-copy="{{ $invite->code }}" title="Copy code">
                                                        <i data-lucide="copy"></i>
                                                    </button>
                                                </div>
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
                                            <td>
                                                <div class="admin-code-chip">
                                                    <span class="admin-mono">{{ $room->slug }}</span>
                                                    <button class="admin-copy-btn admin-copy-btn--tiny" type="button" data-copy="{{ $room->slug }}" title="Copy slug">
                                                        <i data-lucide="copy"></i>
                                                    </button>
                                                </div>
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
                                    <div class="admin-pagination__meta">Page {{ $rooms->currentPage() }} / {{ $rooms->lastPage() }}</div>
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
                                    <div class="admin-pagination__meta">Page {{ $recentUsers->currentPage() }} / {{ $recentUsers->lastPage() }}</div>
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
                                                    <td>
                                                        <div class="admin-code-chip">
                                                            <span class="admin-mono">{{ \Illuminate\Support\Str::limit($p->fingerprint, 24) }}</span>
                                                            @if($p->fingerprint)
                                                                <button class="admin-copy-btn admin-copy-btn--tiny" type="button" data-copy="{{ $p->fingerprint }}" title="Copy fingerprint">
                                                                    <i data-lucide="copy"></i>
                                                                </button>
                                                            @endif
                                                        </div>
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
                                    <div class="admin-pagination__meta">Page {{ $participants->currentPage() }} / {{ $participants->lastPage() }}</div>
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
                <p class="admin-muted">Use "Rooms" and "Ban controls" tabs for detailed actions. Automated moderation is manual for now.</p>
            </div>
        </div>
    </section>

                <section class="admin-section" data-section="updates" id="updates">
                    @if ($errors->any())
                        <div class="flash flash-danger admin-flash" data-flash>
                            <span>{{ $errors->first() }}</span>
                            <button class="icon-btn flash-close" type="button" data-flash-close aria-label="Close">
                                <i data-lucide="x"></i>
                            </button>
                        </div>
                    @endif
                    <div class="admin-grid admin-grid--cols-2">
                        <div class="admin-column">
                            <div class="admin-card">
                                <div class="admin-card__header">
                                    <div>
                                        <h2 class="admin-card__title">Project version</h2>
                                        <p class="admin-card__subtitle">Controls the "what's new" modal version gate</p>
                                    </div>
                                </div>
                                <div class="admin-card__body">
                                    <div class="admin-list">
                                        <div class="admin-list__item">
                                            <span>Current version</span>
                                            <span class="admin-chip admin-chip--ghost">{{ $appVersion ?? '1.0.0' }}</span>
                                        </div>
                                    </div>
                                    <form class="admin-form-grid" method="POST" action="{{ route('admin.updates.version') }}">
                                        @csrf
                                        <div class="admin-field">
                                            <label class="admin-label" for="appVersion">Set version</label>
                                            <input class="admin-input" id="appVersion" name="version" type="text" value="{{ old('version', $appVersion) }}" placeholder="e.g. 1.3.0">
                                        </div>
                                        <div class="admin-field" style="align-self: end;">
                                            <button class="admin-btn admin-btn--primary" type="submit">Update version</button>
                                        </div>
                                    </form>
                                    <p class="admin-muted">Users see the what's-new modal when their stored version is lower than this value.</p>
                                </div>
                            </div>

                            @php
                                $releaseFormAction = $editingRelease
                                    ? route('admin.updates.releases.update', $editingRelease)
                                    : route('admin.updates.releases.store');
                                $releasePublishedAt = old('published_at', optional($editingRelease?->published_at)->format('Y-m-d\TH:i'));
                            @endphp
                            <div class="admin-card">
                                <div class="admin-card__header">
                                    <div>
                                        <h2 class="admin-card__title">{{ $editingRelease ? 'Edit “What\'s new” modal' : 'New “What\'s new” modal' }}</h2>
                                        <p class="admin-card__subtitle">Markdown supported. Publish to show in the modal.</p>
                                    </div>
                                    @if($editingRelease)
                                        <a class="admin-link-btn" href="{{ route('admin.index') }}#updates">Cancel edit</a>
                                    @endif
                                </div>
                                <div class="admin-card__body">
                                    <form class="admin-form" method="POST" action="{{ $releaseFormAction }}" enctype="multipart/form-data">
                                        @csrf
                                        @if($editingRelease)
                                            @method('PATCH')
                                        @endif
                                        <div class="admin-form-grid">
                                            <div class="admin-field">
                                                <label class="admin-label" for="releaseTitle">Title</label>
                                                <input class="admin-input" id="releaseTitle" name="title" type="text" value="{{ old('title', $editingRelease->title ?? '') }}" required>
                                            </div>
                                            <div class="admin-field">
                                                <label class="admin-label" for="releaseVersion">Version</label>
                                                <input class="admin-input" id="releaseVersion" name="version" type="text" value="{{ old('version', $editingRelease->version ?? '') }}" required placeholder="1.3.0">
                                            </div>
                                            <div class="admin-field">
                                                <label class="admin-label" for="releasePublishedAt">Publish at</label>
                                                <input class="admin-input" id="releasePublishedAt" name="published_at" type="datetime-local" value="{{ $releasePublishedAt }}">
                                            </div>
                                            <div class="admin-field">
                                                <label class="admin-label" for="releaseImage">Cover image</label>
                                                <input class="admin-input" id="releaseImage" name="image" type="file" accept="image/*">
                                                @if($editingRelease?->cover_url)
                                                    <label class="admin-label">
                                                        <input type="checkbox" name="remove_image" value="1">
                                                        <span>Remove current image</span>
                                                    </label>
                                                    <div class="admin-muted">Current: <a href="{{ $editingRelease->cover_url }}" target="_blank" rel="noreferrer">view</a></div>
                                                @endif
                                            </div>
                                            <div class="admin-field">
                                                <label class="admin-label" for="releasePublished">
                                                    <input type="checkbox" id="releasePublished" name="is_published" value="1" {{ old('is_published', $editingRelease ? $editingRelease->is_published : true) ? 'checked' : '' }}>
                                                    <span>Publish</span>
                                                </label>
                                                <label class="admin-label" for="setAsVersion">
                                                    <input type="checkbox" id="setAsVersion" name="set_as_version" value="1" {{ old('set_as_version', !$editingRelease || ($editingRelease && $editingRelease->version === $appVersion)) ? 'checked' : '' }}>
                                                    <span>Mark as current version</span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="admin-field">
                                            <label class="admin-label" for="releaseBody">Content (Markdown)</label>
                                            <textarea class="admin-input" id="releaseBody" name="body" rows="8" required>{{ old('body', $editingRelease->body ?? '') }}</textarea>
                                        </div>
                                        <div class="admin-actions" style="margin-top: 0.5rem;">
                                            <button class="admin-btn admin-btn--primary" type="submit">{{ $editingRelease ? 'Update release' : 'Save release' }}</button>
                                            @if($editingRelease)
                                                <a class="admin-btn admin-btn--ghost" href="{{ route('admin.index') }}#updates">Cancel</a>
                                            @endif
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        @php
                            $blogFormAction = $editingBlog
                                ? route('admin.updates.posts.update', $editingBlog)
                                : route('admin.updates.posts.store');
                            $blogPublishedAt = old('published_at', optional($editingBlog?->published_at)->format('Y-m-d\TH:i'));
                        @endphp
                        <div class="admin-column">
                            <div class="admin-card">
                                <div class="admin-card__header">
                                    <div>
                                        <h2 class="admin-card__title">{{ $editingBlog ? 'Edit update post' : 'New update post' }}</h2>
                                        <p class="admin-card__subtitle">Publish to show on the public updates page.</p>
                                    </div>
                                    @if($editingBlog)
                                        <a class="admin-link-btn" href="{{ route('admin.index') }}#updates">Cancel edit</a>
                                    @endif
                                </div>
                                <div class="admin-card__body">
                                    <form class="admin-form" method="POST" action="{{ $blogFormAction }}" enctype="multipart/form-data">
                                        @csrf
                                        @if($editingBlog)
                                            @method('PATCH')
                                        @endif
                                        <div class="admin-form-grid">
                                            <div class="admin-field">
                                                <label class="admin-label" for="postTitle">Title</label>
                                                <input class="admin-input" id="postTitle" name="title" type="text" value="{{ old('title', $editingBlog->title ?? '') }}" required>
                                            </div>
                                            <div class="admin-field">
                                                <label class="admin-label" for="postSlug">Slug</label>
                                                <input class="admin-input" id="postSlug" name="slug" type="text" value="{{ old('slug', $editingBlog->slug ?? '') }}" placeholder="auto-generated if empty">
                                            </div>
                                            <div class="admin-field">
                                                <label class="admin-label" for="postPublishedAt">Publish at</label>
                                                <input class="admin-input" id="postPublishedAt" name="published_at" type="datetime-local" value="{{ $blogPublishedAt }}">
                                            </div>
                                            <div class="admin-field">
                                                <label class="admin-label" for="postImage">Cover image</label>
                                                <input class="admin-input" id="postImage" name="image" type="file" accept="image/*">
                                                @if($editingBlog?->cover_url)
                                                    <label class="admin-label">
                                                        <input type="checkbox" name="remove_image" value="1">
                                                        <span>Remove current image</span>
                                                    </label>
                                                    <div class="admin-muted">Current: <a href="{{ $editingBlog->cover_url }}" target="_blank" rel="noreferrer">view</a></div>
                                                @endif
                                            </div>
                                            <div class="admin-field">
                                                <label class="admin-label" for="postPublished">
                                                    <input type="checkbox" id="postPublished" name="is_published" value="1" {{ old('is_published', $editingBlog ? $editingBlog->is_published : false) ? 'checked' : '' }}>
                                                    <span>Publish</span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="admin-field">
                                            <label class="admin-label" for="postExcerpt">Excerpt</label>
                                            <textarea class="admin-input" id="postExcerpt" name="excerpt" rows="2" placeholder="Optional short summary">{{ old('excerpt', $editingBlog->excerpt ?? '') }}</textarea>
                                        </div>
                                        <div class="admin-field">
                                            <label class="admin-label" for="postBody">Content (Markdown)</label>
                                            <textarea class="admin-input" id="postBody" name="body" rows="10" required>{{ old('body', $editingBlog->body ?? '') }}</textarea>
                                        </div>
                                        <div class="admin-actions" style="margin-top: 0.5rem;">
                                            <button class="admin-btn admin-btn--primary" type="submit">{{ $editingBlog ? 'Update post' : 'Save post' }}</button>
                                            @if($editingBlog)
                                                <a class="admin-btn admin-btn--ghost" href="{{ route('admin.index') }}#updates">Cancel</a>
                                            @endif
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="admin-grid admin-grid--cols-2 admin-grid--offset-top">
                        <div class="admin-card">
                            <div class="admin-card__header">
                                <div>
                                    <h2 class="admin-card__title">Releases & modals</h2>
                                    <p class="admin-card__subtitle">Latest first</p>
                                </div>
                            </div>
                            <div class="admin-card__body admin-table-wrapper">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Version</th>
                                            <th>Title</th>
                                            <th>Status</th>
                                            <th>Published</th>
                                            <th class="text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($whatsNewEntries as $release)
                                            <tr>
                                                <td class="admin-mono">{{ $release->version ?? '—' }}</td>
                                                <td>{{ $release->title }}</td>
                                                <td>
                                                    @if($release->is_live)
                                                        <span class="admin-chip admin-chip--success">Published</span>
                                                    @elseif($release->is_published)
                                                        <span class="admin-chip admin-chip--warn">Scheduled</span>
                                                    @else
                                                        <span class="admin-chip admin-chip--muted">Draft</span>
                                                    @endif
                                                </td>
                                                <td class="admin-muted">{{ $release->published_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                                <td class="text-right">
                                                    <div class="admin-inline-actions">
                                                        <a class="admin-link-btn" href="{{ route('admin.index', ['edit_release' => $release->id]) }}#updates">Edit</a>
                                                        @if($release->version)
                                                            <form method="POST" action="{{ route('admin.updates.version') }}" style="display:inline;">
                                                                @csrf
                                                                <input type="hidden" name="version" value="{{ $release->version }}">
                                                                <button class="admin-link-btn" type="submit" title="Mark version {{ $release->version }}">Use</button>
                                                            </form>
                                                        @endif
                                                        <form method="POST" action="{{ route('admin.updates.releases.destroy', $release) }}" style="display:inline;" onsubmit="return confirm('Delete this release?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button class="admin-link-btn admin-link-btn--danger" type="submit">Delete</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="admin-muted">No releases yet.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="admin-pagination">
                                <div class="admin-pagination__meta">Page {{ $whatsNewEntries->currentPage() }} / {{ $whatsNewEntries->lastPage() }}</div>
                                {{ $whatsNewEntries->links() }}
                            </div>
                        </div>
                        <div class="admin-card">
                            <div class="admin-card__header">
                                <div>
                                    <h2 class="admin-card__title">Blog posts</h2>
                                    <p class="admin-card__subtitle">Public updates page</p>
                                </div>
                            </div>
                            <div class="admin-card__body admin-table-wrapper">
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Status</th>
                                            <th>Published</th>
                                            <th class="text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($blogUpdates as $post)
                                            <tr>
                                                <td>{{ $post->title }}</td>
                                                <td>
                                                    @if($post->is_live)
                                                        <span class="admin-chip admin-chip--success">Published</span>
                                                    @elseif($post->is_published)
                                                        <span class="admin-chip admin-chip--warn">Scheduled</span>
                                                    @else
                                                        <span class="admin-chip admin-chip--muted">Draft</span>
                                                    @endif
                                                </td>
                                                <td class="admin-muted">{{ $post->published_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                                <td class="text-right">
                                                    <div class="admin-inline-actions">
                                                        <a class="admin-link-btn" href="{{ route('admin.index', ['edit_post' => $post->id]) }}#updates">Edit</a>
                                                        <form method="POST" action="{{ route('admin.updates.posts.destroy', $post) }}" style="display:inline;" onsubmit="return confirm('Delete this post?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button class="admin-link-btn admin-link-btn--danger" type="submit">Delete</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="admin-muted">No update posts yet.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="admin-pagination">
                                <div class="admin-pagination__meta">Page {{ $blogUpdates->currentPage() }} / {{ $blogUpdates->lastPage() }}</div>
                                {{ $blogUpdates->links() }}
                            </div>
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
                                    <a class="admin-link" href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
                                </div>
                                <div class="admin-list__item">
                                    <span>Repository</span>
                                    <a class="admin-link" href="{{ $githubRepoUrl }}" target="_blank" rel="noreferrer">GitHub link</a>
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
                            @if($auditLogs->isEmpty())
                                <p class="admin-muted">No audit events yet.</p>
                            @else
                                <div class="admin-table-wrapper">
                                    <table class="admin-table">
                                        <thead>
                                            <tr>
                                                <th>When</th>
                                                <th>Action</th>
                                                <th>Actor</th>
                                                <th>Room</th>
                                                <th>Target</th>
                                                <th>IP</th>
                                                <th>Request</th>
                                                <th>Meta</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($auditLogs as $log)
                                                @php
                                                    $actor = $log->actorUser?->name
                                                        ?? $log->actorParticipant?->display_name
                                                        ?? 'system';
                                                    $roomLabel = $log->room?->title ?? '-';
                                                    $target = $log->target_type
                                                        ? ($log->target_type.($log->target_id ? '#'.$log->target_id : ''))
                                                        : '-';
                                                    $metadataJson = $log->metadata
                                                        ? json_encode($log->metadata, JSON_UNESCAPED_SLASHES)
                                                        : '';
                                                @endphp
                                                <tr>
                                                    <td class="admin-muted">{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                                                    <td class="admin-mono">{{ $log->action }}</td>
                                                    <td>{{ $actor }}</td>
                                                    <td>{{ $roomLabel }}</td>
                                                    <td class="admin-mono">{{ $target }}</td>
                                                    <td class="admin-mono">{{ $log->ip_address ?? '-' }}</td>
                                                    <td class="admin-mono">
                                                        {{ $log->request_id ? \Illuminate\Support\Str::limit($log->request_id, 18) : '-' }}
                                                    </td>
                                                    <td class="admin-muted">
                                                        {{ $metadataJson ? \Illuminate\Support\Str::limit($metadataJson, 80) : '-' }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
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
            const layout = document.querySelector('.admin-layout');
            const backdrop = document.querySelector('[data-sidebar-backdrop]');
            const closeSidebarBtn = document.querySelector('[data-sidebar-close]');
            const navToggles = document.querySelectorAll('[data-nav-toggle]');
            const desktopMedia = window.matchMedia('(min-width: 961px)');
            let currentRoomsView = 'all';
            const initialHash = window.location.hash ? window.location.hash.replace('#', '') : '';
            const renderIcons = (root = document) => {
                if (window.lucide?.createIcons && window.lucide?.icons) {
                    window.lucide.createIcons({ icons: window.lucide.icons }, root);
                }
            };

            const closeSidebar = () => {
                sidebar?.classList.remove('is-open');
                backdrop?.classList.remove('is-visible');
            };
            const openSidebar = () => {
                layout?.classList.remove('is-collapsed');
                sidebar?.classList.add('is-open');
                backdrop?.classList.add('is-visible');
            };

            const toggleSidebar = () => {
                if (desktopMedia.matches) {
                    layout?.classList.toggle('is-collapsed');
                    return;
                }
                if (sidebar?.classList.contains('is-open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            };

            const setSection = (target) => {
                const key = Array.from(sections).some((section) => section.dataset.section === target)
                    ? target
                    : 'overview';
                sections.forEach((section) => {
                    const match = section.dataset.section === key;
                    section.classList.toggle('is-active', match);
                });
                navButtons.forEach((btn) => {
                    btn.classList.toggle('is-active', btn.dataset.sectionTarget === key);
                });
                if (sidebar && sidebar.classList.contains('is-open')) {
                    closeSidebar();
                }
                renderIcons();
                if (key) {
                    history.replaceState(null, '', `#${key}`);
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
                        renderIcons();
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

            navToggles.forEach((toggle) => {
                toggle.addEventListener('click', toggleSidebar);
            });
            if (closeSidebarBtn) {
                closeSidebarBtn.addEventListener('click', closeSidebar);
            }
            if (backdrop) {
                backdrop.addEventListener('click', closeSidebar);
            }
            desktopMedia.addEventListener('change', (event) => {
                if (!event.matches) {
                    layout?.classList.remove('is-collapsed');
                }
                closeSidebar();
            });
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
            setSection(initialHash || 'overview');
            applyRoomsView('all');
            renderIcons();
        });
    </script>
</x-app-layout>
