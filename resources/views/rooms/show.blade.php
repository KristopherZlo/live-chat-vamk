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
    @php
        $defaultQuickResponses = [
            'What was unclear in the last topic?',
            'Where should we start the lecture?',
            'Any questions at this point?',
        ];
    @endphp
    @php
        $repliesByParent = $messages->groupBy('reply_to_id');
        $myReplyThreads = $messages->filter(function ($message) use ($repliesByParent, $currentUserId, $participant) {
            $isMine = ($currentUserId && $message->user_id === $currentUserId)
                || ($participant && $message->participant && $message->participant->id === $participant->id);
            return $isMine && ($repliesByParent[$message->id] ?? collect())->isNotEmpty();
        })->map(function ($message) use ($repliesByParent) {
            return [
                'parent' => $message,
                'replies' => $repliesByParent[$message->id] ?? collect(),
            ];
        })->values();
        $buildReplyBranch = function ($message) use (&$buildReplyBranch, $repliesByParent) {
            $children = $repliesByParent[$message->id] ?? collect();
            $childBranches = $children->map(fn ($child) => $buildReplyBranch($child))->values();
            $descendants = $childBranches->reduce(
                fn ($carry, $branch) => $carry + 1 + ($branch['reply_count'] ?? 0),
                0
            );
            return [
                'id' => $message->id,
                'author' => $message->user?->name ?? $message->participant?->display_name ?? 'Guest',
                'time' => $message->created_at?->format('H:i'),
                'content' => $message->content,
                'is_question' => (bool) $message->question,
                'reply_count' => $descendants,
                'replies' => $childBranches,
            ];
        };
        $myReplyThreadsData = $myReplyThreads->map(function ($thread) use ($buildReplyBranch, $repliesByParent) {
            $parent = $thread['parent'];
            $children = $repliesByParent[$parent->id] ?? collect();
            $childBranches = $children->map(fn ($reply) => $buildReplyBranch($reply))->values();
            $descendants = $childBranches->reduce(
                fn ($carry, $branch) => $carry + 1 + ($branch['reply_count'] ?? 0),
                0
            );
            return [
                'parent' => [
                    'id' => $parent->id,
                    'author' => $parent->user?->name ?? $parent->participant?->display_name ?? 'Guest',
                    'time' => $parent->created_at?->format('H:i'),
                    'content' => $parent->content,
                    'is_question' => (bool) $parent->question,
                ],
                'replies' => $childBranches,
                'reply_count' => $descendants,
            ];
        })->values();
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
        data-last-visited-room
        data-room-slug="{{ $room->slug }}"
        data-room-title="{{ $room->title ?? 'Untitled room' }}"
        data-room-description="{{ $room->description ?? '' }}"
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
      @else
        <button class="mobile-tab-btn" data-tab-target="questions">My questions</button>
      @endif
      <button class="mobile-tab-btn mobile-tab-more" type="button" id="mobileMenuTabsBtn">
        <i data-lucide="more-horizontal"></i>
        <span>More</span>
      </button>
    </nav>

        <div id="layoutRoot" class="layout {{ $isOwner ? 'teacher' : '' }}">
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

                <div class="chat-subtabs" data-chat-tabs>
                    <button class="chat-tab-btn active" type="button" data-chat-tab="chat">
                        <span>Chat</span>
                    </button>
                    <button class="chat-tab-btn" type="button" data-chat-tab="replies">
                        <span>Replies</span>
                        <span class="pill-soft" data-replies-unread hidden>0</span>
                    </button>
                    @if($isOwner)
                        <button class="chat-tab-btn" type="button" data-chat-tab="bans" data-onboarding-target="bans-tab">
                            <span>Bans</span>
                            <span class="pill-soft">{{ $bannedParticipants->count() }}</span>
                        </button>
                    @endif
                </div>

                <div class="chat-pane" data-chat-panel="chat" data-onboarding-target="chat-pane">
                    <ol
                        class="chat-messages messages-container"
                        id="chatMessages"
                        data-history-url="{{ $messagesHistoryUrl ?? '' }}"
                        data-has-more="{{ !empty($messagesHasMore) ? '1' : '0' }}"
                        data-oldest-id="{{ $messagesOldestId ?? '' }}"
                        data-page-size="{{ $messagePageSize ?? 50 }}"
                    >
                        <li
                            class="message message-loader"
                            data-messages-loader
                            hidden
                            style="text-align:center; padding:12px; color:var(--muted, #6b7280);"
                        >
                            <div
                                data-messages-loader-text
                                style="display:inline-block; padding:8px 12px; border:1px solid #e5e7eb; border-radius:12px; background:#f8fafc; font-weight:600;"
                            >
                                Fetching previous messages...
                            </div>
                        </li>
                        @forelse($messages as $message)
                            @php
                                $isOwnerMessage = $message->user && $message->user_id === $room->user_id;
                                $authorName = $message->user?->name ?? $message->participant?->display_name ?? 'Guest';
                                $initials = \Illuminate\Support\Str::of($authorName)->substr(0, 2)->upper();
                                $isOutgoing = $isOwner ? $isOwnerMessage : ($participant && $message->participant && $message->participant->id === $participant->id);
                                $isQuestionMessage = (bool) $message->question;
                                $replyTo = $message->replyTo;
                                $replyDeleted = $replyTo?->trashed();
                                $deleteUrl = route('rooms.messages.destroy', [$room, $message]);
                                $canDeleteOwn = ($currentUserId && $message->user_id === $currentUserId)
                                    || ($participant && $message->participant && $participant->id === $message->participant->id);
                                $avatarBg = $avatarColor($authorName);
                                $usePrecomputedReactions = isset($reactionsByMessage) && is_array($reactionsByMessage);
                                $usePrecomputedMine = isset($myReactionsByMessage) && is_array($myReactionsByMessage);

                                if ($usePrecomputedReactions || $usePrecomputedMine) {
                                    $rawReactions = $usePrecomputedReactions ? ($reactionsByMessage[$message->id] ?? []) : [];
                                    $myReactions = $usePrecomputedMine ? ($myReactionsByMessage[$message->id] ?? []) : [];
                                    $myReactionSet = array_fill_keys($myReactions, true);
                                    $reactionsGrouped = collect($rawReactions)->map(function ($reaction) use ($myReactionSet) {
                                        $emoji = $reaction['emoji'] ?? '';
                                        return [
                                            'emoji' => $emoji,
                                            'count' => (int) ($reaction['count'] ?? 0),
                                            'reacted' => $emoji !== '' && isset($myReactionSet[$emoji]),
                                        ];
                                    })->values();
                                } else {
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
                                }
                            @endphp
                            <li
                                class="message {{ $isOutgoing ? 'message--outgoing' : '' }} {{ $isQuestionMessage ? 'message--question' : '' }}"
                                data-message-id="{{ $message->id }}"
                                data-reactions-url="{{ route('rooms.messages.reactions.toggle', [$room, $message]) }}"
                                data-reactions='@json($reactionsGrouped)'
                                data-my-reactions='@json($myReactions)'
                                data-user-id="{{ $message->user_id ?? '' }}"
                                data-participant-id="{{ $message->participant_id ?? '' }}"
                                data-author="{{ e($authorName) }}"
                                data-content="{{ e($message->content) }}"
                                data-reply-to="{{ $replyTo?->id ?? '' }}"
                                data-delete-url="{{ $deleteUrl }}"
                                data-question="{{ $isQuestionMessage ? '1' : '0' }}"
                                data-time="{{ $message->created_at?->format('H:i') }}"
                                data-created="{{ $message->created_at?->toIso8601String() }}"
                            >
                                <div class="message-avatar colorized" style="background: {{ $avatarBg }}; color: #fff; border-color: transparent;">{{ $initials }}</div>
                                <div class="message-body">
                                    @if($isOwner && $message->participant && !$isOwnerMessage)
                                        <div class="message-admin-actions">
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
                                            <form
                                                method="POST"
                                                action="{{ $deleteUrl }}"
                                                class="message-delete-form"
                                                data-message-delete
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="message-delete-btn" title="Delete message">
                                                    <i data-lucide="trash-2"></i>
                                                </button>
                                            </form>
                                        </div>
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
                                            $replyText = $replyDeleted ? 'Message deleted' : \Illuminate\Support\Str::limit($replyTo->content, 120);
                                        @endphp
                                        <div class="message-reply" data-reply-target="{{ $replyTo->id }}" data-reply-deleted="{{ $replyDeleted ? '1' : '0' }}">
                                            <span class="reply-author">{{ $replyAuthor }}</span>
                                            <span class="reply-text">{{ $replyText }}</span>
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
                                        @if($canDeleteOwn || ($isOwner && $isOwnerMessage))
                                            <button
                                                type="button"
                                                class="msg-action msg-action-delete"
                                                data-message-delete-trigger
                                                data-message-id="{{ $message->id }}"
                                                data-delete-url="{{ $deleteUrl }}"
                                            >
                                                <i data-lucide="trash-2"></i>
                                                <span>Delete</span>
                                            </button>
                                        @endif
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
                            <span class="panel-subtitle chat-hint">Press Enter to send, Shift+Enter for a new line</span>
                            @if($isOwner)
                                <div class="quick-responses" data-quick-responses data-default-responses='@json($defaultQuickResponses)'>
                                    <div
                                        class="quick-responses__buttons"
                                        role="toolbar"
                                        aria-label="Quick responses"
                                        data-quick-responses-buttons
                                    ></div>
                                    <button
                                        type="button"
                                        class="quick-responses__settings"
                                        data-quick-responses-settings
                                        aria-label="Open quick responses settings"
                                    >
                                        <i data-lucide="settings"></i>
                                    </button>
                                </div>
                                <div
                                    class="quick-responses-modal"
                                    id="quickResponsesModal"
                                    data-quick-responses-modal
                                    hidden
                                    aria-hidden="true"
                                    role="dialog"
                                    aria-modal="true"
                                >
                                    <div class="modal-card">
                                        <button
                                            type="button"
                                            class="modal-close"
                                            data-quick-responses-modal-close
                                            aria-label="Close quick responses settings"
                                        >
                                            <i data-lucide="x"></i>
                                        </button>
                                        <h3>Quick responses</h3>
                                        <p class="panel-subtitle">Save up to three canned replies that you can send with one tap.</p>
                                        <form class="quick-responses-modal-form" data-quick-responses-form>
                                            <div class="quick-responses-modal-list" data-quick-responses-list></div>
                                            <div class="quick-responses-modal-actions">
                                                <button type="button" class="btn btn-sm btn-ghost" data-quick-response-add>
                                                    Add response
                                                </button>
                                            </div>
                                            <div class="modal-actions">
                                                <button type="button" class="btn btn-ghost" data-quick-responses-modal-close>Cancel</button>
                                                <button type="submit" class="btn btn-primary">Save</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endif
                            <form id="chat-form" method="POST" action="{{ route('rooms.messages.store', $room) }}">
                                @csrf
                                <div class="chat-send-options">
                                    @unless($isOwner)
                                        <label class="switch" id="sendToTeacherSwitch">
                                            <input type="checkbox" name="as_question" value="1" id="sendToTeacher">
                                            <span class="switch-track">
                                              <span class="switch-thumb"></span>
                                            </span>
                                            <span class="switch-label">Send as a question</span>
                                        </label>
                                    @endunless
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
                <div class="chat-pane" data-chat-panel="replies" hidden>
                    <div class="replies-layout">
                        <div class="replies-sidebar">
                            <div class="replies-sidebar-head">
                                <div class="panel-title">Replies to your messages</div>
                                <div class="panel-subtitle">Pick a message to open its thread.</div>
                            </div>
                            <ol class="reply-inbox" data-reply-inbox>
                                @forelse($myReplyThreadsData as $thread)
                                    @php
                                        $parent = $thread['parent'];
                                        $replyCount = $thread['reply_count'];
                                    @endphp
                                    <li
                                        class="reply-inbox-item"
                                        data-reply-thread-trigger
                                        data-reply-parent="{{ $parent['id'] }}"
                                        data-reply-thread='@json($thread)'
                                        data-reply-count="{{ $replyCount }}"
                                        data-reply-unread="0"
                                    >
                                        <div class="reply-inbox-top">
                                            <span class="reply-inbox-author">{{ $parent['author'] }}</span>
                                            <div class="reply-inbox-meta-row">
                                                @if($parent['is_question'])
                                                    <span class="message-badge message-badge-question">To host</span>
                                                @endif
                                                @if($parent['time'])
                                                    <span class="reply-inbox-time">{{ $parent['time'] }}</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="reply-inbox-text">{{ \Illuminate\Support\Str::limit($parent['content'], 180) }}</div>
                                        <div class="reply-inbox-meta">
                                            <span class="pill-soft">{{ $replyCount }}</span>
                                            <span class="reply-inbox-meta-label">{{ \Illuminate\Support\Str::plural('reply', $replyCount) }}</span>
                                            <span class="reply-inbox-new" aria-hidden="true"></span>
                                        </div>
                                    </li>
                                @empty
                                    <li class="reply-inbox-empty">
                                        <div class="message-text">No replies yet.</div>
                                    </li>
                                @endforelse
                            </ol>
                        </div>
                        <div class="replies-detail" data-reply-detail hidden>
                            <div class="reply-detail-empty" data-replies-empty>
                                <div class="reply-detail-illustration">&#128172;</div>
                                <div class="reply-detail-empty-text">
                                    <div class="panel-title">Open a thread</div>
                                    <div class="panel-subtitle">Select any message on the left to view every reply and nested conversations.</div>
                                </div>
                            </div>
                            <div class="reply-detail-body" data-reply-thread-view hidden></div>
                        </div>
                    </div>
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
        <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/emoji-picker-element@1.27.0/themes/light.css">
    @endpush

    @push('scripts')
        <script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@1.27.0/index.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (window.__chatPageBound) return;
                window.__chatPageBound = true;
                const roomSlug = @json($room->slug);
                const isOwnerUser = @json($isOwner);
                const currentUserId = @json(auth()->id());
                const currentParticipantId = @json($participant?->id);
                const currentUserName = @json(auth()->user()?->name ?? $participant?->display_name ?? 'Guest');
                const currentParticipantName = @json($participant?->display_name ?? 'Guest');
                const publicLink = @json($publicLink);
                const queueSoundUrl = @json($queueSoundUrl);
                window.queueSoundUrl = queueSoundUrl;
                const messagesHistoryUrl = @json($messagesHistoryUrl);
                const messagesHasMoreInitial = @json($messagesHasMore ?? false);
                const messagesOldestId = @json($messagesOldestId);
                const messagesPageSize = @json($messagePageSize ?? 50);
                const queueItemUrlTemplate = @json($queueItemUrlTemplate ?? null);
                const queueItemsBatchUrl = @json($isOwner ? route('rooms.questions.batch', $room) : null);
                const queueChunkUrl = @json($isOwner ? route('rooms.questions.chunk', $room) : null);
                const queuePageSize = Number(@json($queuePageSize ?? 50));
                const questionsPanel = document.getElementById('questions-panel');
                const questionsPanelUrl = @json(route('rooms.questionsPanel', $room));
                const myQuestionsPanel = document.getElementById('myQuestionsPanel');
                const myQuestionsPanelUrl = @json(route('rooms.myQuestionsPanel', $room));
                const banStoreUrl = @json(route('rooms.bans.store', $room));
                const rootWindow = window;
                const queueRenderedIds = new Set();
                const seedQueueRenderedIds = () => {
                    queueRenderedIds.clear();
                    const list = document.querySelector('#queuePanel .queue-list');
                    if (!list) return;
                    list.querySelectorAll('.queue-item[data-question-id]').forEach((item) => {
                        const parsed = normalizeId(item.dataset.questionId);
                        if (parsed) queueRenderedIds.add(parsed);
                    });
                };
                const playQueueSoundSafe = () => {
                    if (!queueSoundUrl) return;
                    console.debug('[queue-sound] playQueueSoundSafe start', {
                        url: queueSoundUrl,
                        hasApi: typeof window.playQueueSound === 'function',
                        enabled: typeof window.isQueueSoundEnabled === 'function' ? window.isQueueSoundEnabled() : true,
                    });
                    if (typeof window.isQueueSoundEnabled === 'function' && !window.isQueueSoundEnabled()) {
                        console.debug('[queue-sound] blocked by user setting');
                        return;
                    }
                    if (typeof window.initQueueSoundPlayer === 'function') {
                        try {
                            window.initQueueSoundPlayer(queueSoundUrl);
                        } catch (err) {
                            console.debug('[queue-sound] initQueueSoundPlayer error', err);
                        }
                    }
                    if (typeof window.playQueueSound === 'function') {
                        try {
                            window.playQueueSound(queueSoundUrl);
                        } catch (err) {
                            console.debug('[queue-sound] playQueueSound error', err);
                        }
                        return;
                    }
                    try {
                        const audio = new Audio(queueSoundUrl);
                        audio.currentTime = 0;
                        audio.play()
                            .then(() => console.debug('[queue-sound] fallback audio played'))
                            .catch((err) => console.debug('[queue-sound] fallback audio error', err));
                    } catch (err) {
                        console.debug('[queue-sound] playQueueSoundSafe fallback error', err);
                    }
                };
                let queuePipButton;
                const supportsDocumentPip = Boolean(window.documentPictureInPicture && window.documentPictureInPicture.requestWindow);
                let queuePipWindow = null;
                let queuePipSyncTimer = null;
                let queuePipStylesCloned = false;
                let questionsReloadInFlight = false;
                let questionsReloadPending = false;
                let questionsReloadTimeout = null;
                let lastQuestionsReloadAt = 0;
                const MIN_QUESTIONS_RELOAD_MS = 1200;
                const handleQueuePipClick = () => {
                    if (queuePipWindow && !queuePipWindow.closed) {
                        closeQueuePip();
                    } else {
                        openQueuePip();
                    }
                };
                const attachQueuePipButton = () => {
                    queuePipButton = document.querySelector('[data-queue-pip]');
                    if (!queuePipButton) {
                        return;
                    }
                    queuePipButton.removeEventListener('click', handleQueuePipClick);
                    queuePipButton.addEventListener('click', handleQueuePipClick);
                    if (!supportsDocumentPip) {
                        queuePipButton.title = 'Picture-in-picture is not fully supported in this browser.';
                    }
                };
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
                let queueHasMore = false;
                let queueFullyLoaded = false;
                let queueOffset = 0;
                let queueLoading = false;
                let queueActiveFilter = null;
                let queueInitialFilterHandled = false;
                const queueActionInflight = new Set();
                const getQueueBody = () => document.querySelector('#queuePanel .panel-body');
                const getQueueList = () => document.querySelector('#queuePanel .queue-list');
                const getQueueFilterSelect = () => document.querySelector('#queuePanel [data-queue-filter]');
                const getQueueFilterCount = (value) => {
                    const select = getQueueFilterSelect();
                    const target = (select && value) ? select.querySelector(`option[value="${value}"]`) : null;
                    const raw = target?.dataset?.count ?? target?.dataset?.statusCount;
                    const parsed = Number(raw);
                    return Number.isFinite(parsed) ? parsed : null;
                };
                const ensureQueueList = () => {
                    let list = getQueueList();
                    if (list) return list;
                    const body = getQueueBody();
                    if (!body) return null;
                    body.innerHTML = '';
                    list = document.createElement('ul');
                    list.className = 'queue-list';
                    list.dataset.queueHasMore = '0';
                    list.dataset.queueOffset = '0';
                    body.appendChild(list);

                    let filterEmpty = body.querySelector('[data-queue-filter-empty]');
                    if (!filterEmpty) {
                        filterEmpty = document.createElement('p');
                        filterEmpty.className = 'empty-state queue-filter-empty';
                        filterEmpty.dataset.queueFilterEmpty = '1';
                        filterEmpty.hidden = true;
                        filterEmpty.textContent = 'No questions in this filter.';
                        body.appendChild(filterEmpty);
                    }

                    let pagination = body.querySelector('.queue-pagination');
                    if (!pagination) {
                        pagination = document.createElement('div');
                        pagination.className = 'queue-pagination';
                        const loader = document.createElement('div');
                        loader.className = 'queue-loading';
                        loader.dataset.queueLoader = '1';
                        loader.hidden = true;
                        loader.setAttribute('role', 'status');
                        loader.setAttribute('aria-label', 'Loading');
                        loader.innerHTML = '<div class="loader-5" aria-hidden="true"><span></span></div>';
                        pagination.append(loader);
                        body.appendChild(pagination);
                    } else if (!pagination.querySelector('[data-queue-loader]')) {
                        const loader = document.createElement('div');
                        loader.className = 'queue-loading';
                        loader.dataset.queueLoader = '1';
                        loader.hidden = true;
                        loader.setAttribute('role', 'status');
                        loader.setAttribute('aria-label', 'Loading');
                        loader.innerHTML = '<div class="loader-5" aria-hidden="true"><span></span></div>';
                        pagination.append(loader);
                    }

                    return list;
                };
                const buildQueueItemUrl = (questionId) => {
                    const id = normalizeId(questionId);
                    if (!id || !queueItemUrlTemplate) return null;
                    return queueItemUrlTemplate.replace('__QUESTION__', String(id));
                };
                const fetchQueueItemHtml = async (questionId) => {
                    const url = buildQueueItemUrl(questionId);
                    if (!url) return null;
                    const response = await fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) return null;
                    return response.text();
                };
                const fetchQueueItemsBatch = async (ids = []) => {
                    if (!queueItemsBatchUrl || !ids.length) return [];
                    const url = new URL(queueItemsBatchUrl, window.location.origin);
                    url.searchParams.set('ids', ids.join(','));
                    const response = await fetch(url.toString(), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }
                    const payload = await response.json();
                    return Array.isArray(payload.items) ? payload.items : [];
                };
                const updateQueueEmptyState = () => {
                    const list = getQueueList();
                    const body = getQueueBody();
                    const emptyState = body?.querySelector('[data-queue-filter-empty]');
                    if (!emptyState) return;
                    const hasItems = Boolean(list?.querySelector('.queue-item:not([hidden])'));
                    const shouldShowEmpty = !hasItems && !queueLoading && !queueHasMore;
                    emptyState.hidden = !shouldShowEmpty;
                    if (list) {
                        list.classList.toggle('queue-list-filter-empty', shouldShowEmpty);
                    }
                };
                const setQueueHasMore = (hasMore) => {
                    queueHasMore = Boolean(hasMore);
                    const list = getQueueList();
                    if (list) {
                        list.dataset.queueHasMore = queueHasMore ? '1' : '0';
                    }
                    updateQueueEmptyState();
                };
                const updateQueueOffsets = () => {
                    const list = getQueueList();
                    if (!list) {
                        queueOffset = 0;
                        return;
                    }
                    const count = list.querySelectorAll('.queue-item').length;
                    queueOffset = count;
                    list.dataset.queueOffset = String(count);
                };
                const pruneQueueListToFilter = (filter) => {
                    const list = getQueueList();
                    if (!list || !filter || filter === 'all') return;
                    const items = Array.from(list.querySelectorAll('.queue-item'));
                    items.forEach((item) => {
                        const status = (item.dataset.status || '').toLowerCase();
                        item.hidden = status !== filter;
                    });
                    updateQueueOffsets();
                    updateQueueEmptyState();
                };
                const syncQueueStateFromDom = (filter = getQueueFilterValue()) => {
                    queueActiveFilter = filter || queueActiveFilter || 'all';
                    updateQueueOffsets();
                    updateQueueFilterCounts();
                    applyFilterToQueue(queueActiveFilter);
                    setQueueHasMore(false);
                    updateQueueEmptyState();
                };
                const resetQueueForFilter = (filter) => {
                    queueActiveFilter = filter || 'all';
                    const list = ensureQueueList();
                    if (list) {
                        list.dataset.queueHasMore = '0';
                        list.dataset.queueOffset = String(list.querySelectorAll('.queue-item').length);
                    }
                    queueLoading = false;
                    setQueueHasMore(false);
                    updateQueueOffsets();
                    updateQueueEmptyState();
                };
                const upsertQueueItemFromHtml = (questionId, html) => {
                    const list = ensureQueueList();
                    if (!list || !html) return;
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = html;
                    const item = wrapper.querySelector('.queue-item');
                    if (!item) return;
                    const id = normalizeId(questionId) || normalizeId(item.dataset.questionId);
                    const status = (item.dataset.status || '').toLowerCase();
                    const existing = id ? list.querySelector(`.queue-item[data-question-id="${id}"]`) : null;
                    const isNewId = id && !queueRenderedIds.has(id);
                    if (existing) {
                        existing.replaceWith(item);
                    } else {
                        list.appendChild(item);
                    }
                    if (id) {
                        queueRenderedIds.add(id);
                    }
                    if (isNewId && isOwnerUser) {
                        console.debug('[queue-sound] new queue item, triggering sound', {
                            id,
                            fromPayload: questionId,
                            status,
                        });
                        playQueueSoundSafe();
                    }
                    bindQueueInteractions(list);
                    updateQueueOffsets();
                    if (queueActiveFilter && queueActiveFilter !== 'all' && queueActiveFilter !== status && status === 'new') {
                        queueNeedsNew = true;
                        if (typeof window.markQueueHasNew === 'function') {
                            window.markQueueHasNew();
                        }
                    }
                    updateQueueFilterCounts();
                    applyFilterToQueue(queueActiveFilter || getQueueFilterValue());
                };
                const removeQueueItem = (questionId) => {
                    const id = normalizeId(questionId);
                    if (!id) return false;
                    const list = getQueueList();
                    const item = list?.querySelector(`.queue-item[data-question-id="${id}"]`);
                    if (!item) return false;
                    item.remove();
                    updateQueueOffsets();
                    updateQueueFilterCounts();
                    applyFilterToQueue(queueActiveFilter || getQueueFilterValue());
                    updateQueueEmptyState();
                    return true;
                };
                const pendingQueueUpserts = new Set();
                let queueUpsertTimer = null;
                const QUEUE_BATCH_DELAY = 220;
                const flushQueueUpserts = async () => {
                    const ids = Array.from(pendingQueueUpserts);
                    pendingQueueUpserts.clear();
                    queueUpsertTimer = null;
                    if (!ids.length) return;
                    try {
                        let items = [];
                        if (queueItemsBatchUrl) {
                            items = await fetchQueueItemsBatch(ids);
                        } else {
                            items = await Promise.all(ids.map(async (id) => {
                                const html = await fetchQueueItemHtml(id);
                                return html ? { id, html } : null;
                            }));
                        }
                        const receivedIds = new Set();
                        (items || [])
                            .filter(Boolean)
                            .forEach((item) => {
                                const normalizedId = normalizeId(item.id);
                                if (normalizedId) {
                                    receivedIds.add(normalizedId);
                                }
                                upsertQueueItemFromHtml(item.id, item.html);
                            });
                        ids
                            .map((value) => normalizeId(value))
                            .filter((value) => value && !receivedIds.has(value))
                            .forEach((missingId) => {
                                removeQueueItem(missingId);
                            });
                    } catch (err) {
                        console.warn('Queue upsert failed, falling back to reload', err);
                        scheduleReloadQuestionsPanel();
                    }
                };
                const requestQueueItemRefresh = (questionId) => {
                    const id = normalizeId(questionId);
                    if (!id) return;
                    pendingQueueUpserts.add(id);
                    if (!queueUpsertTimer) {
                        queueUpsertTimer = window.setTimeout(flushQueueUpserts, QUEUE_BATCH_DELAY);
                    }
                };
                const upsertQueueItem = (questionId) => requestQueueItemRefresh(questionId);
                const setQueueLoader = (isLoading) => {
                    const loader = document.querySelector('#queuePanel [data-queue-loader]');
                    if (!loader) return;
                    loader.hidden = !isLoading;
                };
                const updateQueueFilterCounts = () => {
                    const select = getQueueFilterSelect();
                    const list = getQueueList();
                    if (!select || !list) return;
                    const counts = {
                        all: 0,
                        new: 0,
                        answered: 0,
                        ignored: 0,
                        later: 0,
                    };
                    list.querySelectorAll('.queue-item').forEach((item) => {
                        const status = (item.dataset.status || '').toLowerCase();
                        if (counts[status] !== undefined) {
                            counts[status] += 1;
                        }
                        counts.all += 1;
                    });
                    select.querySelectorAll('option').forEach((option) => {
                        const value = option.value.toLowerCase();
                        const label = option.textContent.replace(/\s+\(\d+\)\s*$/, '').trim();
                        if (value === 'all') {
                            option.dataset.count = String(counts.all);
                            option.textContent = `${label} (${counts.all})`;
                        } else if (counts[value] !== undefined) {
                            option.dataset.count = String(counts[value]);
                            option.textContent = `${label} (${counts[value]})`;
                        }
                    });
                    const badge = document.querySelector('.queue-count-badge');
                    if (badge) {
                        badge.textContent = `${counts.all} questions`;
                    }
                };

                const applyFilterToQueue = (filter) => {
                    const list = getQueueList();
                    if (!list) return;
                    const target = (filter || 'all').toLowerCase();
                    list.querySelectorAll('.queue-item').forEach((item) => {
                        const status = (item.dataset.status || '').toLowerCase();
                        const shouldShow = target === 'all' || status === target;
                        item.hidden = !shouldShow;
                    });
                    updateQueueEmptyState();
                };

                const appendQueueHtml = (html) => {
                    const list = ensureQueueList();
                    if (!list || !html) return 0;
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = html;
                    const items = Array.from(wrapper.querySelectorAll('.queue-item'));
                    if (!items.length) return 0;
                    const fragment = document.createDocumentFragment();
                    items.forEach((item) => {
                        const parsed = normalizeId(item.dataset.questionId);
                        if (parsed) {
                            queueRenderedIds.add(parsed);
                        }
                        fragment.appendChild(item);
                    });
                    list.appendChild(fragment);
                    updateQueueOffsets();
                    updateQueueFilterCounts();
                    applyFilterToQueue(queueActiveFilter || getQueueFilterValue());
                    return items.length;
                };
                const getQueueFilterValue = () => {
                    const select = document.querySelector('#queuePanel [data-queue-filter]');
                    return (select?.value || 'all').toLowerCase();
                };
                const isQueueNearBottom = () => {
                    const body = getQueueBody();
                    if (!body) return false;
                    const nearBottom = body.scrollTop + body.clientHeight >= body.scrollHeight - 140;
                    const notScrollable = body.scrollHeight <= body.clientHeight + 20;
                    return nearBottom || notScrollable;
                };
                const maybeAutoloadQueue = () => {
                    if (queueFullyLoaded || queueLoading) {
                        updateQueueEmptyState();
                        return;
                    }
                    loadAllQueueItems();
                };
                const loadMoreQueueItems = async (offset) => {
                    const list = ensureQueueList();
                    if (!queueChunkUrl || !list) return { added: 0, hasMore: false, nextOffset: offset };
                    const url = new URL(queueChunkUrl, window.location.origin);
                    url.searchParams.set('offset', offset);
                    url.searchParams.set('limit', String(queuePageSize));
                    const response = await fetch(url.toString(), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }
                    const payload = await response.json();
                    const added = appendQueueHtml(payload.html);
                    const nextOffset = payload.next_offset ?? (offset + added);
                    return { added, hasMore: Boolean(payload.has_more), nextOffset };
                };

                const loadAllQueueItems = async () => {
                    if (queueLoading || queueFullyLoaded) return;
                    const list = ensureQueueList();
                    if (!list) return;
                    queueLoading = true;
                    setQueueLoader(true);
                    try {
                        let offset = Number(list.dataset.queueOffset || queueOffset || list.children.length || 0);
                        let hasMore = true;
                        while (hasMore) {
                            const result = await loadMoreQueueItems(offset);
                            offset = result.nextOffset ?? offset;
                            hasMore = result.hasMore && result.added > 0;
                            queueOffset = offset;
                            list.dataset.queueOffset = String(offset);
                        }
                        setQueueHasMore(false);
                        queueFullyLoaded = true;
                        applyFilterToQueue(queueActiveFilter || getQueueFilterValue());
                        updateQueueFilterCounts();
                    } catch (error) {
                        console.error('Failed to load all queue items', error);
                        setQueueHasMore(false);
                    } finally {
                        queueLoading = false;
                        setQueueLoader(false);
                        updateQueueEmptyState();
                    }
                };
                const handleQueueFilterChange = (value, meta = {}) => {
                    const filter = (value || 'all').toLowerCase();
                    const isInitial = Boolean(meta.initial);
                    if (isInitial && queueInitialFilterHandled && filter === queueActiveFilter) {
                        return;
                    }
                    if (isInitial) {
                        queueInitialFilterHandled = true;
                    }
                    queueActiveFilter = filter;
                    applyFilterToQueue(filter);
                    updateQueueEmptyState();
                };
                window.maybeAutoloadQueue = maybeAutoloadQueue;
                  const qrButton = document.getElementById('qrButton');
                  const qrOverlay = document.getElementById('qrOverlay');
                  const qrClose = document.getElementById('qrClose');
                  const qrCanvas = document.getElementById('qrCanvas');
                const chatContainer = document.querySelector('.messages-container');
                const chatMessagesList = document.getElementById('chatMessages');
                const messagesLoader = document.querySelector('[data-messages-loader]');
                const messagesLoaderText = document.querySelector('[data-messages-loader-text]');
                const messagePageLimit = Number(messagesPageSize) || Number(chatMessagesList?.dataset?.pageSize || '0') || 50;
                const messagesHistoryEndpoint = (messagesHistoryUrl || chatMessagesList?.dataset?.historyUrl || '').toString();
                let messagesHasMore = typeof messagesHasMoreInitial === 'boolean'
                    ? messagesHasMoreInitial
                    : (chatMessagesList?.dataset?.hasMore === '1');
                let messagesOldestKnownId = normalizeId(messagesOldestId) || normalizeId(chatMessagesList?.dataset?.oldestId);
                let messagesLoading = false;
                const scrollChatToBottom = () => {
                    if (!chatContainer) return;
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                };
                const updateMessagesStateAttributes = () => {
                    if (!chatMessagesList) return;
                    chatMessagesList.dataset.oldestId = messagesOldestKnownId ? String(messagesOldestKnownId) : '';
                    chatMessagesList.dataset.hasMore = messagesHasMore ? '1' : '0';
                };
                const setMessagesLoader = (isLoading) => {
                    if (messagesLoader) {
                        messagesLoader.hidden = !isLoading && !messagesHasMore;
                    }
                    if (messagesLoaderText) {
                        messagesLoaderText.textContent = isLoading
                            ? 'Fetching previous messages...'
                            : 'More messages available';
                    }
                };
                const updateMessagesLoadMoreVisibility = () => {};
                const loadOlderMessages = async () => {
                    if (!chatContainer || !messagesHistoryEndpoint || messagesLoading || !messagesHasMore) return;
                    if (!messagesOldestKnownId) {
                        const first = chatContainer.querySelector('.message[data-message-id]');
                        const fallbackId = normalizeId(first?.dataset?.messageId);
                        if (fallbackId) {
                            messagesOldestKnownId = fallbackId;
                            updateMessagesStateAttributes();
                        } else {
                            messagesHasMore = false;
                            updateMessagesStateAttributes();
                            setMessagesLoader(false);
                            return;
                        }
                    }
                    messagesLoading = true;
                    setMessagesLoader(true);
                    const prevHeight = chatContainer.scrollHeight;
                    const prevTop = chatContainer.scrollTop;
                    try {
                        const url = new URL(messagesHistoryEndpoint, window.location.origin);
                        url.searchParams.set('before_id', messagesOldestKnownId);
                        url.searchParams.set('limit', String(messagePageLimit));
                        const response = await fetch(url.toString(), {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}`);
                        }
                        const payload = await response.json();
                        const items = Array.isArray(payload.data) ? payload.data : [];
                        if (!items.length) {
                            messagesHasMore = false;
                            updateMessagesStateAttributes();
                            updateMessagesLoadMoreVisibility();
                            return;
                        }
                        const fragment = document.createDocumentFragment();
                        items.forEach((item) => {
                            const node = createMessageElement(item, { pending: false, allowBan: true });
                            fragment.appendChild(node);
                        });
                        const loaderAnchor = chatMessagesList?.querySelector('[data-messages-loader]');
                        if (loaderAnchor && loaderAnchor.parentNode === chatContainer) {
                            loaderAnchor.after(fragment);
                        } else {
                            chatContainer.prepend(fragment);
                        }
                        bindBanForms(chatContainer);
                        bindDeleteForms(chatContainer);
                        bindDeleteTriggers(chatContainer);
                        if (window.refreshLucideIcons) {
                            window.refreshLucideIcons(chatContainer);
                        }
                        removeEmptyMessageState();
                        const newHeight = chatContainer.scrollHeight;
                        chatContainer.scrollTop = newHeight - (prevHeight - prevTop);
                        messagesOldestKnownId = normalizeId(items[0]?.id) || messagesOldestKnownId;
                        messagesHasMore = Boolean(payload.has_more);
                        updateMessagesStateAttributes();
                        updateMessagesLoadMoreVisibility();
                    } catch (error) {
                        console.error('Failed to load older messages', error);
                    } finally {
                        messagesLoading = false;
                        setMessagesLoader(false);
                        requestAnimationFrame(() => maybeLoadOlderMessages());
                    }
                };
                const isChatNearTop = () => chatContainer && chatContainer.scrollTop <= 80;
                const isChatNotScrollable = () => chatContainer
                    && chatContainer.scrollHeight <= chatContainer.clientHeight + 32;
                const maybeLoadOlderMessages = (force = false) => {
                    if (!chatContainer || messagesLoading || !messagesHasMore) return;
                    if (force || isChatNearTop() || isChatNotScrollable()) {
                        loadOlderMessages();
                    }
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
                const setEmojiToggleState = (isActive) => {
                    if (!chatEmojiToggle) return;
                    chatEmojiToggle.classList.toggle('is-active', Boolean(isActive));
                };
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
                const replyInbox = document.querySelector('[data-reply-inbox]');
                const replyDetail = document.querySelector('[data-reply-detail]');
                const replyDetailBody = document.querySelector('[data-reply-thread-view]');
                const replyDetailEmpty = document.querySelector('[data-replies-empty]');
                const repliesPane = document.querySelector('[data-chat-panel=\"replies\"]');
                const repliesLayout = document.querySelector('.replies-layout');
                const repliesMobileQuery = window.matchMedia('(max-width: 720px)');
                const isRepliesMobile = () => repliesMobileQuery.matches;
                const repliesBadge = document.querySelector('[data-replies-unread]');
                const replyThreadsState = new Map();
                let activeReplyParentId = null;
                let chatActiveTab = 'chat';
                const csrfMeta = document.querySelector('meta[name=\"csrf-token\"]');
                const csrfToken = csrfMeta?.getAttribute('content') || '';
                const replyToInput = document.getElementById('replyToId');
                const replyPreview = document.getElementById('replyPreview');
                const replyPreviewAuthor = document.getElementById('replyPreviewAuthor');
                const replyPreviewText = document.getElementById('replyPreviewText');
                const replyPreviewCancel = document.getElementById('replyPreviewCancel');
                const avatarPalette = ['#2563eb', '#0ea5e9', '#6366f1', '#8b5cf6', '#14b8a6', '#f97316', '#f59e0b', '#10b981', '#ef4444'];
                const reactionUrlTemplate = @json(route('rooms.messages.reactions.toggle', [$room, '__MESSAGE__']));
                const deleteUrlTemplate = @json(route('rooms.messages.destroy', [$room, '__MESSAGE__']));
                const popularReactions = @json($popularReactions);
                const deletedMessageText = 'Message deleted';
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
                const showThrottleNotice = (text) => {
                    const message = text || 'You are doing that too quickly. Please slow down.';
                    if (typeof window.showFlashNotification === 'function') {
                        window.showFlashNotification(message, { type: 'warning', source: 'throttle-alert', duration: 4800 });
                        return;
                    }
                    const host = chatInputWrapper?.parentElement || document.querySelector('[data-chat-panel="chat"]') || document.body;
                    const existing = host.querySelector('.flash[data-throttle-alert]');
                    if (existing) {
                        existing.remove();
                    }
                    const flash = document.createElement('div');
                    flash.className = 'flash flash-warning';
                    flash.dataset.throttleAlert = '1';
                    flash.innerHTML = `
                        <span>${escapeHtml(message)}</span>
                        <button class="icon-btn flash-close" type="button" data-throttle-close aria-label="Close">
                            <i data-lucide="x"></i>
                        </button>
                    `;
                    host.prepend(flash);
                    const closer = flash.querySelector('[data-throttle-close]');
                    if (closer) {
                        closer.addEventListener('click', () => flash.remove());
                    }
                    window.setTimeout(() => {
                        flash.remove();
                    }, 4800);
                    if (window.refreshLucideIcons) {
                        window.refreshLucideIcons();
                    }
                };
                const removeEmptyMessageState = () => {
                    if (!chatContainer) return;
                    const empty = chatContainer.querySelector('.message-empty');
                    if (empty) {
                        empty.remove();
                    }
                };
                const flashMessage = (() => {
                    const timers = new WeakMap();
                    return (el) => {
                        if (!el) return;
                        const prev = timers.get(el);
                        if (prev) {
                            clearTimeout(prev);
                        }
                        el.classList.add('message--flash');
                        const timer = window.setTimeout(() => {
                            el.classList.remove('message--flash');
                            timers.delete(el);
                        }, 1500);
                        timers.set(el, timer);
                    };
                })();
                const scrollToMessage = (id) => {
                    if (!id || !chatContainer) return null;
                    const target = chatContainer.querySelector(`.message[data-message-id="${id}"]`);
                    if (!target) return null;
                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    flashMessage(target);
                    return target;
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
                const isMineMessageEl = (el) => {
                    if (!el) return false;
                    const userId = normalizeId(el.dataset.userId);
                    const participantId = normalizeId(el.dataset.participantId);
                    if (currentUserId && userId && Number(currentUserId) === userId) return true;
                    if (currentParticipantId && participantId && Number(currentParticipantId) === participantId) return true;
                    return false;
                };
                const isMineMessageData = (data) => {
                    if (!data) return false;
                    const userId = normalizeId(data.user_id ?? data.userId);
                    const participantId = normalizeId(data.participant_id ?? data.participantId);
                    if (currentUserId && userId && Number(currentUserId) === userId) return true;
                    if (currentParticipantId && participantId && Number(currentParticipantId) === participantId) return true;
                    return false;
                };
                const countDescendants = (list = []) => list.reduce((acc, node) => acc + 1 + countDescendants(node?.replies || []), 0);
                const formatTime = (value) => {
                    if (!value) return '';
                    const date = new Date(value);
                    if (Number.isNaN(date.getTime())) return '';
                    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                };
                const truncateText = (value, max = 180) => {
                    const str = String(value ?? '');
                    if (str.length <= max) return str;
                    return `${str.slice(0, max)}...`;
                };
                const isMyMessage = (messageEl) => {
                    if (!messageEl) return false;
                    const userId = normalizeId(messageEl.dataset.userId);
                    const participantId = normalizeId(messageEl.dataset.participantId);
                    if (currentUserId && userId && Number(currentUserId) === userId) return true;
                    if (currentParticipantId && participantId && Number(currentParticipantId) === participantId) return true;
                    return false;
                };
                const getMessageElById = (id) => {
                    if (!id || !chatContainer) return null;
                    return chatContainer.querySelector(`.message[data-message-id="${id}"]`);
                };
                const threadContainsId = (state, targetId) => {
                    if (!state || !targetId) return false;
                    const target = String(targetId);
                    if (String(state.parent?.id) === target) return true;
                    const walk = (list = []) => {
                        for (const child of list) {
                            if (String(child.id) === target) return true;
                            if (walk(child.replies || [])) return true;
                        }
                        return false;
                    };
                    return walk(state.replies || []);
                };
                const findThreadContainingId = (id) => {
                    if (!id) return null;
                    for (const state of replyThreadsState.values()) {
                        if (threadContainsId(state, id)) {
                            return state;
                        }
                    }
                    return null;
                };
                const getParentDataFromMessageEl = (el) => {
                    if (!el) return null;
                    return {
                        id: el.dataset.messageId,
                        author: el.dataset.author || el.querySelector('.message-author')?.textContent?.trim() || 'Guest',
                        time: el.dataset.time || formatTime(el.dataset.created),
                        content: el.dataset.content || el.querySelector('.message-text')?.textContent?.trim() || '',
                        is_question: el.dataset.question === '1',
                    };
                };
                const updateRepliesBadge = () => {
                    if (!repliesBadge) return;
                    const total = Array.from(replyThreadsState.values()).reduce((sum, thread) => sum + (Number(thread.unread) || 0), 0);
                    if (total > 0) {
                        repliesBadge.textContent = total;
                        repliesBadge.hidden = false;
                        repliesBadge.removeAttribute('hidden');
                    } else {
                        repliesBadge.textContent = '0';
                        repliesBadge.hidden = true;
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
                const findReplyRootData = (startId) => {
                    if (!startId) return null;
                    let current = getMessageElById(startId);
                    let last = current;
                    let safety = 0;
                    while (current && current.dataset.replyTo && safety < 20) {
                        const parentId = current.dataset.replyTo;
                        const parentEl = getMessageElById(parentId);
                        if (!parentEl) break;
                        last = parentEl;
                        current = parentEl;
                        safety += 1;
                    }
                    return last ? getParentDataFromMessageEl(last) : null;
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
                    const isTempMessage = String(messageId).startsWith('temp-');
                    const authorUserId = normalizeId(author?.user_id);
                    const authorParticipantId = normalizeId(author?.participant_id);
                    const messageKey = makeMessageKey(content, authorUserId, authorParticipantId, replyTo?.id, asQuestion);
                    container.classList.add('message');
                    container.dataset.messageId = messageId;
                    container.dataset.reactionsUrl = reactionUrlTemplate && messageId ? reactionUrlTemplate.replace('__MESSAGE__', messageId) : '';
                    container.dataset.reactions = JSON.stringify(reactions || []);
                    container.dataset.myReactions = JSON.stringify(myReactions || []);
                    container.dataset.replyTo = replyTo?.id ? String(replyTo.id) : '';
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
                    const replyDeleted = Boolean(replyTo?.is_deleted);
                    const replyContent = replyDeleted ? deletedMessageText : (replyTo?.content || '');
                    const replyTargetAttr = replyTo?.id ? ` data-reply-target="${escapeHtml(String(replyTo.id))}"` : '';
                    const replyDeletedAttr = replyDeleted ? ' data-reply-deleted="1"' : '';
                    const replyHtml = replyTo ? `<div class="message-reply"${replyTargetAttr}${replyDeletedAttr}><span class="reply-author">${escapeHtml(replyTo.author || 'Guest')}</span><span class="reply-text">${escapeHtml(replyContent)}</span></div>` : '';
                    const time = createdAt ? new Date(createdAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    const deleteUrl = (!isTempMessage && deleteUrlTemplate && messageId)
                        ? deleteUrlTemplate.replace('__MESSAGE__', messageId)
                        : '';
                    container.dataset.userId = authorUserId ?? '';
                    container.dataset.participantId = authorParticipantId ?? '';
                    container.dataset.author = authorNameRaw || 'Guest';
                    container.dataset.content = content || '';
                    container.dataset.deleteUrl = deleteUrl;
                    container.dataset.replyDeleted = replyDeleted ? '1' : '';
                    container.dataset.question = asQuestion ? '1' : '0';
                    container.dataset.created = createdAt || '';
                    container.dataset.time = time;
                    const canBan = allowBan && isOwnerUser && !isOwnerAuthor && author.participant_id;
                    const canAdminDelete = isOwnerUser && !isOwnerAuthor;
                    const canSelfDelete = (currentUserId && authorUserId && Number(currentUserId) === Number(authorUserId))
                        || (currentParticipantId && authorParticipantId && Number(currentParticipantId) === Number(authorParticipantId));
                    const canInlineDelete = (canSelfDelete || (isOwnerUser && isOwnerAuthor)) && (Boolean(deleteUrl) || pending);
                    const adminActionsHtml = (canBan || canAdminDelete) && deleteUrl ? `
                        <div class="message-admin-actions">
                            ${canBan ? `
                                <form method="POST" action="${banStoreUrl}" class="message-ban-form" data-ban-confirm="1">
                                    <input type="hidden" name="_token" value="${csrfToken}">
                                    <input type="hidden" name="participant_id" value="${author.participant_id}">
                                    <button type="submit" class="message-ban-btn" title="Ban participant">
                                        <i data-lucide="gavel"></i>
                                    </button>
                                </form>` : ''}
                            ${canAdminDelete ? `
                                <form method="POST" action="${deleteUrl}" class="message-delete-form" data-message-delete>
                                    <input type="hidden" name="_token" value="${csrfToken}">
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button type="submit" class="message-delete-btn" title="Delete message">
                                        <i data-lucide="trash-2"></i>
                                    </button>
                                </form>` : ''}
                        </div>
                    ` : '';
                    container.innerHTML = `
                        <div class="message-avatar colorized" style="background:${avatarColor}; color:#fff; border-color:transparent;">${initials}</div>
                        <div class="message-body">
                            ${adminActionsHtml}
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
                                ${canInlineDelete ? `
                                    <button type="button" class="msg-action msg-action-delete" data-message-delete-trigger data-message-id="${messageId}" data-delete-url="${deleteUrl}">
                                        <i data-lucide="trash-2"></i>
                                        <span>Delete</span>
                                    </button>` : ''}
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
                const updateMessageElementFromPayload = (element, payload, options = {}) => {
                    const { refreshIcons = true } = options;
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
                    if (refreshIcons && window.refreshLucideIcons) {
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
                    setEmojiToggleState(false);
                };
                const showEmojiPanel = (mode = 'input', targetMessage = null) => {
                    if (!chatEmojiPanel) return;
                    emojiPickerMode = mode;
                    reactionPickerTarget = targetMessage;
                    chatEmojiPanel.hidden = false;
                    chatEmojiPanel.classList.add('open');
                    setEmojiToggleState(mode === 'input');
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

                const deleteModal = (() => {
                    let overlay = null;
                    let confirmBtn = null;
                    let cancelBtn = null;
                    let resolver = null;

                    const ensureModal = () => {
                        if (overlay) return;
                        overlay = document.createElement('div');
                        overlay.className = 'modal-overlay';
                        overlay.hidden = true;
                        overlay.innerHTML = `
                            <div class="modal-dialog">
                                <div class="modal-header">
                                    <div class="modal-title-group">
                                        <div class="modal-eyebrow">Moderation</div>
                                        <div class="modal-title">Delete message?</div>
                                    </div>
                                    <button type="button" class="modal-close" data-delete-cancel aria-label="Close">
                                        <i data-lucide="x"></i>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <div class="modal-text">This action cannot be undone. Remove the message for everyone?</div>
                                </div>
                                <div class="modal-actions">
                                    <button type="button" class="btn btn-sm btn-ghost" data-delete-cancel>Cancel</button>
                                    <button type="button" class="btn btn-sm btn-danger" data-delete-confirm>Delete</button>
                                </div>
                            </div>
                        `;
                        document.body.appendChild(overlay);
                        confirmBtn = overlay.querySelector('[data-delete-confirm]');
                        cancelBtn = overlay.querySelectorAll('[data-delete-cancel]');

                        const close = (result = false) => {
                            overlay.classList.remove('show');
                            document.body.classList.remove('modal-open');
                            if (resolver) {
                                resolver(result);
                                resolver = null;
                            }
                            setTimeout(() => overlay.hidden = true, 120);
                        };

                        overlay.addEventListener('click', (event) => {
                            if (event.target === overlay) {
                                close(false);
                            }
                        });

                        cancelBtn.forEach((btn) => btn.addEventListener('click', () => close(false)));
                        confirmBtn?.addEventListener('click', () => close(true));
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

                const requestDeleteMessage = async (url) => {
                    const result = { ok: false, status: 0, message: '' };
                    if (!url) return result;
                    try {
                        const response = await fetch(url, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                        });
                        result.ok = response.ok;
                        result.status = response.status;
                        const payload = await response.json().catch(() => ({}));
                        result.message = payload?.message || '';
                        return result;
                    } catch (error) {
                        console.error('Delete message error', error);
                        return result;
                    }
                };

                const bindDeleteForms = (scope = document) => {
                    const forms = scope.querySelectorAll('form[data-message-delete]');
                    forms.forEach((form) => {
                        if (form.dataset.deleteBound === '1') return;
                        form.dataset.deleteBound = '1';
                        form.addEventListener('submit', async (event) => {
                            event.preventDefault();
                            const confirmed = await deleteModal.open();
                            if (!confirmed) return;
                            const url = form.getAttribute('action');
                            const { ok, status, message } = await requestDeleteMessage(url);
                            if (status === 429) {
                                showThrottleNotice(message || 'You are deleting messages too quickly. Please wait a moment.');
                                return;
                            }
                            if (ok) {
                                const messageEl = form.closest('.message');
                                handleMessageDeleted(messageEl?.dataset?.messageId || null);
                                return;
                            }
                            form.submit();
                        });
                    });
                };

                const bindDeleteTriggers = (scope = document) => {
                    const triggers = scope.querySelectorAll('[data-message-delete-trigger]');
                    triggers.forEach((btn) => {
                        if (btn.dataset.deleteTriggerBound === '1') return;
                        btn.dataset.deleteTriggerBound = '1';
                        btn.addEventListener('click', async (event) => {
                            event.preventDefault();
                            const url = btn.dataset.deleteUrl || btn.closest('.message')?.dataset.deleteUrl || '';
                            if (!url) return;
                            const confirmed = await deleteModal.open();
                            if (!confirmed) return;
                            const { ok, status, message } = await requestDeleteMessage(url);
                            if (status === 429) {
                                showThrottleNotice(message || 'You are deleting messages too quickly. Please wait a moment.');
                                return;
                            }
                            const messageId = btn.dataset.messageId || btn.closest('.message')?.dataset.messageId;
                            if (ok) {
                                handleMessageDeleted(messageId);
                            }
                        });
                    });
                };

                function setupChatTabs() {
                    if (!chatTabButtons.length || !chatPanes.length) return;
                    const current = Array.from(chatTabButtons).find((btn) => btn.classList.contains('active'));
                    if (current?.dataset.chatTab) {
                        chatActiveTab = current.dataset.chatTab;
                    }

                    chatTabButtons.forEach((btn) => {
                        btn.addEventListener('click', () => {
                            setChatTab(btn.dataset.chatTab || 'chat');
                        });
                    });

                    setChatTab(chatActiveTab || 'chat');
                }

                function syncRepliesDetailLayout() {
                    if (!repliesLayout) return;
                    const shouldOverlay = !replyDetail?.hidden && isRepliesMobile();
                    repliesLayout.classList.toggle('mobile-thread-open', shouldOverlay);
                }

                const hideReplyDetail = () => {
                    if (replyDetail) {
                        replyDetail.hidden = true;
                        replyDetail.classList.remove('is-open');
                    }
                    if (repliesLayout) {
                        repliesLayout.classList.add('is-collapsed');
                        repliesLayout.classList.remove('mobile-thread-open');
                    }
                    if (replyDetailBody) {
                        replyDetailBody.innerHTML = '';
                        replyDetailBody.hidden = true;
                    }
                    if (replyDetailEmpty) {
                        replyDetailEmpty.hidden = false;
                    }
                    activeReplyParentId = null;
                    if (replyInbox) {
                        replyInbox.querySelectorAll('.reply-inbox-item.is-active').forEach((item) => {
                            item.classList.remove('is-active');
                            item.removeAttribute('aria-current');
                        });
                    }
                    syncRepliesDetailLayout();
                };
                const setChatTab = (tabName = 'chat') => {
                    chatActiveTab = tabName;
                    chatTabButtons.forEach((btn) => {
                        const isActive = btn.dataset.chatTab === tabName;
                        btn.classList.toggle('active', isActive);
                    });
                    chatPanes.forEach((pane) => {
                        const isMatch = pane.dataset.chatPanel === tabName;
                        pane.hidden = !isMatch;
                    });
                    if (tabName !== 'replies') {
                        hideReplyDetail();
                    }
                };

                const syncReplyThreadCounts = (state) => {
                    if (!state) return 0;
                    const total = countDescendants(state.replies || []);
                    state.reply_count = total;
                    return total;
                };

                const buildReplyBranchFromPayload = (payload) => {
                    if (!payload) return null;
                    const replyToId = normalizeId(payload.reply_to_id || payload.reply_to?.id);
                    return {
                        id: payload.id,
                        author: payload.author?.name || payload.author || 'Guest',
                        time: formatTime(payload.created_at),
                        content: payload.content || '',
                        is_question: Boolean(payload.as_question || payload.question),
                        reply_to_id: replyToId,
                        replies: [],
                        reply_count: 0,
                    };
                };

                const insertReplyNode = (state, parentId, replyNode) => {
                    if (!state || !parentId || !replyNode) return false;
                    const parentStr = String(parentId);
                    const alreadyInTree = (list = []) => list.some((child) => {
                        if (String(child.id) === String(replyNode.id)) return true;
                        return alreadyInTree(child.replies || []);
                    });
                    if (alreadyInTree(state.replies || [])) return true;

                    const attach = (list = []) => {
                        for (const child of list) {
                            if (String(child.id) === parentStr) {
                                child.replies = child.replies || [];
                                child.replies.push(replyNode);
                                return true;
                            }
                            if (attach(child.replies || [])) return true;
                        }
                        return false;
                    };

                    if (String(state.parent?.id) === parentStr) {
                        state.replies = state.replies || [];
                        state.replies.push(replyNode);
                        return true;
                    }

                    return attach(state.replies || []);
                };

                const syncReplyInboxItem = (item, state) => {
                    if (!item || !state) return;
                    item.dataset.replyCount = state.reply_count ?? state.replies?.length ?? 0;
                    item.dataset.replyUnread = state.unread ?? 0;
                    item.dataset.replyThread = JSON.stringify(state);
                    item.classList.toggle('has-new', Number(state.unread) > 0);
                    const pill = item.querySelector('.pill-soft');
                    const label = item.querySelector('.reply-inbox-meta-label');
                    if (pill) {
                        pill.textContent = state.reply_count ?? 0;
                    }
                    if (label) {
                        const count = Number(state.reply_count ?? 0);
                        label.textContent = count === 1 ? 'reply' : 'replies';
                    }
                    const textEl = item.querySelector('.reply-inbox-text');
                    if (textEl) {
                        const preview = state.parent?.deleted ? deletedMessageText : (state.parent?.content || '');
                        textEl.textContent = truncateText(preview);
                    }
                };

                const ensureInboxItem = (state) => {
                    if (!replyInbox || !state || !state.parent) return null;
                    let item = replyInbox.querySelector(`.reply-inbox-item[data-reply-parent="${state.parent.id}"]`);
                    if (item) {
                        syncReplyInboxItem(item, state);
                        return item;
                    }
                    item = document.createElement('li');
                    item.className = 'reply-inbox-item';
                    item.dataset.replyThreadTrigger = '1';
                    item.dataset.replyParent = state.parent.id;
                    item.dataset.replyThread = JSON.stringify(state);
                    item.dataset.replyCount = state.reply_count ?? 0;
                    item.dataset.replyUnread = state.unread ?? 0;
                    const inboxPreview = truncateText(state.parent?.deleted ? deletedMessageText : state.parent?.content || '');
                    item.innerHTML = `
                        <div class="reply-inbox-top">
                            <span class="reply-inbox-author">${escapeHtml(state.parent.author || 'Guest')}</span>
                            <div class="reply-inbox-meta-row">
                                ${state.parent.is_question ? '<span class="message-badge message-badge-question">To host</span>' : ''}
                                ${state.parent.time ? `<span class="reply-inbox-time">${escapeHtml(state.parent.time)}</span>` : ''}
                            </div>
                        </div>
                        <div class="reply-inbox-text">${escapeHtml(inboxPreview)}</div>
                        <div class="reply-inbox-meta">
                            <span class="pill-soft">${state.reply_count ?? 0}</span>
                            <span class="reply-inbox-meta-label">${Number(state.reply_count ?? 0) === 1 ? 'reply' : 'replies'}</span>
                            <span class="reply-inbox-new" aria-hidden="true"></span>
                        </div>
                    `;
                    replyInbox.prepend(item);
                    syncReplyInboxItem(item, state);
                    return item;
                };

                const bootstrapRepliesState = () => {
                    replyThreadsState.clear();
                    if (!replyInbox) return;
                    replyInbox.querySelectorAll('.reply-inbox-item').forEach((item) => {
                        const thread = parseJsonSafe(item.dataset.replyThread, null);
                        if (!thread || !thread.parent) return;
                        thread.unread = Number(item.dataset.replyUnread || 0) || 0;
                        thread.reply_count = Number(thread.reply_count ?? countDescendants(thread.replies || []));
                        const id = String(thread.parent.id);
                        replyThreadsState.set(id, thread);
                        syncReplyInboxItem(item, thread);
                    });
                    updateRepliesBadge();
                };

                const renderReplyBranch = (node, depth = 0) => {
                    const branch = document.createElement('div');
                    branch.className = 'reply-thread-branch';
                    branch.style.setProperty('--depth', String(depth));

                    const authorName = node?.author || 'Guest';
                    const author = escapeHtml(authorName);
                    const time = escapeHtml(node?.time || '');
                    const isQuestion = Boolean(node?.is_question);
                    const targetId = escapeHtml(String(node?.id || ''));
                    const avatarBg = avatarColorFromName(authorName);
                    const initials = escapeHtml(initialsFromName(authorName));
                    const nodeContent = node?.deleted ? deletedMessageText : (node?.content || '');

                    const item = document.createElement('div');
                    item.className = 'reply-thread-message';
                    item.innerHTML = `
                        <div class="reply-thread-avatar" style="background: ${avatarBg}; color: #fff;">${initials}</div>
                        <div class="reply-thread-bubble">
                            <div class="reply-thread-meta">
                                <span class="reply-thread-author">${author}</span>
                                ${isQuestion ? '<span class="message-badge message-badge-question">To host</span>' : ''}
                                ${time ? `<span class="reply-thread-time">${time}</span>` : ''}
                                ${targetId ? `<button type="button" class="icon-btn reply-jump" data-reply-jump data-target-id="${targetId}" title="View in chat"><i data-lucide="arrow-up-right"></i></button>` : ''}
                            </div>
                            <div class="reply-thread-text">${escapeHtml(nodeContent)}</div>
                        </div>
                    `;
                    branch.appendChild(item);

                    const children = Array.isArray(node?.replies) ? node.replies : [];
                    if (children.length) {
                        const childrenWrap = document.createElement('div');
                        childrenWrap.className = 'reply-thread-children';
                        children.forEach((child) => {
                            childrenWrap.appendChild(renderReplyBranch(child, depth + 1));
                        });
                        branch.appendChild(childrenWrap);
                    }

                    return branch;
                };

                const renderReplyDetail = (threadData) => {
                    if (!threadData || !threadData.parent || !replyDetail || !replyDetailBody) return;
                    const parent = threadData.parent || {};
                    const replyCount = Number(threadData.reply_count ?? countDescendants(threadData.replies || []));
                    threadData.reply_count = replyCount;
                    const replyLabel = replyCount === 1 ? 'reply' : 'replies';
                    const parentName = parent.author || 'Guest';
                    const parentInitials = initialsFromName(parentName);
                    const parentAvatarBg = avatarColorFromName(parentName);
                    const parentContent = parent.deleted ? deletedMessageText : (parent.content || '');
                    activeReplyParentId = String(parent.id || '');
                    replyThreadsState.set(String(parent.id || ''), threadData);
                    if (repliesLayout) {
                        repliesLayout.classList.remove('is-collapsed');
                    }
                    replyDetail.hidden = false;
                    replyDetail.classList.add('is-open');
                    replyDetailBody.hidden = false;
                    replyDetailBody.innerHTML = '';
                    syncRepliesDetailLayout();

                    const header = document.createElement('div');
                    header.className = 'reply-detail-header';
                    header.innerHTML = `
                        <div class="reply-question">
                            <div class="reply-question-avatar" style="background: ${parentAvatarBg}; color: #fff;">${escapeHtml(parentInitials)}</div>
                            <div class="reply-question-content">
                                <div class="reply-question-meta">
                                    <span class="reply-question-author">${escapeHtml(parentName)}</span>
                                    ${parent.is_question ? '<span class="message-badge message-badge-question">To host</span>' : ''}
                                    ${parent.time ? `<span class="reply-question-time">${escapeHtml(parent.time)}</span>` : ''}
                                </div>
                                <div class="reply-question-text">${escapeHtml(parentContent)}</div>
                            </div>
                        </div>
                        <div class="reply-detail-actions">
                            <div class="reply-count">
                                <span class="pill-soft">${replyCount}</span>
                                <span class="reply-count-label">${replyLabel}</span>
                            </div>
                            <button type="button" class="icon-btn reply-detail-close" data-reply-detail-close aria-label="Hide thread">
                                <i data-lucide="x"></i>
                            </button>
                        </div>
                    `;
                    const closeBtn = header.querySelector('[data-reply-detail-close]');
                    if (closeBtn) {
                        closeBtn.addEventListener('click', hideReplyDetail);
                    }

                    const threadWrap = document.createElement('div');
                    threadWrap.className = 'reply-thread';
                    const replies = Array.isArray(threadData.replies) ? threadData.replies : [];
                    if (replies.length) {
                        replies.forEach((child) => {
                            threadWrap.appendChild(renderReplyBranch(child, 0));
                        });
                    } else {
                        const empty = document.createElement('div');
                        empty.className = 'panel-subtitle reply-thread-empty';
                        empty.textContent = 'No replies yet.';
                        threadWrap.appendChild(empty);
                    }

                    replyDetailBody.appendChild(header);
                    replyDetailBody.appendChild(threadWrap);
                    if (replyDetailEmpty) {
                        replyDetailEmpty.hidden = true;
                    }
                    if (replyInbox && parent.id) {
                        replyInbox.querySelectorAll('.reply-inbox-item').forEach((row) => {
                            const isActive = row.dataset.replyParent === String(parent.id);
                            row.classList.toggle('is-active', isActive);
                            if (isActive) {
                                row.setAttribute('aria-current', 'true');
                            } else {
                                row.removeAttribute('aria-current');
                            }
                        });
                        const activeItem = replyInbox.querySelector(`.reply-inbox-item[data-reply-parent="${parent.id}"]`);
                        const state = replyThreadsState.get(String(parent.id));
                        if (state) {
                            state.unread = 0;
                            syncReplyThreadCounts(state);
                        }
                        if (activeItem && state) {
                            syncReplyInboxItem(activeItem, state);
                        }
                    }
                    updateRepliesBadge();
                    if (window.refreshLucideIcons) {
                        window.refreshLucideIcons();
                    }
                };

                const handleIncomingReplyThread = (payload) => {
                    const replyParentId = normalizeId(payload?.reply_to?.id);
                    if (!replyParentId) return;

                    const replyParentDeleted = Boolean(payload?.reply_to?.is_deleted);
                    const replyAuthorUserId = normalizeId(payload.author?.user_id);
                    const replyAuthorParticipantId = normalizeId(payload.author?.participant_id);
                    const isOwnReply = (currentUserId && replyAuthorUserId && Number(currentUserId) === replyAuthorUserId)
                        || (currentParticipantId && replyAuthorParticipantId && Number(currentParticipantId) === replyAuthorParticipantId);

                    const existingThread = findThreadContainingId(replyParentId);
                    const parentEl = getMessageElById(replyParentId);
                    let parentData = existingThread?.parent || getParentDataFromMessageEl(parentEl);
                    if (!parentData && payload?.reply_to) {
                        parentData = {
                            id: replyParentId,
                            author: payload.reply_to.author || 'Guest',
                            time: payload.reply_to.time || formatTime(payload.reply_to.created_at),
                            content: replyParentDeleted ? deletedMessageText : (payload.reply_to.content || ''),
                            is_question: !!payload.reply_to.is_question,
                            deleted: replyParentDeleted,
                        };
                    } else if (parentData) {
                        parentData.deleted = replyParentDeleted || Boolean(parentData.deleted);
                        if (replyParentDeleted) {
                            parentData.content = deletedMessageText;
                        }
                    }

                    const rootData = findReplyRootData(replyParentId) || parentData;
                    const rootId = rootData?.id ? String(rootData.id) : String(replyParentId);

                    const isMineRoot = isMineMessageEl(parentEl) || isMineMessageData(parentData) || isMineMessageData(rootData);
                    if (!isOwnReply && !isMineRoot) {
                        return;
                    }

                    let state = existingThread || replyThreadsState.get(rootId);
                    if (!state && rootData) {
                        state = {
                            parent: rootData,
                            replies: [],
                            reply_count: 0,
                            unread: 0,
                        };
                        replyThreadsState.set(rootId, state);
                    }
                    if (!state) return;

                    // Keep parent info aligned to the root message
                    if (rootData) {
                        state.parent = rootData;
                    }

                    const replyNode = buildReplyBranchFromPayload(payload);
                    if (!replyNode) return;

                    const inserted = insertReplyNode(state, payload.reply_to?.id, replyNode);
                    if (!inserted) {
                        state.replies = state.replies || [];
                        state.replies.push(replyNode);
                    }

                    syncReplyThreadCounts(state);
                    const activeKey = String(state.parent?.id || rootId);
                    if (activeReplyParentId === activeKey && repliesPane && !repliesPane.hidden) {
                        state.unread = 0;
                        renderReplyDetail(state);
                    } else if (!isOwnReply) {
                        state.unread = Number(state.unread || 0) + 1;
                    }

                    const inboxItem = ensureInboxItem(state);
                    if (inboxItem) {
                        syncReplyInboxItem(inboxItem, state);
                    }
                    updateRepliesBadge();
                };

                const markRepliesDeleted = (deletedId) => {
                    if (!deletedId || !chatContainer) return;
                    const target = String(deletedId);
                    const replies = chatContainer.querySelectorAll(`.message[data-reply-to="${target}"]`);
                    replies.forEach((msg) => {
                        msg.dataset.replyDeleted = '1';
                        const replyEl = msg.querySelector('.message-reply');
                        if (replyEl) {
                            replyEl.dataset.replyDeleted = '1';
                            const textEl = replyEl.querySelector('.reply-text');
                            if (textEl) {
                                textEl.textContent = deletedMessageText;
                            }
                        }
                    });
                };

                const pruneReplyThreadsForDeletion = (deletedId) => {
                    if (!deletedId) return;
                    const target = String(deletedId);
                    const pruneNode = (node) => {
                        if (!node) return null;
                        if (String(node.id) === target) return null;
                        if (Array.isArray(node.replies)) {
                            node.replies = node.replies.map(pruneNode).filter(Boolean);
                        }
                        return node;
                    };

                    replyThreadsState.forEach((state) => {
                        if (!state || !state.parent) return;
                        const parentId = String(state.parent.id);
                        if (parentId === target) {
                            state.parent.deleted = true;
                            state.parent.content = deletedMessageText;
                        }
                        state.replies = (state.replies || []).map(pruneNode).filter(Boolean);
                        syncReplyThreadCounts(state);
                        if (replyInbox) {
                            const inboxItem = replyInbox.querySelector(`.reply-inbox-item[data-reply-parent="${state.parent.id}"]`);
                            if (inboxItem) {
                                syncReplyInboxItem(inboxItem, state);
                            }
                        }
                        if (activeReplyParentId === parentId && replyDetail && !replyDetail.hidden) {
                            renderReplyDetail(state);
                        }
                    });
                    updateRepliesBadge();
                };

                const ensureEmptyMessageState = () => {
                    if (!chatContainer) return;
                    const hasMessages = chatContainer.querySelector('.message:not(.message-empty)');
                    if (hasMessages) return;
                    let empty = chatContainer.querySelector('.message-empty');
                    if (!empty) {
                        empty = document.createElement('li');
                        empty.className = 'message message-empty';
                        empty.innerHTML = '<div class="message-body"><div class="message-text">No messages yet.</div></div>';
                        chatContainer.appendChild(empty);
                    }
                    empty.hidden = false;
                };

                const handleMessageDeleted = (messageId) => {
                    const targetId = normalizeId(messageId) ?? messageId;
                    if (!targetId) return;
                    const target = String(targetId);
                    const container = chatContainer || document.querySelector('.messages-container');
                    if (container) {
                        const el = container.querySelector(`.message[data-message-id="${target}"]`);
                        if (el) {
                            el.remove();
                        }
                    }
                    if (replyToInput && replyToInput.value === target) {
                        replyToInput.value = '';
                        if (replyPreviewAuthor) replyPreviewAuthor.textContent = '';
                        if (replyPreviewText) replyPreviewText.textContent = '';
                        if (replyPreview) replyPreview.hidden = true;
                    }
                    markRepliesDeleted(target);
                    pruneReplyThreadsForDeletion(target);
                    ensureEmptyMessageState();
                };

                function setupRepliesPane() {
                    if (!replyInbox) return;
                    replyInbox.addEventListener('click', (event) => {
                        const item = event.target.closest('[data-reply-thread-trigger]');
                        if (!item) return;
                        const parentId = item.dataset.replyParent;
                        const parsed = replyThreadsState.get(String(parentId)) || parseJsonSafe(item.dataset.replyThread, null);
                        if (!parsed || !parsed.parent) return;
                        parsed.unread = 0;
                        syncReplyThreadCounts(parsed);
                        replyThreadsState.set(String(parsed.parent.id), parsed);
                        syncReplyInboxItem(item, parsed);
                        updateRepliesBadge();
                        renderReplyDetail(parsed);
                    });
                    bootstrapRepliesState();
                    hideReplyDetail();
                    if (repliesMobileQuery?.addEventListener) {
                        repliesMobileQuery.addEventListener('change', syncRepliesDetailLayout);
                    } else if (repliesMobileQuery?.addListener) {
                        repliesMobileQuery.addListener(syncRepliesDetailLayout);
                    }
                    window.addEventListener('resize', syncRepliesDetailLayout);
                    syncRepliesDetailLayout();
                }

                const handleReplyJump = (event) => {
                    const replyEl = event.target.closest('.message-reply[data-reply-target]');
                    if (!replyEl) return;
                    const targetId = replyEl.dataset.replyTarget;
                    if (!targetId) return;
                    event.preventDefault();
                    scrollToMessage(targetId);
                };
                const jumpToChatMessage = (id) => {
                    if (!id) return;
                    setChatTab('chat');
                    scrollToMessage(id);
                };

                if (queueSoundUrl) {
                    window.queueSoundUrl = queueSoundUrl;
                    if (typeof window.initQueueSoundPlayer === 'function') {
                        window.initQueueSoundPlayer(queueSoundUrl);
                    }
                }

                setupChatTabs();
                bindBanForms();
                bindDeleteForms();
                bindDeleteTriggers();
                setupRepliesPane();
                setupInitialReactions();
                renderReactionMenuOptions();
                updateMessagesStateAttributes();
                updateMessagesLoadMoreVisibility();
                autosizeComposer();
                updateSendButtonState();
                scrollChatToBottom();
                maybeLoadOlderMessages();
                if (chatContainer) {
                    chatContainer.addEventListener('click', handleReplyJump);
                    chatContainer.addEventListener('scroll', () => {
                        maybeLoadOlderMessages();
                    });
                }
                if (replyDetailBody) {
                    replyDetailBody.addEventListener('click', (event) => {
                        const btn = event.target.closest('[data-reply-jump]');
                        if (!btn) return;
                        event.preventDefault();
                        const id = btn.dataset.targetId;
                        jumpToChatMessage(id);
                    });
                }
                window.addEventListener('keydown', (event) => {
                    if (event.key !== 'Escape') return;
                    if (repliesPane && repliesPane.hidden) return;
                    if (replyDetail && !replyDetail.hidden) {
                        hideReplyDetail();
                    }
                });
                if (reactionMenu) {
                    reactionMenu.addEventListener('click', handleReactionMenuClick);
                }

                if (chatInput) {
                    chatInput.addEventListener('input', () => {
                        autosizeComposer();
                        updateSendButtonState();
                    });
                    chatInput.addEventListener('keydown', (event) => {
                        // Stop bubbling so global shortcuts don’t swallow typing inside the composer.
                        if (event.key === ' ' || event.key === 'Enter') {
                            event.stopPropagation();
                        }
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
                            hideEmojiPanel();
                        } else {
                            insertEmojiIntoInput(emoji);
                            chatInput?.focus();
                        }
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
                  const getQrModules = (link, canvasSize) => {
                      if (typeof window.createQrModules !== 'function') {
                          return null;
                      }
                      return window.createQrModules(link, canvasSize, {
                          errorCorrectionLevel: 'H',
                          quietModules: 4,
                      });
                  };
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
                              const parsed = getQrModules(link, canvasSize);
                              if (!parsed) {
                                  throw new Error('Unable to generate QR code');
                              }

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
                          } catch (error) {
                              console.error('Styled QR build failed', error);
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
                            const item = form.closest('.queue-item');
                            const qId = normalizeId(item?.dataset.questionId);
                            if (qId) {
                                upsertQueueItem(qId);
                            } else {
                                scheduleReloadQuestionsPanel();
                            }
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
                    shell.appendChild(queueClone);
                    pipDoc.body.innerHTML = '';
                    pipDoc.body.appendChild(shell);
                    if (window.refreshLucideIcons) {
                        window.refreshLucideIcons(queueClone);
                    }
                    if (typeof window.setupQueueFilter === 'function') {
                        window.setupQueueFilter(queueClone);
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
                    attachQueuePipButton();
                }
                seedQueueRenderedIds();
                if (questionsPanel) {
                    questionsPanel.addEventListener('queue:filter-change', (event) => {
                        const value = event.detail?.value || getQueueFilterValue();
                        handleQueueFilterChange(value, {
                            initial: Boolean(event.detail?.initial),
                            preserveExisting: Boolean(event.detail?.initial),
                        });
                    });
                    bindQueueInteractions();
                    const body = getQueueBody();
                    if (body) {
                        body.addEventListener('scroll', () => {
                            maybeAutoloadQueue();
                        });
                    }
                    handleQueueFilterChange(getQueueFilterValue(), { initial: true, preserveExisting: true });
                    maybeAutoloadQueue();
                }

                attachQueuePipButton();

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
                    const actionAttr = (form.getAttribute('action') || '').trim();
                    const actionUrl = actionAttr ? new URL(actionAttr, window.location.href) : null;
                    if (!actionUrl) {
                        console.warn('Remote form skipped: missing action');
                        return false;
                    }
                    if (actionUrl.pathname === window.location.pathname || /\/r\/[^/]+/.test(actionUrl.pathname)) {
                        console.warn('Remote form skipped: action points to room page', actionUrl.pathname);
                        return false;
                    }

                    try {
                        const response = await fetch(actionUrl.toString(), {
                            method,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': token,
                            },
                            credentials: 'same-origin', // include session cookie for CSRF validation
                            body: formData,
                        });
                        const ok = response.status >= 200 && response.status < 400;
                        if (!ok) {
                            console.error('Remote form failed', response.status);
                            return false;
                        }
                        if (typeof onDone === 'function') {
                            onDone();
                        }
                        return true;
                    } catch (err) {
                        console.error('Remote form error', err);
                        return false;
                    }
                };

                const applyQuestionStatus = (item, status) => {
                    if (!item) return;
                    const normalized = (status || '').toLowerCase();
                    const pill = item.querySelector('.status-pill');
                    item.dataset.status = normalized;
                    if (normalized === 'new') {
                        item.classList.add('queue-item-new');
                        if (pill) pill.remove();
                        return;
                    }
                    item.classList.remove('queue-item-new');
                    const text = normalized.charAt(0).toUpperCase() + normalized.slice(1);
                    if (pill) {
                        pill.textContent = text;
                        pill.className = `status-pill status-${normalized}`;
                    } else {
                        const badge = document.createElement('span');
                        badge.className = `status-pill status-${normalized}`;
                        badge.textContent = text;
                        const header = item.querySelector('.question-header');
                        if (header) {
                            header.appendChild(badge);
                        } else {
                            item.appendChild(badge);
                        }
                    }
                };

                const setQueueItemBusy = (item, busy = true) => {
                    if (!item) return;
                    item.querySelectorAll('button').forEach((btn) => {
                        btn.disabled = busy;
                        btn.setAttribute('aria-busy', busy ? 'true' : 'false');
                        if (!busy) {
                            btn.removeAttribute('aria-busy');
                        }
                    });
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
                const initialsFromName = (name = 'Guest') => {
                    const parts = String(name || 'Guest').trim().split(/\s+/).filter(Boolean);
                    if (!parts.length) return 'GU';
                    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
                    return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
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

                const scheduleReloadQuestionsPanel = () => {
                    if (questionsReloadInFlight) {
                        questionsReloadPending = true;
                        return;
                    }
                    const now = Date.now();
                    const elapsed = now - lastQuestionsReloadAt;
                    if (elapsed < MIN_QUESTIONS_RELOAD_MS) {
                        clearTimeout(questionsReloadTimeout);
                        questionsReloadTimeout = setTimeout(() => {
                            questionsReloadTimeout = null;
                            reloadQuestionsPanel();
                        }, MIN_QUESTIONS_RELOAD_MS - elapsed);
                        return;
                    }
                    reloadQuestionsPanel();
                };

                async function reloadQuestionsPanel() {
                    if (!questionsPanel) return;
                    if (questionsReloadInFlight) {
                        questionsReloadPending = true;
                        return;
                    }

                    questionsReloadInFlight = true;

                    try {
                        await loadAllQueueItems();
                        bindQueueInteractions(questionsPanel);
                        updateQueueFilterCounts();
                        applyFilterToQueue(queueActiveFilter || getQueueFilterValue());
                        renderQueuePipContent();
                        if (queueNeedsNew && typeof window.markQueueHasNew === 'function') {
                            window.markQueueHasNew();
                            queueNeedsNew = false;
                        }
                    } catch (e) {
                        console.error('Refresh questions panel error', e);
                    } finally {
                        questionsReloadInFlight = false;
                        lastQuestionsReloadAt = Date.now();
                        if (questionsReloadPending) {
                            questionsReloadPending = false;
                            scheduleReloadQuestionsPanel();
                        }
                    }
                }

                function startQuestionsPolling() {
                    if (!questionsPanel || questionsPollTimer) return;
                    questionsPollTimer = setInterval(scheduleReloadQuestionsPanel, 6000);
                }

                if (questionsPanel) {
                    questionsPanel.addEventListener('submit', (event) => {
                        const target = event.target;
                        if (!(target instanceof HTMLFormElement)) return;
                        if (target.dataset.remote !== 'questions-panel') return;
                        const rawAction = (target.getAttribute('action') || '').trim();
                        const actionUrl = rawAction ? new URL(rawAction, window.location.href) : null;
                        if (!actionUrl || actionUrl.pathname === window.location.pathname || /\/r\/[^/]+/.test(actionUrl.pathname)) {
                            // Let the browser submit normally if action is missing or points to the room page.
                            return;
                        }
                        event.preventDefault();
                        const statusInput = target.querySelector('input[name="status"]');
                        const desiredStatus = (statusInput?.value || '').toLowerCase();
                        const methodAttr = (target.getAttribute('method') || 'POST').toUpperCase();
                        const methodOverride = (target.querySelector('input[name="_method"]')?.value || '').toUpperCase();
                        const effectiveMethod = methodOverride || methodAttr;
                        const isDeleteAction = effectiveMethod === 'DELETE';
                        const item = target.closest('.queue-item');
                        const qId = normalizeId(item?.dataset.questionId);
                        const shouldBlockActions = Boolean(qId && (desiredStatus || isDeleteAction));
                        if (shouldBlockActions && queueActionInflight.has(qId)) {
                            return;
                        }
                        const pill = item?.querySelector('.status-pill') || null;
                        const prev = item
                            ? {
                                status: item.dataset.status || 'new',
                                pillClass: pill?.className || '',
                                pillText: pill?.textContent || '',
                                hadPill: !!pill,
                                wasNew: item.classList.contains('queue-item-new'),
                            }
                            : null;

                        if (item && (desiredStatus || isDeleteAction)) {
                            if (shouldBlockActions && qId) {
                                queueActionInflight.add(qId);
                            }
                            setQueueItemBusy(item, true);
                            if (desiredStatus) {
                                applyQuestionStatus(item, desiredStatus);
                                if (desiredStatus === 'answered') {
                                    if (qId) {
                                        markMainQueueItemSeen(qId);
                                    }
                                }
                            }
                            window.setupQueueFilter?.(item.closest('#queuePanel') || document);
                        }

                        submitRemoteForm(target, () => {
                            if (qId) {
                                if (isDeleteAction) {
                                    removeQueueItem(qId);
                                } else {
                                    upsertQueueItem(qId);
                                }
                            } else {
                                scheduleReloadQuestionsPanel();
                            }
                            renderQueuePipContent();
                        }).then((ok) => {
                            if (ok) return;
                            if (prev && item) {
                                applyQuestionStatus(item, prev.status);
                                const updatedPill = item.querySelector('.status-pill');
                                if (!prev.hadPill && updatedPill) {
                                    updatedPill.remove();
                                } else if (updatedPill) {
                                    updatedPill.className = prev.pillClass;
                                    updatedPill.textContent = prev.pillText;
                                }
                                if (prev.wasNew) {
                                    item.classList.add('queue-item-new');
                                }
                                window.setupQueueFilter?.(item.closest('#queuePanel') || document);
                            }
                            if (typeof window.showFlashNotification === 'function') {
                                window.showFlashNotification('Unexpected error while updating the question.', {
                                    type: 'danger',
                                    source: 'queue-update',
                                });
                            } else {
                                alert('Unexpected error while updating the question.');
                            }
                        }).finally(() => {
                            if (qId) {
                                queueActionInflight.delete(qId);
                            }
                            setQueueItemBusy(item, false);
                        });
                    });
                }

                async function reloadMyQuestionsPanel() {
                    if (!myQuestionsPanel || !myQuestionsPanelUrl) return;

                    try {
                        const response = await fetch(myQuestionsPanelUrl, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
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

                    chatContainer.addEventListener('dblclick', (event) => {
                        const messageEl = event.target.closest('.message');
                        if (!messageEl) return;
                        const replyBtn = messageEl.querySelector('[data-reply-id]');
                        if (!replyBtn) return;
                        event.preventDefault();
                        setReplyContext(replyBtn.dataset.replyAuthor, replyBtn.dataset.replyText, replyBtn.dataset.replyId);
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

                const MAX_RENDERED_MESSAGES = Math.max(messagePageLimit * 6, 200);
                const incomingMessagesBuffer = [];
                let incomingMessagesFlushTimer = null;

                const isChatNearBottom = () => {
                    if (!chatContainer) return false;
                    return chatContainer.scrollTop + chatContainer.clientHeight >= chatContainer.scrollHeight - 28;
                };

                const trimRenderedMessages = (maxCount = MAX_RENDERED_MESSAGES) => {
                    if (!chatContainer || !Number.isFinite(maxCount) || maxCount <= 0) return;
                    const messages = Array.from(chatContainer.querySelectorAll('.message:not([data-messages-loader])'));
                    const excess = messages.length - maxCount;
                    if (excess > 0) {
                        const toRemove = messages.slice(0, excess);
                        toRemove.forEach((msg) => msg.remove());
                        const newOldest = messages[excess];
                        const newOldestId = normalizeId(newOldest?.dataset?.messageId);
                        if (newOldestId) {
                            messagesOldestKnownId = newOldestId;
                            updateMessagesStateAttributes();
                        }
                    }
                };

                const applyIncomingMessagePayload = (payload, newNodes) => {
                    if (!chatContainer || !payload) return { needsIcons: false, added: false };
                    const existing = chatContainer.querySelector(`.message[data-message-id="${payload.id}"]`);
                    if (existing) {
                        existing.classList.remove('message--pending');
                        existing.removeAttribute('data-temp-id');
                        existing.removeAttribute('data-temp-key');
                        updateMessageElementFromPayload(existing, payload, { refreshIcons: false });
                        bindBanForms(existing);
                        bindDeleteForms(existing);
                        bindDeleteTriggers(existing);
                        handleIncomingReplyThread(payload);
                        return { needsIcons: true, added: false };
                    }

                    const authorUserId = normalizeId(payload.author?.user_id);
                    const authorParticipantId = normalizeId(payload.author?.participant_id);
                    const tempKey = makeMessageKey(payload.content, authorUserId, authorParticipantId, payload.reply_to?.id, payload.as_question);
                    const pendingMatch = chatContainer.querySelector(`.message--pending[data-temp-key="${tempKey}"]`);
                    if (pendingMatch) {
                        updateMessageElementFromPayload(pendingMatch, payload, { refreshIcons: false });
                        bindBanForms(pendingMatch);
                        bindDeleteForms(pendingMatch);
                        bindDeleteTriggers(pendingMatch);
                        handleIncomingReplyThread(payload);
                        return { needsIcons: true, added: false };
                    }

                    const wrapper = createMessageElement(payload, { pending: false });
                    bindBanForms(wrapper);
                    bindDeleteForms(wrapper);
                    bindDeleteTriggers(wrapper);
                    newNodes.push(wrapper);
                    handleIncomingReplyThread(payload);
                    return { needsIcons: true, added: true };
                };

                const flushIncomingMessages = () => {
                    incomingMessagesFlushTimer = null;
                    if (!incomingMessagesBuffer.length) return;
                    if (!chatContainer) {
                        incomingMessagesBuffer.length = 0;
                        return;
                    }
                    const payloads = incomingMessagesBuffer.splice(0, incomingMessagesBuffer.length);
                    const shouldAutoscroll = isChatNearBottom();
                    const newNodes = [];
                    let needsIcons = false;
                    let added = false;

                    payloads.forEach((payload) => {
                        const result = applyIncomingMessagePayload(payload, newNodes);
                        needsIcons = needsIcons || result.needsIcons;
                        added = added || result.added;
                    });

                    if (newNodes.length) {
                        const fragment = document.createDocumentFragment();
                        newNodes.forEach((node) => fragment.appendChild(node));
                        chatContainer.appendChild(fragment);
                    }

                    if (added) {
                        removeEmptyMessageState();
                    }

                    trimRenderedMessages();

                    if (needsIcons && window.refreshLucideIcons) {
                        window.refreshLucideIcons(chatContainer);
                    }

                    if (shouldAutoscroll) {
                        scrollChatToBottom();
                    }
                };

                const enqueueIncomingMessage = (payload) => {
                    incomingMessagesBuffer.push(payload);
                    if (!incomingMessagesFlushTimer) {
                        incomingMessagesFlushTimer = window.requestAnimationFrame(flushIncomingMessages);
                    }
                };

                const initRealtime = () => {
                    if (window.Echo) {
                        const channelName = 'room.' + roomSlug;
                        window.Echo.channel(channelName)
                            .listen('MessageSent', (e) => {
                                enqueueIncomingMessage(e);
                            })
                            .listen('ReactionUpdated', (payload) => {
                                updateReactionsFromEvent(payload.message_id, payload.reactions, payload);
                            })
                            .listen('MessageDeleted', (payload) => {
                                handleMessageDeleted(payload.id);
                            })
                            .listen('QuestionCreated', (payload) => {
                                if (questionsPanel && payload?.id) {
                                    upsertQueueItem(payload.id);
                                }
                                if (myQuestionsPanel) {
                                    reloadMyQuestionsPanel();
                                }
                            })
                            .listen('QuestionUpdated', (payload) => {
                                if (questionsPanel && payload?.id) {
                                    upsertQueueItem(payload.id);
                                }
                                if (myQuestionsPanel) {
                                    reloadMyQuestionsPanel();
                                }
                            })
                            .error(() => {
                                startQuestionsPolling();
                                startMyQuestionsPolling();
                            });
                        return;
                    }
                    startQuestionsPolling();
                    startMyQuestionsPolling();
                };

                const echoReady = window.__echoReady;
                if (echoReady && typeof echoReady.then === 'function') {
                    echoReady.then(initRealtime).catch(initRealtime);
                } else {
                    initRealtime();
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

                            if (response.status === 429) {
                                if (optimisticEl) optimisticEl.remove();
                                const payload = await response.json().catch(() => ({}));
                                const message = payload?.message || 'You are sending messages too quickly. Please slow down.';
                                showThrottleNotice(message);
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
                                    bindBanForms(optimisticEl);
                                    bindDeleteForms(optimisticEl);
                                    bindDeleteTriggers(optimisticEl);
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
    @vite('resources/js/quick-responses.ts')
    @vite('resources/js/track-last-visited-room.ts')
</x-app-layout>
