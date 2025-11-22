<x-app-layout>
    <section class="panel create-hero">
        <div class="panel-header">
            <div class="panel-title">
                <i data-lucide="plus-circle"></i>
                <span>Create a room</span>
            </div>
            <a class="btn btn-sm btn-ghost" href="{{ route('dashboard') }}">
                <i data-lucide="layout-dashboard"></i>
                <span>Back to dashboard</span>
            </a>
        </div>
        <div class="create-hero-body">
            <p class="panel-subtitle">Keep it simple: a title, an optional description, and whether guests can read without logging in.</p>
            <div class="create-hero-actions">
                <a class="btn btn-primary" href="#roomForm">Fill the form</a>
                <div class="create-hero-tags">
                    <span>1 minute setup</span>
                    <span>Mobile friendly</span>
                    <span>Public link ready</span>
                </div>
            </div>
        </div>
    </section>

    <section class="create-layout">
        <div class="panel create-form-card" id="roomForm">
            <div class="panel-header">
                <div class="panel-title">
                    <i data-lucide="wand-2"></i>
                    <span>Room details</span>
                </div>
                <span class="panel-subtitle">Use the same polish as your rooms</span>
            </div>

            <form method="POST" action="{{ route('rooms.store') }}" class="create-form-body">
                @csrf

                @if ($errors->any())
                    <div class="form-alert">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="form-grid">
                    <label class="input-field">
                        <span class="input-label">Room title</span>
                        <input
                            type="text"
                            name="title"
                            class="field-control"
                            value="{{ old('title') }}"
                            placeholder="Example: Databases Q&A, Week 6"
                            required
                            autofocus
                        >
                        <span class="input-hint">This becomes the header on the live room and the dashboard card.</span>
                    </label>

                    <label class="input-field">
                        <span class="input-label">Description</span>
                        <textarea
                            name="description"
                            rows="3"
                            class="field-control"
                            placeholder="Add a short agenda or extra instructions (optional)">{{ old('description') }}</textarea>
                        <span class="input-hint">Shown below the title for both guests and hosts.</span>
                    </label>
                </div>

                <div class="input-field">
                    <div class="input-label">Access</div>
                    <label class="switch create-switch">
                        <input type="checkbox" name="is_public_read" value="1" {{ old('is_public_read', true) ? 'checked' : '' }}>
                        <span class="switch-track">
                            <span class="switch-thumb"></span>
                        </span>
                        <span class="switch-label">Allow guests to read without signing in</span>
                    </label>
                    <p class="input-hint">They will still need the room link to participate.</p>
                </div>

                <div class="form-footer">
                    <div class="form-footnote">
                        <i data-lucide="sparkles"></i>
                        <span>Your room appears instantly in the dashboard list.</span>
                    </div>
                    <button type="submit" class="btn btn-primary">Create room</button>
                </div>
            </form>
        </div>

        <aside class="panel create-preview">
            <div class="panel-header">
                <div class="panel-title">
                    <i data-lucide="monitor"></i>
                    <span>Live preview</span>
                </div>
                <span class="panel-subtitle">Matches dashboard & room cards</span>
            </div>
            <div class="create-preview-body">
                <article class="room-card preview-room-card" aria-hidden="true">
                    <div class="room-card-header">
                        <div class="room-card-title">
                            <span>Room title</span>
                            <span class="status-pill status-active">Active</span>
                        </div>
                        <button class="icon-btn" type="button">
                            <i data-lucide="copy"></i>
                        </button>
                    </div>
                    <div class="room-card-meta">
                        <span class="room-code">Code: ROOM-2025</span>
                        <span class="dot-separator">&bull;</span>
                        <span class="message-meta">Updated just now</span>
                    </div>
                    <p class="room-card-desc">Description shows here for both the dashboard card and the room header.</p>
                    <div class="room-card-stats">
                        <div class="room-stat">
                            <div class="room-stat-icon accent">
                                <i data-lucide="message-square"></i>
                            </div>
                            <div>
                                <div class="room-stat-label">Messages</div>
                                <div class="room-stat-value">0</div>
                            </div>
                        </div>
                        <div class="room-stat">
                            <div class="room-stat-icon">
                                <i data-lucide="help-circle"></i>
                            </div>
                            <div>
                                <div class="room-stat-label">Questions</div>
                                <div class="room-stat-value">0</div>
                            </div>
                        </div>
                        <div class="room-stat">
                            <div class="room-stat-icon neutral">
                                <i data-lucide="shield-check"></i>
                            </div>
                            <div>
                                <div class="room-stat-label">Public read</div>
                                <div class="room-stat-value">Enabled</div>
                            </div>
                        </div>
                    </div>
                    <div class="room-card-actions">
                        <span class="btn btn-sm btn-ghost">
                            <i data-lucide="external-link"></i>
                            <span>Public view</span>
                        </span>
                        <span class="btn btn-sm btn-primary">
                            <i data-lucide="messages-square"></i>
                            <span>Open live room</span>
                        </span>
                    </div>
                </article>

                <ul class="create-tips">
                    <li class="create-tip">
                        <i data-lucide="sparkles"></i>
                        <span>Form fields mirror what appears in the live room header.</span>
                    </li>
                    <li class="create-tip">
                        <i data-lucide="badge-check"></i>
                        <span>Use the access toggle to align with the public read badge.</span>
                    </li>
                    <li class="create-tip">
                        <i data-lucide="phone-call"></i>
                        <span>Everything stays mobile friendly, like the live chat layout.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </section>
</x-app-layout>
