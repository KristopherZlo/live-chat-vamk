@push('styles')
    @vite('resources/css/admin.css')
@endpush

<x-app-layout page-class="page-admin admin-shell">
    @php
        $status = session('status');
    @endphp

    @if ($status)
        <div class="flash flash-success" data-flash>
            <span>{{ $status }}</span>
            <button class="icon-btn flash-close" type="button" data-flash-close aria-label="Close">
                <i data-lucide="x"></i>
            </button>
        </div>
    @endif

    <section class="admin-card">
        <div class="admin-card__header">
            <div class="admin-card__title">
                <i data-lucide="shield-check"></i>
                <span>Admin panel</span>
            </div>
            <div class="admin-card__subtitle">Visible only to developers (is_dev)</div>
        </div>
        <div class="admin-card__body">
            <div class="admin-grid">
                <div class="admin-pill">Users: {{ $stats['users'] }}</div>
                <div class="admin-pill">Active users: {{ $stats['active_users'] }}</div>
                <div class="admin-pill">Rooms: {{ $stats['rooms'] }}</div>
                <div class="admin-pill">Messages: {{ $stats['messages'] }}</div>
                <div class="admin-pill">Questions: {{ $stats['questions'] }}</div>
                <div class="admin-pill">Participants: {{ $stats['participants'] }}</div>
            </div>
        </div>
    </section>

    <section class="admin-card">
        <div class="admin-card__header">
            <div class="admin-card__title">
                <i data-lucide="key-round"></i>
                <span>Invite codes</span>
            </div>
            <div class="admin-actions">
                <form method="POST" action="{{ route('admin.invites.store') }}">
                    @csrf
                    <button class="admin-btn admin-btn--primary" type="submit">Generate random</button>
                </form>
                <form class="admin-actions" method="POST" action="{{ route('admin.invites.store') }}">
                    @csrf
                    <input type="text" name="code" class="input" placeholder="Custom code">
                    <button class="admin-btn" type="submit">Save</button>
                </form>
            </div>
        </div>
        <div class="admin-card__body">
            <div class="table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($inviteCodes as $invite)
                            <tr>
                                <td class="admin-actions">
                                    <code>{{ $invite->code }}</code>
                                    <button class="admin-copy-btn" type="button" data-copy="{{ $invite->code }}" title="Copy code">
                                        <i data-lucide="copy"></i>
                                    </button>
                                </td>
                                <td>
                                    @if($invite->used_at)
                                        Used by {{ $invite->usedBy?->name ?? 'unknown' }} at {{ $invite->used_at->format('Y-m-d H:i') }}
                                    @else
                                        <span class="text-ok">Unused</span>
                                    @endif
                                </td>
                                <td>{{ $invite->created_at?->format('Y-m-d H:i') }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.invites.destroy', $invite) }}" onsubmit="return confirm('Delete this invite code?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="admin-btn" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">No invite codes yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="admin-card__subtitle" style="margin-top: 0.75rem;">Recently used</div>
            <div class="table-wrapper">
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
                                <td><code>{{ $invite->code }}</code></td>
                                <td>{{ $invite->usedBy?->name ?? 'unknown' }}</td>
                                <td>{{ $invite->used_at?->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">No usage yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="admin-card">
        <div class="admin-card__header">
            <div class="admin-card__title">
                <i data-lucide="list"></i>
                <span>Rooms</span>
            </div>
            <div class="admin-card__subtitle">All rooms</div>
        </div>
        <div class="admin-card__body">
            <div class="admin-card__subtitle">Top by messages</div>
            <div class="table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Owner</th>
                            <th>Messages</th>
                            <th>Questions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topRooms as $room)
                            <tr>
                                <td>{{ $room->title }}</td>
                                <td>{{ $room->owner?->name ?? '—' }}</td>
                                <td>{{ $room->messages_count }}</td>
                                <td>{{ $room->questions_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4">No rooms yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="admin-card__subtitle" style="margin-top: 0.85rem;">All rooms</div>
            <div class="table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Slug</th>
                            <th>Owner</th>
                            <th>Messages</th>
                            <th>Questions</th>
                            <th>Bans</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rooms as $room)
                            <tr>
                                <td>{{ $room->title }}</td>
                                <td class="admin-actions">
                                    <code>{{ $room->slug }}</code>
                                    <button class="admin-copy-btn" type="button" data-copy="{{ $room->slug }}" title="Copy slug">
                                        <i data-lucide="copy"></i>
                                    </button>
                                </td>
                                <td>{{ $room->owner?->name ?? '—' }}</td>
                                <td>{{ $room->messages_count }}</td>
                                <td>{{ $room->questions_count }}</td>
                                <td>{{ $room->bans_count }}</td>
                                <td>{{ $room->updated_at?->format('Y-m-d H:i') }}</td>
                                <td class="admin-actions">
                                    <a class="admin-btn" href="{{ route('rooms.public', $room->slug) }}" target="_blank">Open room</a>
                                    <a class="admin-btn" href="{{ route('rooms.questionsPanel', $room) }}" target="_blank">Questions</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">No rooms found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="admin-pagination">{{ $rooms->links() }}</div>
        </div>
    </section>

    <section class="admin-card">
        <div class="admin-card__header">
            <div class="admin-card__title">
                <i data-lucide="ban"></i>
                <span>Ban controls</span>
            </div>
            <div class="admin-card__subtitle">Create a ban entry or review recent bans</div>
        </div>
        <div class="admin-card__body">
            <form class="admin-grid" method="POST" action="{{ route('admin.bans.store') }}" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); align-items: end;">
                @csrf
                <label class="form-field">
                    <span class="form-label">Room</span>
                    <select name="room_id" required>
                        <option value="" disabled selected>Select room</option>
                        @foreach($allRooms as $room)
                            <option value="{{ $room->id }}">{{ $room->title }} ({{ $room->slug }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-field">
                    <span class="form-label">Participant ID (optional)</span>
                    <input type="number" name="participant_id" placeholder="participant id">
                </label>
                <label class="form-field">
                    <span class="form-label">Session token</span>
                    <input type="text" name="session_token" placeholder="session token">
                </label>
                <label class="form-field">
                    <span class="form-label">Display name</span>
                    <input type="text" name="display_name" placeholder="Name to record">
                </label>
                <label class="form-field">
                    <span class="form-label">IP address</span>
                    <input type="text" name="ip_address" placeholder="optional">
                </label>
                <label class="form-field">
                    <span class="form-label">Fingerprint</span>
                    <input type="text" name="fingerprint" placeholder="optional">
                </label>
                <button class="admin-btn admin-btn--primary" type="submit">Add ban</button>
            </form>

            <div class="table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>Participant</th>
                            <th>Session</th>
                            <th>IP</th>
                            <th>Fingerprint</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentBans as $ban)
                            <tr>
                                <td>{{ $ban->room?->title ?? '—' }}</td>
                                <td>{{ $ban->participant?->display_name ?? $ban->display_name ?? '—' }}</td>
                                <td><code>{{ \Illuminate\Support\Str::limit($ban->session_token, 16) }}</code></td>
                                <td>{{ $ban->ip_address ?? '—' }}</td>
                                <td>{{ $ban->fingerprint ? \Illuminate\Support\Str::limit($ban->fingerprint, 12) : '—' }}</td>
                                <td>{{ $ban->created_at?->format('Y-m-d H:i') }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.bans.destroy', $ban) }}" onsubmit="return confirm('Remove this ban?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-ghost" type="submit">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No bans yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="admin-card">
        <div class="admin-card__header">
            <div class="admin-card__title">
                <i data-lucide="users"></i>
                <span>Recent users</span>
            </div>
            <div class="admin-card__subtitle">Last registered users</div>
        </div>
        <div class="admin-card__body">
            <div class="table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th>Ban</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentUsers as $user)
                            <tr>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->is_dev ? 'Dev' : 'User' }}</td>
                                <td>{{ $user->created_at?->format('Y-m-d H:i') }}</td>
                                <td>
                                    <form class="admin-actions" method="POST" action="{{ route('admin.bans.store') }}">
                                        @csrf
                                        <input type="hidden" name="display_name" value="{{ $user->name }}">
                                        <select name="room_id" required>
                                            <option value="" disabled selected>Room</option>
                                            @foreach($allRooms as $room)
                                                <option value="{{ $room->id }}">{{ $room->title }}</option>
                                            @endforeach
                                        </select>
                                        <button class="admin-btn" type="submit">Ban</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4">No users.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="admin-pagination">{{ $recentUsers->links() }}</div>
            </div>
        </div>
    </section>

    <section class="admin-card">
        <div class="admin-card__header">
            <div class="admin-card__title">
                <i data-lucide="fingerprint"></i>
                <span>Participants & fingerprints</span>
            </div>
            <div class="admin-card__subtitle">Recent participants with fingerprints/IP for quick bans</div>
        </div>
        <div class="admin-card__body">
            <div class="table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Room</th>
                            <th>Fingerprint</th>
                            <th>IP</th>
                            <th>Session</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($participants as $p)
                            <tr>
                                <td>{{ $p->display_name ?? 'Guest' }}</td>
                                <td>{{ $p->room?->title ?? '—' }}</td>
                                <td>
                                    <code>{{ \Illuminate\Support\Str::limit($p->fingerprint, 24) }}</code>
                                    @if($p->fingerprint)
                                        <button class="admin-copy-btn" type="button" data-copy="{{ $p->fingerprint }}" title="Copy fingerprint">
                                            <i data-lucide="copy"></i>
                                        </button>
                                    @endif
                                </td>
                                <td>{{ $p->ip_address ?? '—' }}</td>
                                <td><code>{{ \Illuminate\Support\Str::limit($p->session_token, 24) }}</code></td>
                                <td>
                                    <form class="admin-actions" method="POST" action="{{ route('admin.bans.store') }}">
                                        @csrf
                                        <input type="hidden" name="participant_id" value="{{ $p->id }}">
                                        <input type="hidden" name="session_token" value="{{ $p->session_token }}">
                                        <input type="hidden" name="fingerprint" value="{{ $p->fingerprint }}">
                                        <input type="hidden" name="ip_address" value="{{ $p->ip_address }}">
                                        <input type="hidden" name="display_name" value="{{ $p->display_name }}">
                                        <select name="room_id" required>
                                            <option value="" disabled selected>Room</option>
                                            @foreach($allRooms as $room)
                                                <option value="{{ $room->id }}" @selected($room->id === $p->room_id)>{{ $room->title }}</option>
                                            @endforeach
                                        </select>
                                        <button class="admin-btn" type="submit">Ban</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No participants.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="admin-pagination">{{ $participants->links() }}</div>
            </div>
        </div>
    </section>

    <script>
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
    </script>
</x-app-layout>
