<x-app-layout>
    @php
        $publicLink = route('rooms.public', $room->slug);
        $isClosed = $room->status !== 'active';
        $queueSoundUrl = asset('audio/new-question-sound.mp3');
    @endphp
    @php
        $avatarPalette = ['#2563eb', '#0ea5e9', '#6366f1', '#8b5cf6', '#14b8a6', '#f97316', '#f59e0b', '#10b981', '#ef4444'];
        $avatarColor = function (string $name = 'Guest') use ($avatarPalette) {
            $hash = crc32($name);
            $index = abs((int) $hash) % count($avatarPalette);
            return $avatarPalette[$index];
        };
    @endphp
    @php
        $popularReactions = ['❤️', '👍', '👎', '🔥', '🙏', '😁', '😭', '🤔'];
        $currentUserId = auth()->id();
        $currentParticipantId = $participant?->id;
    @endphp

    @if($isOwner)
        @push('room-header-actions')
            <button class="btn btn-sm btn-ghost" type="button" data-copy="{{ $publicLink }}">Copy link</button>
            <button class="btn btn-sm btn-ghost" type="button" id="qrButton">
                <i data-lucide="qr-code"></i>
                <span>Show QR-code</span>
            </button>
        @endpush
    @endif

    <div
        class="{{ $isOwner ? 'role-teacher' : 'role-student' }} room-page"
        data-room-role="{{ $isOwner ? 'owner' : 'participant' }}"
        data-room-id="{{ $room->id }}"
    >
        <div class="panel room-header">
            @php
                $hasLongDescription = $room->description && \Illuminate\Support\Str::length($room->description) > 255;
                $roomDescription = $room->description ?: 'Add a description';
            @endphp
            <div class="panel-header room-header-bar">
                <div class="room-header-main">
                    <div class="panel-title">
                        <i data-lucide="messages-square"></i>
                        <div class="inline-editable inline-edit-inline" data-inline-edit>
                            <div class="inline-edit-display room-name">{{ $room->title }}</div>
                            @if($isOwner)
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
                            @endif
                        </div>
                    </div>

                    <div class="inline-editable room-description-block" data-inline-edit>
                        <div class="room-description-row">
                            <div
                                class="inline-edit-display panel-subtitle room-description {{ $hasLongDescription ? 'is-collapsible is-collapsed' : '' }}"
                                @if($hasLongDescription)
                                    data-room-description
                                    data-collapsed="true"
                                    tabindex="0"
                                    role="button"
                                    aria-expanded="false"
                                    aria-label="Toggle room description"
                                @endif
                            >
                                {{ $roomDescription }}
                            </div>
                            @if($isOwner)
                                <div class="room-description-actions">
                                    <button class="icon-btn inline-edit-trigger" type="button" aria-label="Edit description" data-inline-trigger>
                                        <i data-lucide="pencil"></i>
                                    </button>
                                </div>
                            @endif
                        </div>
                        @if($isOwner)
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
                        @endif
                    </div>
                </div>
                <div class="room-header-aside">
                    <span class="room-code">Room code: {{ $room->slug }}</span>
                    <span class="status-pill status-{{ $room->status }} room-status">{{ ucfirst($room->status) }}</span>
                </div>
            </div>
        </div>

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

    <nav class="mobile-tabs" id="mobileTabs" aria-label="Sections">
      <button class="mobile-tab-btn active" data-tab-target="chat">Chat</button>
      @if($isOwner)
        <button class="mobile-tab-btn" data-tab-target="queue">Queue</button>
        <button class="mobile-tab-btn" data-tab-target="history">History</button>
      @else
        <button class="mobile-tab-btn" data-tab-target="questions">My questions</button>
      @endif
      <button class="mobile-tab-btn mobile-tab-more" type="button" id="mobileMenuTabsBtn">
        <i data-lucide="more-horizontal"></i>
        <span>More</span>
      </button>
    </nav>

        <div id="layoutRoot" class="layout {{ $isOwner ? 'teacher history-hidden' : '' }}">
            <section class="panel chat-panel mobile-panel mobile-active" data-mobile-panel="chat">
                <div class="panel-header">
                    <div class="panel-title">
                        <i data-lucide="message-circle"></i>
                        <span>Ghost Room chat</span>
                    </div>
                    <div class="panel-subtitle">Ask and discuss during the lecture.</div>
                </div>

                @if($isBanned)
                    <div class="flash flash-danger" data-flash>
                        <span>You were banned by the host. Chat is read-only.</span>
                        <button class="icon-btn flash-close" type="button" data-flash-close aria-label="Close">
                            <i data-lucide="x"></i>
                        </button>
                    </div>
                @endif

                @if($isOwner)
                    <div class="chat-subtabs" data-chat-tabs>
                        <button class="chat-tab-btn active" type="button" data-chat-tab="chat">
                            <span>Chat</span>
                        </button>
                        <button class="chat-tab-btn" type="button" data-chat-tab="bans" data-onboarding-target="bans-tab">
                            <span>Bans</span>
                            <span class="pill-soft">{{ $bannedParticipants->count() }}</span>
                        </button>
                    </div>
                @endif

                <div class="chat-pane" data-chat-panel="chat" data-onboarding-target="chat-pane">
                    <ol class="chat-messages messages-container" id="chatMessages">
                        @forelse($messages as $message)
                            @php
                                $isOwnerMessage = $message->user && $message->user_id === $room->user_id;
                                $authorName = $message->user?->name ?? $message->participant?->display_name ?? 'Guest';
                                $initials = \Illuminate\Support\Str::of($authorName)->substr(0, 2)->upper();
                                $isOutgoing = $isOwner ? $isOwnerMessage : ($participant && $message->participant && $message->participant->id === $participant->id);
                                $isQuestionMessage = (bool) $message->question;
                                $replyTo = $message->replyTo;
                                $avatarBg = $avatarColor($authorName);
                                $reactionsGrouped = $message->reactions->groupBy('emoji')->map(function ($items, $emoji) use ($currentUserId, $currentParticipantId) {
                                    return [
                                        'emoji' => $emoji,
                                        'count' => $items->count(),
                                        'reacted' => $items->contains(function ($reaction) use ($currentUserId, $currentParticipantId) {
                                            return ($currentUserId && $reaction->user_id === $currentUserId)
                                                || ($currentParticipantId && $reaction->participant_id === $currentParticipantId);
                                        }),
                                    ];
                                })->values();
                                $myReactions = $reactionsGrouped->where('reacted', true)->pluck('emoji')->values();
                            @endphp
                            <li
                                class="message {{ $isOutgoing ? 'message--outgoing' : '' }} {{ $isQuestionMessage ? 'message--question' : '' }}"
                                data-message-id="{{ $message->id }}"
                                data-reactions-url="{{ route('rooms.messages.reactions.toggle', [$room, $message]) }}"
                                data-reactions='@json($reactionsGrouped)'
                                data-my-reactions='@json($myReactions)'
                            >
                                <div class="message-avatar colorized" style="background: {{ $avatarBg }}; color: #fff; border-color: transparent;">{{ $initials }}</div>
                                <div class="message-body">
                                    @if($isOwner && $message->participant && !$isOwnerMessage)
                                        <form
                                            method="POST"
                                            action="{{ route('rooms.bans.store', $room) }}"
                                            class="message-ban-form"
                                            data-ban-confirm="1"
                                        >
                                            @csrf
                                            <input type="hidden" name="participant_id" value="{{ $message->participant->id }}">
                                            <button type="submit" class="message-ban-btn" title="Ban participant">
                                                <i data-lucide="gavel"></i>
                                            </button>
                                        </form>
                                    @endif
                                    <div class="message-header">
                                        <span class="message-author">
                                            {{ $authorName }}
                                            @if($message->user?->is_dev)
                                                <span class="message-badge message-badge-dev">dev</span>
                                            @endif
                                        </span>
                                        <div class="message-meta">
                                            <span>{{ $message->created_at->format('H:i') }}</span>
                                            @if($isOwnerMessage)
                                                <span class="message-badge message-badge-teacher">Host</span>
                                            @endif
                                            @if($isQuestionMessage)
                                                <span class="message-badge message-badge-question">To host</span>
                                            @endif
                                            @if($replyTo)
                                                <span class="message-badge">Reply</span>
                                            @endif
                                        </div>
                                    </div>
                                    @if($replyTo)
                                        @php
                                            $replyAuthor = $replyTo->user?->name ?? $replyTo->participant?->display_name ?? 'Guest';
                                        @endphp
                                        <div class="message-reply">
                                            <span class="reply-author">{{ $replyAuthor }}</span>
                                            <span class="reply-text">{{ \Illuminate\Support\Str::limit($replyTo->content, 120) }}</span>
                                        </div>
                                    @endif
                                    <div class="message-text">{{ $message->content }}</div>
                                    <div class="message-reactions" data-message-reactions>
                                        <div class="reactions-list" data-reactions-list>
                                            @foreach($reactionsGrouped as $reaction)
                                                <button
                                                    type="button"
                                                    class="reaction-chip {{ $reaction['reacted'] ? 'is-active' : '' }}"
                                                    data-reaction-chip
                                                    data-emoji="{{ $reaction['emoji'] }}"
                                                >
                                                    <span class="emoji">{{ $reaction['emoji'] }}</span>
                                                    <span class="count">{{ $reaction['count'] }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="message-actions">
                                        <button
                                            type="button"
                                            class="msg-action"
                                            data-reply-id="{{ $message->id }}"
                                            data-reply-author="{{ e($authorName) }}"
                                            data-reply-text="{{ e(\Illuminate\Support\Str::limit($message->content, 500)) }}"
                                        >
                                            <i data-lucide="corner-up-right"></i>
                                            <span>Reply</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="msg-action msg-action-react"
                                            data-reaction-trigger
                                        >
                                            <i data-lucide="smile-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </li>
                        @empty
                            <li class="message message-empty" data-empty-message>
                                <div class="message-body">
                                    <div class="message-text">No messages yet.</div>
                                </div>
                            </li>
                        @endforelse
                    </ol>
                    <div class="reaction-menu" id="reactionMenu" data-reaction-menu hidden>
                        <div class="reaction-menu-title">Quick reactions</div>
                        <div class="reaction-menu-current" data-reaction-current hidden>
                            <span class="label">Your reaction:</span>
                            <span data-reaction-current-emoji></span>
                        </div>
                        <div class="reaction-menu-grid" data-reaction-grid></div>
                        <div class="reaction-menu-more-panel" data-reaction-more-panel hidden></div>
                    </div>

                    @if($isBanned)
                        <div class="panel-footer ban-notice">
                            You were banned by the host. You can still read messages but cannot post.
                        </div>
                    @elseif(!$isClosed)
                        <div class="chat-input">
                            <form id="chat-form" method="POST" action="{{ route('rooms.messages.store', $room) }}">
                                @csrf
                                <div class="chat-send-options">
                                    @unless($isOwner)
                                        <label class="switch" id="sendToTeacherSwitch">
                                            <input type="checkbox" name="as_question" value="1" id="sendToTeacher">
                                            <span class="switch-track">
                                              <span class="switch-thumb"></span>
                                            </span>
                                            <span class="switch-label">Send to host</span>
                                        </label>
                                    @endunless
                                    <span class="panel-subtitle">Press Enter to send, Shift+Enter for a new line</span>
                                </div>
                                <div class="reply-preview" id="replyPreview" hidden>
                                    <div class="reply-preview-label">
                                        <i data-lucide="corner-up-left"></i>
                                        <span>Replying to</span>
                                        <span class="reply-preview-author" id="replyPreviewAuthor"></span>
                                    </div>
                                    <div class="reply-preview-text" id="replyPreviewText"></div>
                                    <button type="button" class="icon-btn" id="replyPreviewCancel" title="Cancel reply">
                                        <i data-lucide="x"></i>
                                    </button>
                                </div>
                                <div class="chat-composer" data-chat-composer>
                                    <button type="button" class="composer-btn composer-emoji" id="chatEmojiToggle" title="Add emoji">
                                        <i data-lucide="smile"></i>
                                    </button>
                                    <textarea
                                        name="content"
                                        id="chatInput"
                                        class="chat-textarea"
                                        placeholder="Type your message..."
                                        rows="1"
                                        data-onboarding-target="chat-input"
                                    ></textarea>
                                    <input type="hidden" name="reply_to_id" id="replyToId" value="">
                                    <button type="submit" class="composer-btn composer-send" id="sendButton" title="Send message">
                                        <i data-lucide="send"></i>
                                    </button>
                                </div>
                                <div class="emoji-picker-panel" id="chatEmojiPanel" hidden>
                                    <emoji-picker id="chatEmojiPicker" class="emoji-picker-element light"></emoji-picker>
                                </div>
                            </form>
                        </div>
                    @else
                        <div class="panel-footer">
                            This room is closed. Messages are read-only.
                        </div>
                    @endif
                </div>

                @if($isOwner)
                    <div class="chat-pane chat-pane-bans" data-chat-panel="bans" hidden>
                        <div class="moderation-block">
                            <div class="moderation-head">
                                <div>
                                    <div class="moderation-title">Banned participants</div>
                                    <div class="panel-subtitle">Banned users cannot post messages or questions.</div>
                                </div>
                                <span class="pill-soft">{{ $bannedParticipants->count() }}</span>
                            </div>
                            @if($bannedParticipants->isEmpty())
                                <div class="empty-state">No banned participants yet.</div>
                            @else
                                <ul class="ban-list">
                                    @foreach($bannedParticipants as $ban)
                                        <li class="ban-item">
                                            <div>
                                                <div class="ban-name">{{ $ban->display_name ?? $ban->participant?->display_name ?? 'Guest' }}</div>
                                                <div class="ban-meta">Banned {{ $ban->created_at->diffForHumans(null, true) }} ago</div>
                                            </div>
                                            <form method="POST" action="{{ route('rooms.bans.destroy', [$room, $ban->id]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-ghost">Unban</button>
                                            </form>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                @endif
            </section>

            @if($isOwner)
                <div id="questions-panel" class="teacher-panels">
                    @include('rooms.partials.questions_panel')
                </div>
            @else
                <section class="panel student-panel mobile-panel" data-mobile-panel="questions" id="myQuestionsPanel">
                    @include('rooms.partials.my_questions_panel', ['room' => $room, 'myQuestions' => $myQuestions])
                </section>
            @endif
        </div>
    </div>

    <div class="qr-overlay" id="qrOverlay" aria-hidden="true">
        <div class="qr-card" role="dialog" aria-modal="true" aria-labelledby="qrTitle">
            <div class="qr-header">
                <div>
                    <div class="qr-title" id="qrTitle">Join this room</div>
                    <div class="panel-subtitle">Scan or copy the public link</div>
                </div>
                <button class="icon-btn" type="button" id="qrClose">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="qr-body">
                <div class="qr-box">
                    <canvas id="qrCanvas" role="img" aria-label="QR code"></canvas>
                    <div class="qr-logo">
                        <img src="{{ asset('icons/logo_black.svg') }}" class="qr-logo-img" alt="Ghost Room logo">
                    </div>
                </div>
                <div class="qr-info">
                    <div class="panel-subtitle">Public link</div>
                    <a href="{{ $publicLink }}" class="qr-link" target="_blank" rel="noreferrer">{{ $publicLink }}</a>
                    <div class="qr-footer">
                        <button class="btn btn-sm btn-ghost" type="button" data-copy="{{ $publicLink }}">Copy link</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('styles')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/emoji-picker-element@1.27.0/themes/light.css">
    @endpush

    @push('scripts')
        <script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@1.27.0/index.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (window.__chatPageBound) return;
                window.__chatPageBound = true;
                const roomId = {{ $room->id }};
                const isOwnerUser = @json($isOwner);
                const currentUserId = @json(auth()->id());
                const currentParticipantId = @json($participant?->id);
                const currentUserName = @json(auth()->user()?->name ?? $participant?->display_name ?? 'Guest');
                const currentParticipantName = @json($participant?->display_name ?? 'Guest');
                const publicLink = @json($publicLink);
                const queueSoundUrl = @json($queueSoundUrl);
                window.queueSoundUrl = queueSoundUrl;
                const questionsPanel = document.getElementById('questions-panel');
                const questionsPanelUrl = @json(route('rooms.questionsPanel', $room));
                const myQuestionsPanel = document.getElementById('myQuestionsPanel');
                const myQuestionsPanelUrl = @json(route('rooms.myQuestionsPanel', $room));
                const banStoreUrl = @json(route('rooms.bans.store', $room));
                const rootWindow = window;
                const queuePipButton = document.querySelector('[data-queue-pip]');
                const supportsDocumentPip = Boolean(window.documentPictureInPicture && window.documentPictureInPicture.requestWindow);
                let queuePipWindow = null;
                let queuePipSyncTimer = null;
                let queuePipStylesCloned = false;
                const normalizeId = (value) => {
                    const num = Number(value);
                    return Number.isFinite(num) ? num : null;
                };
                const markMainQueueItemSeen = (questionId) => {
                    const id = normalizeId(questionId);
                    if (!id) return;
                    if (typeof rootWindow.markQueueItemSeen === 'function') {
                        rootWindow.markQueueItemSeen(id, rootWindow.document);
                        return;
                    }
                    const mainItem = rootWindow.document.querySelector(`#queuePanel .queue-item[data-question-id=\"${id}\"]`);
                    if (mainItem) {
                        mainItem.classList.remove('queue-item-new');
                    }
                    if (typeof rootWindow.setupQueueNewHandlers === 'function') {
                        rootWindow.setupQueueNewHandlers();
                    } else if (typeof rootWindow.markQueueHasNew === 'function') {
                        rootWindow.markQueueHasNew();
                    }
                };
                let queueNeedsNew = false;
                let questionsPollTimer = null;
                let myQuestionsPollTimer = null;
                  const qrButton = document.getElementById('qrButton');
                  const qrOverlay = document.getElementById('qrOverlay');
                  const qrClose = document.getElementById('qrClose');
                  const qrCanvas = document.getElementById('qrCanvas');
                const chatContainer = document.querySelector('.messages-container');
                const scrollChatToBottom = () => {
                    if (!chatContainer) return;
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                };
                const chatInputWrapper = document.querySelector('[data-chat-panel=\"chat\"] .chat-input');
                const chatInput = document.getElementById('chatInput');
                const sendButton = document.getElementById('sendButton');
                const chatEmojiToggle = document.getElementById('chatEmojiToggle');
                const chatEmojiPanel = document.getElementById('chatEmojiPanel');
                const chatEmojiPicker = document.getElementById('chatEmojiPicker');
                const reactionMenu = document.getElementById('reactionMenu');
                const reactionMenuGrid = reactionMenu?.querySelector('[data-reaction-grid]');
                const reactionMenuMorePanel = reactionMenu?.querySelector('[data-reaction-more-panel]');
                const reactionMenuCurrent = reactionMenu?.querySelector('[data-reaction-current]');
                const reactionMenuCurrentEmoji = reactionMenu?.querySelector('[data-reaction-current-emoji]');
                let reactionEmojiPicker = null;
                const isMobileViewport = () => window.matchMedia('(max-width: 640px)').matches;
                const syncEmojiPickerTheme = () => {
                    const isDark = document.body?.dataset?.theme === 'dark';
                    if (chatEmojiPicker) {
                        chatEmojiPicker.classList.remove('dark', 'light');
                        chatEmojiPicker.classList.add(isDark ? 'dark' : 'light');
                    }
                    if (reactionEmojiPicker) {
                        reactionEmojiPicker.classList.remove('dark', 'light');
                        reactionEmojiPicker.classList.add(isDark ? 'dark' : 'light');
                    }
                };
                const updateSendButtonState = () => {
                    if (!sendButton) return;
                    const hasContent = (chatInput?.value || '').trim().length > 0;
                    const isSending = sendButton.classList.contains('sending');
                    const shouldDisable = isSending || !hasContent;
                    sendButton.disabled = shouldDisable;
                    sendButton.classList.toggle('can-send', hasContent && !isSending);
                    sendButton.setAttribute('aria-disabled', shouldDisable ? 'true' : 'false');
                };
                const chatTabButtons = document.querySelectorAll('[data-chat-tab]');
                const chatPanes = document.querySelectorAll('[data-chat-panel]');
                const csrfMeta = document.querySelector('meta[name=\"csrf-token\"]');
                const csrfToken = csrfMeta?.getAttribute('content') || '';
                const replyToInput = document.getElementById('replyToId');
                const replyPreview = document.getElementById('replyPreview');
                const replyPreviewAuthor = document.getElementById('replyPreviewAuthor');
                const replyPreviewText = document.getElementById('replyPreviewText');
                const replyPreviewCancel = document.getElementById('replyPreviewCancel');
                const avatarPalette = ['#2563eb', '#0ea5e9', '#6366f1', '#8b5cf6', '#14b8a6', '#f97316', '#f59e0b', '#10b981', '#ef4444'];
                const reactionUrlTemplate = @json(route('rooms.messages.reactions.toggle', [$room, '__MESSAGE__']));
                const popularReactions = @json($popularReactions);
                let activeReactionMessage = null;
                let activeReactionTrigger = null;
                let emojiPickerMode = 'input';
                let reactionPickerTarget = null;
                syncEmojiPickerTheme();
                if (!window.__themeObserverBound) {
                    const themeObserver = new MutationObserver(() => syncEmojiPickerTheme());
                    if (document.body) {
                        themeObserver.observe(document.body, { attributes: true, attributeFilter: ['data-theme'] });
                    }
                    window.__themeObserverBound = true;
                }
                const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;',
                }[char] ?? char));
                const removeEmptyMessageState = () => {
                    if (!chatContainer) return;
                    const empty = chatContainer.querySelector('.message-empty');
                    if (empty) {
                        empty.remove();
                    }
                };
                const parseJsonSafe = (raw, fallback = []) => {
                    if (!raw) return fallback;
                    try {
                        const parsed = JSON.parse(raw);
                        return parsed ?? fallback;
                    } catch (e) {
                        return fallback;
                    }
                };
                const getReactionUrl = (messageEl) => {
                    if (!messageEl) return '';
                    if (messageEl.dataset.reactionsUrl) {
                        return messageEl.dataset.reactionsUrl;
                    }
                    if (reactionUrlTemplate && messageEl.dataset.messageId) {
                        return reactionUrlTemplate.replace('__MESSAGE__', messageEl.dataset.messageId);
                    }
                    return '';
                };
                const renderReactions = (messageEl, reactions = [], myReactions = []) => {
                    if (!messageEl) return;
                    const list = messageEl.querySelector('[data-reactions-list]');
                    if (!list) return;
                    const mySet = new Set(myReactions || []);
                    const hasReactions = Array.isArray(reactions) && reactions.length > 0;
                    list.innerHTML = '';
                    messageEl.dataset.reactions = JSON.stringify(reactions || []);
                    messageEl.dataset.myReactions = JSON.stringify(Array.from(mySet));
                    messageEl.classList.toggle('has-reactions', hasReactions);
                    if (!hasReactions) return;
                    reactions.forEach((reaction) => {
                        if (!reaction || !reaction.emoji) return;
                        const chip = document.createElement('button');
                        chip.type = 'button';
                        chip.className = 'reaction-chip';
                        chip.dataset.reactionChip = '1';
                        chip.dataset.emoji = reaction.emoji;
                        if (mySet.has(reaction.emoji)) {
                            chip.classList.add('is-active');
                        }
                        chip.innerHTML = `
                            <span class="emoji">${escapeHtml(reaction.emoji)}</span>
                            <span class="count">${escapeHtml(String(reaction.count ?? 0))}</span>
                        `;
                        list.appendChild(chip);
                    });
                };
                const makeMessageKey = (content, authorUserId, authorParticipantId, replyId = null, asQuestion = false) => {
                    const normalized = String(content || '').trim();
                    return [
                        encodeURIComponent(normalized),
                        String(authorUserId ?? ''),
                        String(authorParticipantId ?? ''),
                        String(replyId ?? ''),
                        asQuestion ? 'q1' : 'q0',
                    ].join('|');
                };
                const buildMessageElement = (payload, options = {}) => {
                    const {
                        id,
                        content = '',
                        created_at: createdAt,
                        author = {},
                        as_question: asQuestion = false,
                        reply_to: replyTo = null,
                        reactions = [],
                        myReactions = [],
                    } = payload || {};
                    const {
                        pending = false,
                        allowBan = true,
                    } = options;
                    const container = document.createElement('li');
                    const messageId = id ?? `temp-${Date.now()}`;
                    const authorUserId = normalizeId(author?.user_id);
                    const authorParticipantId = normalizeId(author?.participant_id);
                    const messageKey = makeMessageKey(content, authorUserId, authorParticipantId, replyTo?.id, asQuestion);
                    container.classList.add('message');
                    container.dataset.messageId = messageId;
                    container.dataset.reactionsUrl = reactionUrlTemplate && messageId ? reactionUrlTemplate.replace('__MESSAGE__', messageId) : '';
                    container.dataset.reactions = JSON.stringify(reactions || []);
                    container.dataset.myReactions = JSON.stringify(myReactions || []);
                    if (pending) {
                        container.classList.add('message--pending');
                        container.dataset.tempId = messageId;
                        container.dataset.tempKey = messageKey;
                    }
                    const isOwnerAuthor = Boolean(author.is_owner);
                    const isOutgoing = (currentUserId && authorUserId && Number(currentUserId) === Number(authorUserId))
                        || (currentParticipantId && authorParticipantId && Number(currentParticipantId) === Number(authorParticipantId));
                    if (isOutgoing) container.classList.add('message--outgoing');
                    if (asQuestion) container.classList.add('message--question');
                    const authorNameRaw = author?.name || currentUserName || 'Guest';
                    const authorName = escapeHtml(authorNameRaw);
                    const avatarColor = avatarColorFromName(authorNameRaw);
                    const initials = escapeHtml((authorNameRaw || '??').slice(0, 2).toUpperCase());
                    const devBadge = author.is_dev ? '<span class="message-badge message-badge-dev">dev</span>' : '';
                    const replyHtml = replyTo ? `<div class="message-reply"><span class="reply-author">${escapeHtml(replyTo.author || 'Guest')}</span><span class="reply-text">${escapeHtml(replyTo.content || '')}</span></div>` : '';
                    const time = createdAt ? new Date(createdAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    const canBan = allowBan && isOwnerUser && !isOwnerAuthor && author.participant_id;
                    const banButtonHtml = canBan ? `
                        <form method="POST" action="${banStoreUrl}" class="message-ban-form" data-ban-confirm="1">
                            <input type="hidden" name="_token" value="${csrfToken}">
                            <input type="hidden" name="participant_id" value="${author.participant_id}">
                            <button type="submit" class="message-ban-btn" title="Ban participant">
                                <i data-lucide="gavel"></i>
                            </button>
                        </form>
                    ` : '';
                    container.innerHTML = `
                        <div class="message-avatar colorized" style="background:${avatarColor}; color:#fff; border-color:transparent;">${initials}</div>
                        <div class="message-body">
                            ${banButtonHtml}
                            <div class="message-header">
                                <span class="message-author">${authorName}${devBadge}</span>
                                <div class="message-meta">
                                    <span>${time}</span>
                                    ${isOwnerAuthor ? '<span class="message-badge message-badge-teacher">Host</span>' : ''}
                                    ${asQuestion ? '<span class="message-badge message-badge-question">To host</span>' : ''}
                                    ${replyHtml ? '<span class="message-badge">Reply</span>' : ''}
                                </div>
                            </div>
                            ${replyHtml}
                            <div class="message-text">${escapeHtml(content)}</div>
                            <div class="message-reactions" data-message-reactions>
                                <div class="reactions-list" data-reactions-list></div>
                            </div>
                            <div class="message-actions">
                                <button type="button" class="msg-action">
                                    <i data-lucide="corner-up-right"></i>
                                    <span>Reply</span>
                                </button>
                                <button type="button" class="msg-action msg-action-react" data-reaction-trigger>
                                    <i data-lucide="smile-plus"></i>
                                </button>
                            </div>
                        </div>`;
                        const replyBtn = container.querySelector('.msg-action');
                        if (replyBtn) {
                            replyBtn.dataset.replyId = messageId;
                            replyBtn.dataset.replyAuthor = authorNameRaw || 'Guest';
                            replyBtn.dataset.replyText = content || '';
                    }
                    renderReactions(container, reactions || [], myReactions || []);
                    return container;
                };
                const updateMessageElementFromPayload = (element, payload) => {
                    if (!element || !payload) return element;
                    const fresh = buildMessageElement(payload, { pending: false });
                    element.className = fresh.className;
                    element.innerHTML = fresh.innerHTML;
                    // Reset and copy dataset
                    Object.keys(element.dataset).forEach((key) => delete element.dataset[key]);
                    Object.entries(fresh.dataset || {}).forEach(([key, value]) => {
                        element.dataset[key] = value;
                    });
                    element.classList.remove('message--pending');
                    element.removeAttribute('data-temp-id');
                    element.removeAttribute('data-temp-key');
                    if (window.refreshLucideIcons) {
                        window.refreshLucideIcons();
                    }
                    return element;
                };
                const createMessageElement = (payload, options = {}) => buildMessageElement(payload, options);
                const getReactionsState = (messageEl) => ({
                    reactions: parseJsonSafe(messageEl?.dataset?.reactions, []),
                    mine: parseJsonSafe(messageEl?.dataset?.myReactions, []),
                });
                const sortReactions = (list = []) => [...list].sort((a, b) => {
                    const countDiff = Number(b.count || 0) - Number(a.count || 0);
                    if (countDiff !== 0) return countDiff;
                    return String(a.emoji || '').localeCompare(String(b.emoji || ''));
                });
                const applyLocalReaction = (messageEl, emoji) => {
                    const { reactions: currentReactions, mine: currentMine } = getReactionsState(messageEl);
                    const map = new Map();
                    currentReactions.forEach((r) => {
                        if (!r || !r.emoji) return;
                        map.set(r.emoji, { emoji: r.emoji, count: Number(r.count || 0) });
                    });
                    const mineSet = new Set(currentMine || []);
                    const hasEmoji = mineSet.has(emoji);

                    if (hasEmoji) {
                        mineSet.delete(emoji);
                        if (map.has(emoji)) {
                            const item = map.get(emoji);
                            item.count = Math.max(0, Number(item.count || 0) - 1);
                            if (item.count <= 0) {
                                map.delete(emoji);
                            } else {
                                map.set(emoji, item);
                            }
                        }
                    } else {
                        if (mineSet.size) {
                            mineSet.forEach((prev) => {
                                if (map.has(prev)) {
                                    const item = map.get(prev);
                                    item.count = Math.max(0, Number(item.count || 0) - 1);
                                    if (item.count <= 0) {
                                        map.delete(prev);
                                    } else {
                                        map.set(prev, item);
                                    }
                                }
                            });
                            mineSet.clear();
                        }
                        const item = map.get(emoji) || { emoji, count: 0 };
                        item.count = Number(item.count || 0) + 1;
                        map.set(emoji, item);
                        mineSet.add(emoji);
                    }

                    const reactions = sortReactions(Array.from(map.values()).filter((r) => r.count > 0));
                    const mine = Array.from(mineSet);
                    renderReactions(messageEl, reactions, mine);
                    if (activeReactionMessage === messageEl) {
                        setReactionMenuActive(messageEl);
                    }
                    return { reactions, mine };
                };
                const setReactionMenuActive = (messageEl) => {
                    if (!reactionMenuGrid || !messageEl) return;
                    const mine = parseJsonSafe(messageEl.dataset.myReactions, []);
                    const mineSet = new Set(mine);
                    reactionMenuGrid.querySelectorAll('[data-reaction-option]').forEach((btn) => {
                        const emoji = btn.dataset.reactionOption;
                        const isActive = emoji && mineSet.has(emoji);
                        btn.classList.toggle('is-active', Boolean(isActive));
                    });
                    if (reactionMenuCurrent && reactionMenuCurrentEmoji) {
                        const activeEmoji = mine[0] || '';
                        reactionMenuCurrent.hidden = !activeEmoji;
                        if (activeEmoji) {
                            reactionMenuCurrent.removeAttribute('hidden');
                            reactionMenuCurrentEmoji.textContent = activeEmoji;
                        } else {
                            reactionMenuCurrent.setAttribute('hidden', 'true');
                            reactionMenuCurrentEmoji.textContent = '';
                        }
                    }
                    if (reactionMenuMorePanel) {
                        reactionMenuMorePanel.hidden = true;
                        reactionMenuMorePanel.classList.remove('open');
                    }
                };
                const ensureReactionPicker = () => {
                    if (!reactionMenuMorePanel) return null;
                    if (reactionEmojiPicker) return reactionEmojiPicker;
                    const picker = document.createElement('emoji-picker');
                    picker.id = 'reactionEmojiPicker';
                    picker.className = 'emoji-picker-element compact';
                    picker.dataset.reactionPicker = '1';
                    picker.addEventListener('emoji-click', (event) => {
                        const emoji = event.detail?.unicode || event.detail?.emoji || '';
                        if (!emoji || !activeReactionMessage) return;
                        toggleReaction(activeReactionMessage, emoji);
                        reactionMenuMorePanel.hidden = true;
                        reactionMenuMorePanel.classList.remove('open');
                    });
                    reactionMenuMorePanel.appendChild(picker);
                    reactionEmojiPicker = picker;
                    syncEmojiPickerTheme();
                    return picker;
                };
                const setupInitialReactions = () => {
                    document.querySelectorAll('.message[data-message-id]').forEach((message) => {
                        const reactions = parseJsonSafe(message.dataset.reactions, []);
                        const myReactions = parseJsonSafe(message.dataset.myReactions, []);
                        renderReactions(message, reactions, myReactions);
                    });
                };
                const renderReactionMenuOptions = () => {
                    if (!reactionMenuGrid) return;
                    reactionMenuGrid.innerHTML = '';
                    if (Array.isArray(popularReactions)) {
                        popularReactions.forEach((emoji) => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'reaction-menu-btn';
                            btn.dataset.reactionOption = emoji;
                            btn.textContent = emoji;
                            reactionMenuGrid.appendChild(btn);
                        });
                    }
                    const moreBtn = document.createElement('button');
                    moreBtn.type = 'button';
                    moreBtn.className = 'reaction-menu-btn reaction-menu-more';
                    moreBtn.dataset.reactionMore = '1';
                    moreBtn.textContent = '…';
                    reactionMenuGrid.appendChild(moreBtn);
                };
                const closeReactionMenus = () => {
                    if (reactionMenu) {
                        reactionMenu.hidden = true;
                        reactionMenu.setAttribute('hidden', 'true');
                        reactionMenu.style.left = '';
                        reactionMenu.style.top = '';
                        reactionMenu.style.visibility = '';
                        if (reactionMenuMorePanel) {
                            reactionMenuMorePanel.hidden = true;
                            reactionMenuMorePanel.classList.remove('open');
                        }
                    }
                    if (activeReactionMessage) {
                        activeReactionMessage.classList.remove('reaction-menu-open');
                    }
                    activeReactionMessage = null;
                    activeReactionTrigger = null;
                };
                const positionReactionMenu = (triggerEl) => {
                    if (!reactionMenu) return;
                    const viewportPadding = 12;
                    const triggerRect = triggerEl?.getBoundingClientRect();
                    reactionMenu.style.visibility = 'hidden';
                    reactionMenu.hidden = false;
                    reactionMenu.removeAttribute('hidden');
                    const menuRect = reactionMenu.getBoundingClientRect();
                    const fallback = { top: window.innerHeight / 2, left: window.innerWidth / 2, width: 0, height: 0 };
                    const rect = triggerRect || fallback;
                    const isOutgoing = activeReactionMessage?.classList?.contains('message--outgoing');

                    const spaceAbove = rect.top - viewportPadding;
                    const spaceBelow = window.innerHeight - rect.bottom - viewportPadding;
                    const shouldPlaceBelow = spaceBelow >= menuRect.height || spaceBelow >= spaceAbove;

                    let top = shouldPlaceBelow
                        ? rect.bottom + 8
                        : rect.top - menuRect.height - 8;

                    // Clamp vertically into the viewport
                    top = Math.min(Math.max(top, viewportPadding), Math.max(viewportPadding, window.innerHeight - menuRect.height - viewportPadding));

                    let left;
                    if (isMobileViewport()) {
                        left = Math.max(viewportPadding, (window.innerWidth - menuRect.width) / 2);
                    } else if (isOutgoing) {
                        left = rect.right - menuRect.width; // align to the right edge of outgoing messages
                    } else {
                        left = rect.left; // align to the left edge of incoming messages
                    }
                    left = Math.min(Math.max(left, viewportPadding), Math.max(viewportPadding, window.innerWidth - menuRect.width - viewportPadding));

                    reactionMenu.style.left = `${Math.round(left)}px`;
                    reactionMenu.style.top = `${Math.round(top)}px`;
                    reactionMenu.style.visibility = 'visible';
                };
                const repositionReactionMenu = () => {
                    if (!reactionMenu || reactionMenu.hidden || !activeReactionMessage) return;
                    positionReactionMenu(activeReactionTrigger || activeReactionMessage);
                };
                const openReactionMenu = (messageEl, triggerEl = null) => {
                    if (!messageEl || !reactionMenu) return;
                    if (activeReactionMessage === messageEl && !reactionMenu.hidden) {
                        closeReactionMenus();
                        return;
                    }
                    closeReactionMenus();
                    activeReactionMessage = messageEl;
                    activeReactionTrigger = triggerEl || messageEl;
                    reactionMenu.removeAttribute('hidden');
                    reactionMenu.hidden = false;
                    positionReactionMenu(activeReactionTrigger);
                    messageEl.classList.add('reaction-menu-open');
                    setReactionMenuActive(messageEl);
                    syncEmojiPickerTheme();
                };
                const handleReactionMenuClick = (event) => {
                    const option = event.target.closest('[data-reaction-option]');
                    if (option && activeReactionMessage) {
                        toggleReaction(activeReactionMessage, option.dataset.reactionOption);
                        return;
                    }
                    const more = event.target.closest('[data-reaction-more]');
                    if (more && activeReactionMessage) {
                        const picker = ensureReactionPicker();
                        if (!picker) return;
                        if (!reactionMenuMorePanel) return;
                        const isOpen = !reactionMenuMorePanel.hidden;
                        reactionMenuMorePanel.hidden = isOpen;
                        reactionMenuMorePanel.classList.toggle('open', !isOpen);
                        requestAnimationFrame(repositionReactionMenu);
                    }
                };

                const isPayloadFromMe = (payload) => {
                    if (!payload) return false;
                    const actorUserId = payload.actor_user_id ? Number(payload.actor_user_id) : null;
                    const actorParticipantId = payload.actor_participant_id ? Number(payload.actor_participant_id) : null;
                    if (actorUserId && currentUserId && Number(currentUserId) === actorUserId) return true;
                    if (actorParticipantId && currentParticipantId && Number(currentParticipantId) === actorParticipantId) return true;
                    return false;
                };
                const getMyReactions = (messageEl, payload) => {
                    if (isPayloadFromMe(payload) && payload?.your_reactions && Array.isArray(payload.your_reactions)) {
                        return payload.your_reactions;
                    }
                    return parseJsonSafe(messageEl?.dataset?.myReactions, []);
                };
                const toggleReaction = async (messageEl, emoji) => {
                    if (!messageEl || !emoji) return;
                    activeReactionMessage = messageEl;
                    const url = getReactionUrl(messageEl);
                    if (!url) return;
                    const previous = getReactionsState(messageEl);
                    applyLocalReaction(messageEl, emoji);
                    const formData = new FormData();
                    formData.append('emoji', emoji);
                    formData.append('_token', csrfToken);
                    messageEl.classList.add('reactions-loading');

                    try {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: formData,
                        });

                        if (!response.ok) {
                            console.error('Reaction request failed', response.status);
                            renderReactions(messageEl, previous.reactions, previous.mine);
                            if (activeReactionMessage === messageEl) {
                                setReactionMenuActive(messageEl);
                            }
                            return;
                        }

                        const payload = await response.json();
                        const reactions = Array.isArray(payload.reactions) ? payload.reactions : null;
                        const mineRaw = Array.isArray(payload.your_reactions)
                            ? payload.your_reactions
                            : getMyReactions(messageEl, payload);
                        const mine = Array.isArray(mineRaw) ? mineRaw : [];
                        if (Array.isArray(reactions)) {
                            renderReactions(messageEl, reactions, mine);
                        } else {
                            // Keep optimistic state only when server did not return reaction data
                            renderReactions(messageEl, previous.reactions, mine.length ? mine : previous.mine);
                        }
                        if (activeReactionMessage === messageEl) {
                            setReactionMenuActive(messageEl);
                        }
                    } catch (err) {
                        console.error('Reaction error', err);
                        renderReactions(messageEl, previous.reactions, previous.mine);
                        if (activeReactionMessage === messageEl) {
                            setReactionMenuActive(messageEl);
                        }
                    } finally {
                        messageEl.classList.remove('reactions-loading');
                        closeReactionMenus();
                    }
                };

                const updateReactionsFromEvent = (messageId, reactions, payload = {}) => {
                    const messageEl = document.querySelector(`.message[data-message-id="${messageId}"]`);
                    if (!messageEl) return;
                    const mine = getMyReactions(messageEl, payload);
                    renderReactions(messageEl, reactions || [], mine);
                    if (activeReactionMessage === messageEl) {
                        setReactionMenuActive(messageEl);
                    }
                };
                const autosizeComposer = () => {
                    if (!chatInput) return;
                    chatInput.style.height = 'auto';
                    const maxHeight = 220;
                    chatInput.style.height = Math.min(chatInput.scrollHeight, maxHeight) + 'px';
                };
                const hideEmojiPanel = () => {
                    if (!chatEmojiPanel) return;
                    chatEmojiPanel.hidden = true;
                    chatEmojiPanel.classList.remove('open');
                    emojiPickerMode = 'input';
                    reactionPickerTarget = null;
                };
                const showEmojiPanel = (mode = 'input', targetMessage = null) => {
                    if (!chatEmojiPanel) return;
                    emojiPickerMode = mode;
                    reactionPickerTarget = targetMessage;
                    chatEmojiPanel.hidden = false;
                    chatEmojiPanel.classList.add('open');
                    if (window.refreshLucideIcons) {
                        window.refreshLucideIcons();
                    }
                };
                const insertEmojiIntoInput = (emoji) => {
                    if (!chatInput || !emoji) return;
                    const start = chatInput.selectionStart ?? chatInput.value.length;
                    const end = chatInput.selectionEnd ?? chatInput.value.length;
                    const value = chatInput.value;
                    chatInput.value = value.slice(0, start) + emoji + value.slice(end);
                    const cursor = start + emoji.length;
                    chatInput.selectionStart = cursor;
                    chatInput.selectionEnd = cursor;
                    chatInput.focus();
                    autosizeComposer();
                    updateSendButtonState();
                };

                const showBanState = (messageText = 'You were banned by the host. Chat is locked.') => {
                    if (!chatInputWrapper) return;
                    chatInputWrapper.innerHTML = `
                        <div class="flash flash-danger">
                            <span>${escapeHtml(messageText)}</span>
                        </div>
                    `;
                };

                const banModal = (() => {
                    let overlay = null;
                    let confirmBtn = null;
                    let cancelBtn = null;
                    let resolver = null;

                    const ensureModal = () => {
                        if (overlay) return;
                        overlay = document.createElement('div');
                        overlay.className = 'modal-overlay';
                        overlay.dataset.banModal = '1';
                        overlay.hidden = true;
                        overlay.innerHTML = `
                            <div class="modal-dialog">
                                <div class="modal-header">
                                    <div class="modal-title-group">
                                        <div class="modal-eyebrow">Moderation</div>
                                        <div class="modal-title">Ban participant?</div>
                                    </div>
                                    <button type="button" class="modal-close" data-ban-cancel aria-label="Close">
                                        <i data-lucide="x"></i>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <div class="modal-text">Are you sure you want to ban this participant? They won’t be able to post messages or questions.</div>
                                </div>
                                <div class="modal-actions">
                                    <button type="button" class="btn btn-sm btn-ghost" data-ban-cancel>Cancel</button>
                                    <button type="button" class="btn btn-sm btn-danger" data-ban-confirm>Ban</button>
                                </div>
                            </div>
                        `;
                        document.body.appendChild(overlay);
                        confirmBtn = overlay.querySelector('[data-ban-confirm]');
                        cancelBtn = overlay.querySelectorAll('[data-ban-cancel]');

                        const close = () => {
                            overlay.classList.remove('show');
                            document.body.classList.remove('modal-open');
                            if (resolver) {
                                resolver(false);
                                resolver = null;
                            }
                            setTimeout(() => overlay.hidden = true, 120);
                        };

                        overlay.addEventListener('click', (event) => {
                            if (event.target === overlay) {
                                close();
                            }
                        });

                        cancelBtn.forEach((btn) => btn.addEventListener('click', close));

                        if (confirmBtn) {
                            confirmBtn.addEventListener('click', () => {
                                overlay.classList.remove('show');
                                document.body.classList.remove('modal-open');
                                if (resolver) {
                                    resolver(true);
                                    resolver = null;
                                }
                                setTimeout(() => overlay.hidden = true, 120);
                            });
                        }
                    };

                    const open = () => new Promise((resolve) => {
                        ensureModal();
                        resolver = resolve;
                        overlay.hidden = false;
                        requestAnimationFrame(() => overlay.classList.add('show'));
                        document.body.classList.add('modal-open');
                        if (window.refreshLucideIcons) {
                            window.refreshLucideIcons();
                        }
                    });

                    return { open };
                })();

                const bindBanForms = (scope = document) => {
                    const banForms = scope.querySelectorAll('form[data-ban-confirm]');
                    banForms.forEach((form) => {
                        if (form.dataset.banBound === '1') return;
                        form.dataset.banBound = '1';
                        form.addEventListener('submit', async (event) => {
                            event.preventDefault();
                            const confirmed = await banModal.open();
                            if (confirmed) {
                                form.submit();
                            }
                        });
                    });
                };

                function setupChatTabs() {
                    if (!chatTabButtons.length || !chatPanes.length) return;
                    let active = 'chat';
                    const current = Array.from(chatTabButtons).find((btn) => btn.classList.contains('active'));
                    if (current?.dataset.chatTab) {
                        active = current.dataset.chatTab;
                    }

                    const sync = () => {
                        chatTabButtons.forEach((btn) => {
                            const isActive = btn.dataset.chatTab === active;
                            btn.classList.toggle('active', isActive);
                        });
                        chatPanes.forEach((pane) => {
                            const isMatch = pane.dataset.chatPanel === active;
                            pane.hidden = !isMatch;
                        });
                    };

                    chatTabButtons.forEach((btn) => {
                        btn.addEventListener('click', () => {
                            active = btn.dataset.chatTab || 'chat';
                            sync();
                        });
                    });

                    sync();
                }

                if (queueSoundUrl) {
                    window.queueSoundUrl = queueSoundUrl;
                    if (typeof window.initQueueSoundPlayer === 'function') {
                        window.initQueueSoundPlayer(queueSoundUrl);
                    }
                }

                setupChatTabs();
                bindBanForms();
                setupInitialReactions();
                renderReactionMenuOptions();
                autosizeComposer();
                updateSendButtonState();
                scrollChatToBottom();
                if (reactionMenu) {
                    reactionMenu.addEventListener('click', handleReactionMenuClick);
                }

                if (chatInput) {
                    chatInput.addEventListener('input', () => {
                        autosizeComposer();
                        updateSendButtonState();
                    });
                    chatInput.addEventListener('keydown', (event) => {
                        if (event.key === 'Enter' && !event.shiftKey && !event.ctrlKey && !event.altKey && !event.metaKey) {
                            event.preventDefault();
                            if ((chatInput.value || '').trim().length === 0) {
                                return;
                            }
                            if (sendButton?.disabled) {
                                return;
                            }
                            sendButton?.click();
                        }
                    });
                }

                if (chatEmojiPicker) {
                    chatEmojiPicker.addEventListener('emoji-click', (event) => {
                        const emoji = event.detail?.unicode || event.detail?.emoji || '';
                        if (!emoji) return;
                        if (emojiPickerMode === 'reaction' && reactionPickerTarget) {
                            toggleReaction(reactionPickerTarget, emoji);
                            closeReactionMenus();
                        } else {
                            insertEmojiIntoInput(emoji);
                        }
                        hideEmojiPanel();
                    });
                }

                if (chatEmojiToggle) {
                    chatEmojiToggle.addEventListener('click', (event) => {
                        event.preventDefault();
                        const isOpen = chatEmojiPanel && !chatEmojiPanel.hidden && emojiPickerMode === 'input';
                        if (isOpen) {
                            hideEmojiPanel();
                        } else {
                            showEmojiPanel('input', null);
                        }
                    });
                }

                  const QR_CANVAS_SIZE = 360;
                  const QR_FETCH_SIZE = 720;
                  const buildQrUrl = (link, size = QR_FETCH_SIZE) =>
                      `https://api.qrserver.com/v1/create-qr-code/?format=png&margin=16&ecc=H&size=${size}x${size}&data=${encodeURIComponent(link)}`;
                  const getCssVar = (name, fallback = '') => {
                      const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
                      return value || fallback;
                  };
                  const parseQrModules = (imageData, size) => {
                      const isDark = (x, y) => {
                          const idx = (y * size + x) * 4;
                          return imageData.data[idx] < 150;
                      };
                      const sampleY = Math.max(1, Math.floor(size * 0.14));
                      const runs = [];
                      let lastColor = isDark(0, sampleY);
                      let start = 0;
                      for (let x = 1; x <= size; x++) {
                          const current = x === size ? !lastColor : isDark(x, sampleY);
                          if (current !== lastColor) {
                              runs.push({ color: lastColor, length: x - start });
                              start = x;
                              lastColor = current;
                          }
                      }
                      if (!runs.length) {
                          return null;
                      }
                      const quietZone = runs[0];
                      const finderRun = runs.find((run) => run.color && run.length >= 5);
                      if (!quietZone || !finderRun) {
                          return null;
                      }
                      const moduleSize = Math.max(1, Math.round(finderRun.length / 7));
                      const available = size - quietZone.length * 2;
                      const moduleCount = Math.max(21, Math.round(available / moduleSize));
                      if (moduleCount <= 0) {
                          return null;
                      }
                      const modules = [];
                      const offset = quietZone.length;
                      for (let row = 0; row < moduleCount; row++) {
                          const sampleYPos = Math.min(size - 1, Math.floor(offset + (row + 0.5) * moduleSize));
                          const rowValues = [];
                          for (let col = 0; col < moduleCount; col++) {
                              const sampleXPos = Math.min(size - 1, Math.floor(offset + (col + 0.5) * moduleSize));
                              rowValues.push(isDark(sampleXPos, sampleYPos));
                          }
                          modules.push(rowValues);
                      }
                      return { modules, moduleSize, moduleCount, offset };
                  };
                  async function fetchQrImage(link, size) {
                      const response = await fetch(buildQrUrl(link, size), { cache: 'force-cache' });
                      if (!response.ok) {
                          throw new Error('Unable to load QR code');
                      }
                      const blob = await response.blob();
                      if (typeof createImageBitmap === 'function') {
                          return createImageBitmap(blob, { resizeWidth: size, resizeHeight: size });
                      }
                      return new Promise((resolve, reject) => {
                          const url = URL.createObjectURL(blob);
                          const img = new Image();
                          img.onload = () => {
                              URL.revokeObjectURL(url);
                              resolve(img);
                          };
                          img.onerror = (err) => {
                              URL.revokeObjectURL(url);
                              reject(err);
                          };
                          img.src = url;
                      });
                  }
                  let lastRenderedLink = null;
                  let lastRenderedTheme = null;
                  async function drawStyledQr(link) {
                      if (!qrCanvas || !link) return;
                      const currentTheme = document.body.dataset.theme || 'light';
                      if (lastRenderedLink === link && lastRenderedTheme === currentTheme) {
                          return;
                      }
                      const renderJob = (async () => {
                          const canvasSize = QR_CANVAS_SIZE;
                          qrCanvas.width = canvasSize;
                          qrCanvas.height = canvasSize;
                          qrCanvas.style.width = '100%';
                          qrCanvas.style.height = '100%';
                          const ctx = qrCanvas.getContext('2d');
                          if (!ctx) return;
                          const backgroundColor = '#ffffff';
                          const dotColor = '#121212';
                          ctx.clearRect(0, 0, canvasSize, canvasSize);
                          ctx.fillStyle = backgroundColor;
                          ctx.fillRect(0, 0, canvasSize, canvasSize);
                          try {
                              const image = await fetchQrImage(link, QR_FETCH_SIZE);
                              const offscreen = document.createElement('canvas');
                              offscreen.width = canvasSize;
                              offscreen.height = canvasSize;
                              const offCtx = offscreen.getContext('2d');
                              if (!offCtx) return;
                              offCtx.drawImage(image, 0, 0, canvasSize, canvasSize);
                              const imageData = offCtx.getImageData(0, 0, canvasSize, canvasSize);
                              const parsed = parseQrModules(imageData, canvasSize);
                              if (parsed) {
                                  ctx.fillStyle = backgroundColor;
                                  ctx.fillRect(0, 0, canvasSize, canvasSize);
                                  const moduleSize = parsed.moduleSize;
                                  const strokeWidth = Math.max(3, moduleSize * 1.05);
                                  const dotRadius = Math.min(moduleSize * 0.58, strokeWidth / 1.15);
                                  ctx.lineWidth = strokeWidth;
                                  ctx.lineCap = 'round';
                                  ctx.lineJoin = 'round';
                                  ctx.fillStyle = dotColor;
                                  ctx.strokeStyle = dotColor;
                                  parsed.modules.forEach((row, rowIndex) => {
                                      row.forEach((cell, colIndex) => {
                                          if (!cell) return;
                                          const centerX = parsed.offset + (colIndex + 0.5) * moduleSize;
                                          const centerY = parsed.offset + (rowIndex + 0.5) * moduleSize;
                                          ctx.beginPath();
                                          ctx.arc(centerX, centerY, dotRadius, 0, Math.PI * 2);
                                          ctx.fill();
                                          if (colIndex < parsed.moduleCount - 1 && row[colIndex + 1]) {
                                              ctx.beginPath();
                                              ctx.moveTo(centerX, centerY);
                                              ctx.lineTo(centerX + moduleSize, centerY);
                                              ctx.stroke();
                                          }
                                          if (rowIndex < parsed.moduleCount - 1 && parsed.modules[rowIndex + 1][colIndex]) {
                                              ctx.beginPath();
                                              ctx.moveTo(centerX, centerY);
                                              ctx.lineTo(centerX, centerY + moduleSize);
                                              ctx.stroke();
                                          }
                                      });
                                  });
                                  const blankRadius = Math.min(canvasSize / 2.2, parsed.moduleSize * 4);
                                  ctx.globalCompositeOperation = 'destination-out';
                                  ctx.beginPath();
                                  ctx.arc(canvasSize / 2, canvasSize / 2, blankRadius, 0, Math.PI * 2);
                                  ctx.fill();
                                  ctx.globalCompositeOperation = 'source-over';
                                  ctx.fillStyle = backgroundColor;
                                  ctx.beginPath();
                                  ctx.arc(canvasSize / 2, canvasSize / 2, Math.max(blankRadius - 4, 4), 0, Math.PI * 2);
                                  ctx.fill();
                                  ctx.globalCompositeOperation = 'source-over';
                              } else {
                                  ctx.drawImage(image, 0, 0, canvasSize, canvasSize);
                              }
                              if (image && typeof image.close === 'function') {
                                  image.close();
                              }
                          } catch (error) {
                              console.error('Styled QR build failed', error);
                              const fallbackUrl = buildQrUrl(link, QR_FETCH_SIZE);
                              const fallback = new Image();
                              fallback.crossOrigin = 'anonymous';
                              fallback.src = fallbackUrl;
                              await new Promise((resolve) => {
                                  fallback.onload = fallback.onerror = () => resolve();
                              });
                              ctx.drawImage(fallback, 0, 0, canvasSize, canvasSize);
                              return;
                          }
                            lastRenderedLink = link;
                            lastRenderedTheme = currentTheme;
                      })();
                      await renderJob;
                  }
                  const openQr = async () => {
                      if (!qrOverlay) return;
                      qrOverlay.classList.add('show');
                      qrOverlay.setAttribute('aria-hidden', 'false');
                      await drawStyledQr(publicLink);
                  };

                  function closeQr() {
                      if (!qrOverlay) return;
                      qrOverlay.classList.remove('show');
                      qrOverlay.setAttribute('aria-hidden', 'true');
                  }

                  if (qrButton) {
                    qrButton.addEventListener('click', () => {
                      openQr().catch(() => {});
                    });
                  }
                if (qrClose) {
                    qrClose.addEventListener('click', closeQr);
                }
                  if (qrOverlay) {
                      qrOverlay.addEventListener('click', (event) => {
                          if (event.target === qrOverlay) {
                              closeQr();
                          }
                      });
                  }
                  document.addEventListener('keydown', (event) => {
                      if (event.key === 'Escape') {
                          closeQr();
                      }
                  });
                  const themeObserver = new MutationObserver(() => {
                      if (qrOverlay?.classList.contains('show')) {
                          drawStyledQr(publicLink);
                      }
                      syncQueuePipTheme(queuePipWindow?.document);
                  });
                  themeObserver.observe(document.body, { attributes: true, attributeFilter: ['data-theme'] });

                function syncQueuePipTheme(targetDoc) {
                    if (!targetDoc) return;
                    const theme = document.body?.dataset.theme || 'light';
                    targetDoc.body.dataset.theme = theme;
                    targetDoc.documentElement.dataset.theme = theme;
                }

                function cloneStylesToDocument(targetDoc) {
                    if (!targetDoc || !targetDoc.head || queuePipStylesCloned) return;
                    const styles = document.querySelectorAll('link[rel=\"stylesheet\"], style');
                    targetDoc.head.innerHTML = '';
                    styles.forEach((node) => {
                        targetDoc.head.appendChild(node.cloneNode(true));
                    });
                    queuePipStylesCloned = true;
                }

                function bindPipQueueForms(root) {
                    if (!root) return;
                    const handler = (event) => {
                        const form = event.target.closest('form[data-remote=\"questions-panel\"]');
                        if (!form) return;
                        event.preventDefault();
                        submitRemoteForm(form, () => {
                            reloadQuestionsPanel();
                            renderQueuePipContent();
                        });
                        const item = form.closest('.queue-item');
                        const statusInput = form.querySelector('input[name=\"status\"]');
                        if (statusInput && statusInput.value === 'answered') {
                            const id = normalizeId(item?.dataset.questionId);
                            if (id) {
                                markMainQueueItemSeen(id);
                            }
                        }
                    };
                    root.addEventListener('submit', handler, { capture: true });
                }

                function renderQueuePipContent() {
                    if (!queuePipWindow || queuePipWindow.closed) {
                        closeQueuePip();
                        return;
                    }
                    const sourceQueue = document.querySelector('#queuePanel');
                    if (!sourceQueue) return;
                    const pipDoc = queuePipWindow.document;
                    cloneStylesToDocument(pipDoc);
                    syncQueuePipTheme(pipDoc);
                    pipDoc.body.className = 'queue-pip-body';
                    const shell = pipDoc.createElement('div');
                    shell.className = 'queue-pip-shell';
                    if (!supportsDocumentPip) {
                        const fallbackNote = pipDoc.createElement('div');
                        fallbackNote.className = 'queue-pip-fallback';
                        fallbackNote.textContent = 'Picture-in-picture is not available. Using a regular window instead.';
                        shell.appendChild(fallbackNote);
                    }
                    const queueClone = sourceQueue.cloneNode(true);
                    queueClone.querySelectorAll('[data-queue-pip]').forEach((btn) => btn.remove());
                    queueClone.querySelectorAll('[data-toggle-history]').forEach((btn) => btn.remove());
                    shell.appendChild(queueClone);
                    pipDoc.body.innerHTML = '';
                    pipDoc.body.appendChild(shell);
                    if (window.refreshLucideIcons) {
                        window.refreshLucideIcons(queueClone);
                    }
                    if (typeof window.setupQueueNewHandlers === 'function') {
                        window.setupQueueNewHandlers(queueClone);
                    }
                    bindPipQueueForms(queueClone);
                    queueClone.addEventListener('click', (event) => {
                        const item = event.target.closest('.queue-item');
                        if (!item) return;
                        const id = normalizeId(item.dataset.questionId);
                        if (!id) return;
                        if (item.classList.contains('queue-item-new')) {
                            item.classList.remove('queue-item-new');
                            markMainQueueItemSeen(id);
                        }
                    });
                }

                function closeQueuePip() {
                    if (queuePipSyncTimer) {
                        clearInterval(queuePipSyncTimer);
                        queuePipSyncTimer = null;
                    }
                    if (queuePipWindow && !queuePipWindow.closed) {
                        try {
                            queuePipWindow.close();
                        } catch (e) {
                            /* ignore */
                        }
                    }
                    queuePipWindow = null;
                    queuePipStylesCloned = false;
                    if (queuePipButton) {
                        queuePipButton.classList.remove('active');
                    }
                }

                async function openQueuePip() {
                    const sourceQueue = document.querySelector('#queuePanel');
                    if (!sourceQueue) return;
                    if (queuePipWindow && !queuePipWindow.closed) {
                        queuePipWindow.focus();
                        renderQueuePipContent();
                        return;
                    }

                    try {
                        if (supportsDocumentPip) {
                            queuePipWindow = await window.documentPictureInPicture.requestWindow({
                                width: 420,
                                height: 640,
                            });
                        } else {
                            queuePipWindow = window.open('', 'queuePipFallback', 'width=420,height=640,resizable=yes');
                        }
                    } catch (error) {
                        console.error('Cannot open picture-in-picture window', error);
                        return;
                    }

                    if (!queuePipWindow) return;
                    queuePipStylesCloned = false;
                    if (window.lucide) {
                        queuePipWindow.lucide = window.lucide;
                    }
                    queuePipWindow.document.title = 'Question queue';
                    queuePipWindow.addEventListener('pagehide', closeQueuePip);
                    queuePipWindow.addEventListener('beforeunload', closeQueuePip);
                    renderQueuePipContent();
                    queuePipSyncTimer = setInterval(renderQueuePipContent, 6000);
                    if (queuePipButton) {
                        queuePipButton.classList.add('active');
                    }
                }

                function bindQueueInteractions(scope = document) {
                    if (!scope) return;
                    if (typeof window.rebindQueuePanels === 'function') {
                        window.rebindQueuePanels(scope);
                    }
                }
                if (questionsPanel) {
                    bindQueueInteractions();
                }

                if (queuePipButton) {
                    queuePipButton.addEventListener('click', () => {
                        if (queuePipWindow && !queuePipWindow.closed) {
                            closeQueuePip();
                        } else {
                            openQueuePip();
                        }
                    });

                    if (!supportsDocumentPip) {
                        queuePipButton.title = 'Picture-in-picture is not fully supported in this browser.';
                    }
                }

                const buildFormData = (form) => {
                    const view = form?.ownerDocument?.defaultView;
                    if (view && typeof view.FormData === 'function') {
                        return new view.FormData(form);
                    }
                    return new FormData(form);
                };

                const submitRemoteForm = async (form, onDone) => {
                    const formData = buildFormData(form);
                    let method = (form.getAttribute('method') || 'POST').toUpperCase();
                    const override = formData.get('_method');
                    if (override) {
                        method = override.toString().toUpperCase();
                    }
                    const token = formData.get('_token') || csrfMeta?.getAttribute('content') || '';

                    try {
                        const response = await fetch(form.action, {
                            method,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': token,
                            },
                            body: formData,
                        });
                        if (!response.ok) {
                            console.error('Remote form failed', response.status);
                        } else if (typeof onDone === 'function') {
                            onDone();
                        }
                    } catch (err) {
                        console.error('Remote form error', err);
                    }
                };

                const avatarColorFromName = (name = 'Guest') => {
                    const str = String(name || 'Guest');
                    let hash = 0;
                    for (let i = 0; i < str.length; i += 1) {
                        hash = ((hash << 5) - hash) + str.charCodeAt(i);
                        hash |= 0;
                    }
                    const idx = Math.abs(hash) % avatarPalette.length;
                    return avatarPalette[idx];
                };

                const setReplyContext = (author, text, id) => {
                    if (!replyToInput || !replyPreview || !replyPreviewAuthor || !replyPreviewText) return;
                    replyToInput.value = id || '';
                    if (id) {
                        replyPreviewAuthor.textContent = author || 'Guest';
                        replyPreviewText.textContent = text || '';
                        replyPreview.hidden = false;
                    } else {
                        replyPreview.hidden = true;
                        replyPreviewAuthor.textContent = '';
                        replyPreviewText.textContent = '';
                    }
                };

                const clearReplyContext = () => setReplyContext('', '', '');

                if (replyPreviewCancel) {
                    replyPreviewCancel.addEventListener('click', clearReplyContext);
                }

                async function reloadQuestionsPanel() {
                    if (!questionsPanel || !questionsPanelUrl) return;

                    try {
                        const response = await fetch(questionsPanelUrl, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (!response.ok) {
                            console.error('Failed to refresh questions panel', response.status);
                            return;
                        }

                        const html = await response.text();
                        questionsPanel.innerHTML = html;
                        bindQueueInteractions(questionsPanel);
                        renderQueuePipContent();
                        const hasNewItems = questionsPanel.querySelector('.queue-item.queue-item-new');
                        bindBanForms(questionsPanel);
                        if ((queueNeedsNew || hasNewItems) && typeof window.markQueueHasNew === 'function') {
                            window.markQueueHasNew();
                            queueNeedsNew = false;
                        }
                    } catch (e) {
                        console.error('Refresh questions panel error', e);
                    }
                }

                function startQuestionsPolling() {
                    if (!questionsPanel || questionsPollTimer) return;
                    questionsPollTimer = setInterval(reloadQuestionsPanel, 6000);
                }

                if (questionsPanel) {
                    questionsPanel.addEventListener('submit', (event) => {
                        const target = event.target;
                        if (!(target instanceof HTMLFormElement)) return;
                        if (target.dataset.remote !== 'questions-panel') return;
                        event.preventDefault();
                        submitRemoteForm(target, reloadQuestionsPanel);
                    });
                }

                async function reloadMyQuestionsPanel() {
                    if (!myQuestionsPanel || !myQuestionsPanelUrl) return;

                    try {
                        const response = await fetch(myQuestionsPanelUrl, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (!response.ok) {
                            console.error('Failed to refresh my questions panel', response.status);
                            return;
                        }

                        const html = await response.text();
                        myQuestionsPanel.innerHTML = html;
                        if (typeof window.refreshLucideIcons === 'function') {
                            window.refreshLucideIcons();
                        }
                    } catch (e) {
                        console.error('Refresh my questions panel error', e);
                    }
                }

                function startMyQuestionsPolling() {
                    if (!myQuestionsPanel || myQuestionsPollTimer) return;
                    myQuestionsPollTimer = setInterval(reloadMyQuestionsPanel, 6000);
                }

                if (myQuestionsPanel) {
                    myQuestionsPanel.addEventListener('submit', (event) => {
                        const target = event.target;
                        if (!(target instanceof HTMLFormElement)) return;
                        if (target.dataset.remote !== 'my-questions-panel') return;
                        event.preventDefault();
                        submitRemoteForm(target, reloadMyQuestionsPanel);
                    });
                }

                if (chatContainer) {
                    chatContainer.addEventListener('click', (event) => {
                        const reactionTrigger = event.target.closest('[data-reaction-trigger]');
                        if (reactionTrigger) {
                            const messageEl = reactionTrigger.closest('.message');
                            if (messageEl) {
                                openReactionMenu(messageEl, reactionTrigger);
                            }
                            return;
                        }

                        const reactionChip = event.target.closest('[data-reaction-chip]');
                        if (reactionChip) {
                            const messageEl = reactionChip.closest('.message');
                            toggleReaction(messageEl, reactionChip.dataset.emoji);
                            return;
                        }

                        const btn = event.target.closest('[data-reply-id]');
                        if (!btn) return;
                        event.preventDefault();
                        setReplyContext(btn.dataset.replyAuthor, btn.dataset.replyText, btn.dataset.replyId);
                        scrollChatToBottom();
                    });
                }

                document.addEventListener('click', (event) => {
                    const reactionMenuEl = event.target.closest('[data-reaction-menu]');
                    const reactionTrigger = event.target.closest('[data-reaction-trigger]');
                    if (!reactionMenuEl && !reactionTrigger) {
                        closeReactionMenus();
                    }
                    if (!chatEmojiPanel || chatEmojiPanel.hidden) return;
                    const insidePicker = event.target.closest('.emoji-picker-panel');
                    if (insidePicker) return;
                    const insideComposer = event.target.closest('.chat-composer');
                    const insideMessage = event.target.closest('.message');
                    if (emojiPickerMode === 'input' && !insideComposer) {
                        hideEmojiPanel();
                    } else if (emojiPickerMode === 'reaction' && !insideMessage) {
                        hideEmojiPanel();
                    }
                });
                window.addEventListener('resize', () => {
                    requestAnimationFrame(repositionReactionMenu);
                });

                if (window.Echo) {
                    const channelName = 'room.' + roomId;
                    window.Echo.channel(channelName)
                        .listen('MessageSent', (e) => {
                        const container = document.querySelector('.messages-container');
                        if (!container) return;
                        const existing = container.querySelector(`.message[data-message-id="${e.id}"]`);
                        if (existing) {
                            existing.classList.remove('message--pending');
                                existing.removeAttribute('data-temp-id');
                                existing.removeAttribute('data-temp-key');
                                return;
                            }
                            const authorUserId = normalizeId(e.author?.user_id);
                            const authorParticipantId = normalizeId(e.author?.participant_id);
                            const tempKey = makeMessageKey(e.content, authorUserId, authorParticipantId, e.reply_to?.id, e.as_question);
                            const pendingMatch = container.querySelector(`.message--pending[data-temp-key="${tempKey}"]`);
                            if (pendingMatch) {
                                updateMessageElementFromPayload(pendingMatch, e);
                                bindBanForms(pendingMatch);
                                scrollChatToBottom();
                                if (window.refreshLucideIcons) {
                                    window.refreshLucideIcons();
                                }
                                return;
                            }
                            removeEmptyMessageState();
                            const wrapper = createMessageElement(e, { pending: false });
                            container.appendChild(wrapper);
                            bindBanForms(wrapper);
                            scrollChatToBottom();
                            if (window.refreshLucideIcons) {
                                window.refreshLucideIcons();
                            }
                        })
                        .listen('ReactionUpdated', (payload) => {
                            updateReactionsFromEvent(payload.message_id, payload.reactions, payload);
                        })
                        .listen('QuestionCreated', () => {
                            if (questionsPanel) {
                                queueNeedsNew = true;
                                reloadQuestionsPanel();
                            }
                            if (isOwnerUser && typeof window.playQueueSound === 'function') {
                                window.playQueueSound(queueSoundUrl);
                            }
                            if (myQuestionsPanel) {
                                reloadMyQuestionsPanel();
                            }
                        })
                        .listen('QuestionUpdated', () => {
                            if (questionsPanel) {
                                reloadQuestionsPanel();
                            }
                            if (myQuestionsPanel) {
                                reloadMyQuestionsPanel();
                            }
                        })
                        .error(() => {
                            startQuestionsPolling();
                            startMyQuestionsPolling();
                        });
                } else {
                    startQuestionsPolling();
                    startMyQuestionsPolling();
                }

                const chatForm = document.getElementById('chat-form');
                const setSendingState = (isSending) => {
                    if (!sendButton) return;
                    sendButton.classList.toggle('sending', isSending);
                    sendButton.setAttribute('aria-busy', isSending ? 'true' : 'false');
                    updateSendButtonState();
                };

                if (chatForm) {
                    chatForm.addEventListener('submit', async (event) => {
                        event.preventDefault();

                        const formData = new FormData(chatForm);
                        const content = (formData.get('content') || '').toString().trim();
                        if (!content) {
                            return;
                        }
                        formData.set('content', content);
                        const url = chatForm.action;
                        const optimisticId = `temp-${Date.now()}`;
                        const authorNameForOptimistic = currentUserName || currentParticipantName || 'Guest';
                        const replyId = replyToInput?.value || '';
                        const replyData = replyId ? {
                            id: replyId,
                            author: replyPreviewAuthor?.textContent || '',
                            content: replyPreviewText?.textContent || '',
                        } : null;
                        const optimisticPayload = {
                            id: optimisticId,
                            content,
                            created_at: new Date().toISOString(),
                            author: {
                                name: authorNameForOptimistic,
                                user_id: currentUserId,
                                participant_id: currentParticipantId,
                                is_owner: isOwnerUser,
                                is_dev: Boolean(@json(auth()->user()?->is_dev)),
                            },
                            as_question: Boolean(formData.get('as_question')),
                            reply_to: replyData,
                            reactions: [],
                            myReactions: [],
                        };
                        const container = document.querySelector('.messages-container');
                        let optimisticEl = null;
                        if (container) {
                            removeEmptyMessageState();
                            optimisticEl = createMessageElement(optimisticPayload, { pending: true, allowBan: true });
                            container.appendChild(optimisticEl);
                            scrollChatToBottom();
                            if (window.refreshLucideIcons) {
                                window.refreshLucideIcons();
                            }
                        }

                        try {
                            setSendingState(true);
                            const response = await fetch(url, {
                                method: 'POST',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': formData.get('_token'),
                                },
                                body: formData,
                            });

                            if (response.status === 403) {
                                if (optimisticEl) optimisticEl.remove();
                                showBanState('You were banned by the host. Chat is locked.');
                                return;
                            }

                            if (!response.ok) {
                                if (optimisticEl) optimisticEl.remove();
                                console.error('Send message failed', response.status);
                                return;
                            }

                            const payload = await response.json().catch(() => ({}));
                            if (payload?.message_id) {
                                const containerEl = document.querySelector('.messages-container');
                                if (!optimisticEl || !optimisticEl.isConnected) {
                                    const tmpKey = optimisticPayload
                                        ? makeMessageKey(
                                            optimisticPayload.content,
                                            optimisticPayload.author?.user_id,
                                            optimisticPayload.author?.participant_id,
                                            optimisticPayload.reply_to?.id,
                                            optimisticPayload.as_question
                                        )
                                        : null;
                                    optimisticEl = containerEl?.querySelector(`.message--pending[data-temp-key="${tmpKey}"]`) || containerEl?.querySelector(`.message[data-message-id="${payload.message_id}"]`);
                                }
                                if (optimisticEl) {
                                    const merged = {
                                        ...optimisticPayload,
                                        ...payload,
                                        id: payload.message_id,
                                    };
                                    updateMessageElementFromPayload(optimisticEl, merged);
                                    containerEl?.scrollTo?.(0, containerEl.scrollHeight);
                                }
                            }
                        } catch (e) {
                            if (optimisticEl) optimisticEl.remove();
                            console.error('Send message error', e);
                        } finally {
                            removeEmptyMessageState();
                            const textarea = chatForm.querySelector('textarea[name="content"]');
                            if (textarea) {
                                textarea.value = '';
                                textarea.style.height = 'auto';
                                autosizeComposer();
                                updateSendButtonState();
                            }
                            const questionCheckbox = chatForm.querySelector('input[name="as_question"]');
                            if (questionCheckbox) {
                                questionCheckbox.checked = false;
                            }
                            clearReplyContext();
                            hideEmojiPanel();
                            setSendingState(false);
                        }
                    });
                }

                if (chatContainer) {
                    scrollChatToBottom();
                }
            });
        </script>
    @endpush
    @php
        $whatsNewVersion = config('app.version');
        $whatsNewRelease = $whatsNewVersion
            ? (config('whatsnew.releases')[$whatsNewVersion] ?? null)
            : null;
    @endphp

    @if ($whatsNewRelease)
        @php
            $whatsNewImageUrl = $whatsNewRelease['image']
                ? asset($whatsNewRelease['image'])
                : null;
        @endphp
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
                        <h2 id="whatsNewTitle" class="modal-title">Version {{ $whatsNewVersion }}</h2>
                        @if (!empty($whatsNewRelease['date']))
                            <p class="modal-text">Released {{ $whatsNewRelease['date'] }}</p>
                        @endif
                    </div>
                </div>
                <div class="modal-body whats-new-body">
                    @if ($whatsNewImageUrl)
                        <div class="whats-new-media">
                            <img
                                src="{{ $whatsNewImageUrl }}"
                                alt="{{ $whatsNewRelease['image_alt'] ?? 'Update preview' }}"
                                loading="lazy"
                            >
                        </div>
                    @endif
                    @if (!empty($whatsNewRelease['sections']))
                        <div class="whats-new-sections">
                            @foreach ($whatsNewRelease['sections'] as $section)
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
                    @endif
                </div>
                <div class="modal-actions">
                    <button class="btn btn-primary" type="button" data-whats-new-close>Got it!</button>
                </div>
            </div>
        </div>
    @endif

</x-app-layout>
