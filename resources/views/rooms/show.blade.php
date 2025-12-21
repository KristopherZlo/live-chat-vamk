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
                    <span class="room-code">Room code: <span class="room-code-value">{{ $room->slug }}</span></span>
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

        <div id="layoutRoot" class="layout layout-resizable {{ $isOwner ? 'teacher' : '' }}">
            <section class="panel chat-panel mobile-panel mobile-active" data-mobile-panel="chat">
                <button type="button" class="panel-collapse-handle" data-panel-expand="chat">Ghost Room chat</button>
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
                            <span class="pill-soft" data-ban-count>{{ $bannedParticipants->count() }}</span>
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
                                $normalizedMessageContent = \Illuminate\Support\Str::lower(trim($message->content ?? ''));
                                $isJebMessage = $normalizedMessageContent === 'jeb_';
                                $isDeadRabbitMessage = $normalizedMessageContent === 'deadrabbit';
                                $isZloyMessage = preg_match('/\bzloy\b/i', $message->content ?? '') === 1;
                                $isGlitchMessage = $isDeadRabbitMessage || $isZloyMessage;
                                $replyTo = $message->replyTo;
                                $replyDeleted = $replyTo?->trashed();
                                $deleteUrl = route('rooms.messages.destroy', [$room, $message]);
                                $canDeleteOwn = ($currentUserId && $message->user_id === $currentUserId)
                                    || ($participant && $message->participant && $participant->id === $message->participant->id);
                                $avatarBg = $avatarColor($authorName);
                                $usePrecomputedReactions = isset($reactionsByMessage) && is_array($reactionsByMessage);
                                $usePrecomputedMine = isset($myReactionsByMessage) && is_array($myReactionsByMessage);
                                $pollPayload = $pollsByMessage[$message->id] ?? null;
                                $isPollMessage = !empty($pollPayload);

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
                                class="message {{ $isOutgoing ? 'message--outgoing' : '' }} {{ $isQuestionMessage ? 'message--question' : '' }} {{ $isPollMessage ? 'message--poll' : '' }} {{ $isJebMessage ? 'message--jeb' : '' }} {{ $isGlitchMessage ? 'message--glitch' : '' }}"
                                data-message-id="{{ $message->id }}"
                                data-reactions-url="{{ route('rooms.messages.reactions.toggle', [$room, $message]) }}"
                                data-reactions='@json($reactionsGrouped)'
                                data-my-reactions='@json($myReactions)'
                                data-poll='@json($pollPayload)'
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
                                    @if($isPollMessage)
                                        @php
                                            $pollTotal = (int) ($pollPayload['total_votes'] ?? 0);
                                            $pollMyVoteId = $pollPayload['my_vote_id'] ?? null;
                                            $pollOptions = $pollPayload['options'] ?? [];
                                            $pollIsClosed = (bool) ($pollPayload['is_closed'] ?? false);
                                            $pollCanVote = !$pollIsClosed && !$isClosed && !$isBanned;
                                            $normalizeStarWarsLabel = static function (string $label): string {
                                                return (string) \Illuminate\Support\Str::of($label)
                                                    ->lower()
                                                    ->replaceMatches('/[^a-z0-9]+/', '');
                                            };
                                            $starWarsTriggers = ['starwar', 'starwars'];
                                        @endphp
                                        <div class="message-poll" data-poll-card data-poll-id="{{ $pollPayload['id'] ?? '' }}">
                                            <div class="poll-question">{{ $pollPayload['question'] ?? $message->content }}</div>
                                            <div class="poll-options" data-poll-options>
                                                @foreach($pollOptions as $option)
                                                    @php
                                                        $optionVotes = (int) ($option['votes'] ?? 0);
                                                        $optionPercent = (int) ($option['percent'] ?? 0);
                                                        $optionId = $option['id'] ?? null;
                                                        $isSelected = $pollMyVoteId && $optionId && (int) $pollMyVoteId === (int) $optionId;
                                                        $optionLabel = (string) ($option['label'] ?? '');
                                                        $normalizedLabel = $normalizeStarWarsLabel($optionLabel);
                                                        $isStarWarsOption = false;
                                                        foreach ($starWarsTriggers as $trigger) {
                                                            if ($trigger !== '' && str_contains($normalizedLabel, $trigger)) {
                                                                $isStarWarsOption = true;
                                                                break;
                                                            }
                                                        }
                                                    @endphp
                                                    @if($isStarWarsOption)
                                                        <button
                                                            type="button"
                                                            class="poll-option poll-option--saber saber-row {{ $isSelected ? 'is-selected' : '' }}"
                                                            data-poll-option-id="{{ $optionId }}"
                                                            aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                                                            @unless($pollCanVote) disabled @endunless
                                                        >
                                                            <span class="yoda-hilt" aria-hidden="true">
                                                                <span class="bottom">
                                                                    <span class="grip"></span>
                                                                </span>
                                                                <span class="top">
                                                                    <span class="on"></span>
                                                                    <span class="power-adjust-off"></span>
                                                                    <span class="length-adjust-off"></span>
                                                                </span>
                                                            </span>
                                                            <span class="option yoda-blade {{ $isSelected ? 'selected' : '' }}">
                                                                <span class="fill" style="width: {{ $optionPercent }}%;"></span>
                                                                <span class="tip" aria-hidden="true"></span>
                                                                <span class="label">{{ $optionLabel }}</span>
                                                                <span class="right">
                                                                    <span class="count">{{ $optionVotes }}</span>
                                                                    <span>{{ $optionPercent }}%</span>
                                                                </span>
                                                            </span>
                                                        </button>
                                                    @else
                                                        <button
                                                            type="button"
                                                            class="poll-option {{ $isSelected ? 'is-selected' : '' }}"
                                                            data-poll-option-id="{{ $optionId }}"
                                                            aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                                                            @unless($pollCanVote) disabled @endunless
                                                        >
                                                            <span class="poll-option-label">{{ $optionLabel }}</span>
                                                            <span class="poll-option-stats">
                                                                <span class="poll-option-count">{{ $optionVotes }}</span>
                                                                <span class="poll-option-percent">{{ $optionPercent }}%</span>
                                                            </span>
                                                            <span class="poll-option-bar" style="width: {{ $optionPercent }}%;"></span>
                                                        </button>
                                                    @endif
                                                @endforeach
                                            </div>
                                            <div class="poll-footer">
                                                <span class="poll-total">{{ $pollTotal }} votes</span>
                                                @if($pollMyVoteId)
                                                    <span class="poll-status">Your vote is saved</span>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        <div class="message-text">{{ $message->content }}</div>
                                    @endif
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
                                        class="quick-responses__poll"
                                        data-poll-toggle
                                        aria-label="Create poll"
                                    >
                                        <i data-lucide="bar-chart-3"></i>
                                    </button>
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
                                <input type="hidden" name="poll_mode" id="pollMode" value="0">
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
                                @if($isOwner)
                                    <div class="poll-composer" data-poll-composer hidden>
                                        <div class="poll-composer-header">
                                            <div class="poll-composer-title">Create poll</div>
                                            <button type="button" class="icon-btn poll-composer-close" data-poll-cancel aria-label="Close poll builder">
                                                <i data-lucide="x"></i>
                                            </button>
                                        </div>
                                        <label class="poll-composer-label" for="pollQuestionInput">Question</label>
                                        <input
                                            type="text"
                                            id="pollQuestionInput"
                                            name="poll_question"
                                            class="poll-composer-input"
                                            placeholder="Ask the room a question..."
                                            maxlength="255"
                                            data-poll-question
                                            disabled
                                        >
                                        <div class="input-counter" data-char-counter="poll-question" aria-live="polite"></div>
                                        <div class="poll-composer-options" data-poll-options-list></div>
                                        <div class="poll-composer-actions">
                                            <button type="button" class="btn btn-sm btn-ghost" data-poll-add-option disabled>Add option</button>
                                        </div>
                                    </div>
                                @endif
                                <div class="chat-composer" data-chat-composer>
                                    <button type="button" class="composer-btn composer-emoji" id="chatEmojiToggle" title="Add emoji">
                                        <i data-lucide="smile"></i>
                                    </button>
                                    <div class="chat-input-wrap">
                                        <textarea
                                            name="content"
                                            id="chatInput"
                                            class="chat-textarea"
                                            placeholder="Type your message..."
                                            rows="1"
                                            maxlength="2000"
                                            data-onboarding-target="chat-input"
                                        ></textarea>
                                        <div class="input-counter input-counter--chat" data-char-counter="chat" aria-live="polite"></div>
                                    </div>
                                    <input type="hidden" name="reply_to_id" id="replyToId" value="">
                                    <button type="submit" class="composer-btn composer-send" id="sendButton" title="Send message">
                                        <i data-lucide="send"></i>
                                    </button>
                                </div>
                                <div class="emoji-picker-panel" id="chatEmojiPanel" hidden>
                                    <emoji-picker id="chatEmojiPicker" class="emoji-picker-element light"></emoji-picker>
                                </div>
                                <span class="panel-subtitle chat-hint">Press Enter to send, Shift+Enter for a new line</span>
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
                    <div class="chat-pane chat-pane-bans" data-chat-panel="bans" data-bans-panel hidden>
                        <div class="moderation-block">
                            <div class="moderation-head">
                                <div>
                                    <div class="moderation-title">Banned participants</div>
                                    <div class="panel-subtitle">Banned users cannot post messages or questions.</div>
                                </div>
                                <span class="pill-soft" data-ban-count>{{ $bannedParticipants->count() }}</span>
                            </div>
                            @if($bannedParticipants->isEmpty())
                                <div class="empty-state" data-ban-empty>No banned participants yet.</div>
                            @else
                                <ul class="ban-list" data-ban-list>
                                    @foreach($bannedParticipants as $ban)
                                        <li class="ban-item" data-ban-item data-ban-id="{{ $ban->id }}" data-participant-id="{{ $ban->participant_id }}">
                                            <div>
                                                <div class="ban-name">{{ $ban->display_name ?? $ban->participant?->display_name ?? 'Guest' }}</div>
                                                <div class="ban-meta">Banned {{ $ban->created_at->diffForHumans(null, true) }} ago</div>
                                            </div>
                                            <form method="POST" action="{{ route('rooms.bans.destroy', [$room, $ban->id]) }}" data-unban-form>
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

            <div class="layout-resizer" data-layout-resizer role="separator" aria-orientation="vertical" aria-label="Resize panels"></div>

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
    @endpush
    @php
        $roomPageConfig = [
            'roomSlug' => $room->slug,
            'isOwnerUser' => $isOwner,
            'currentUserId' => auth()->id(),
            'currentParticipantId' => $participant?->id,
            'currentUserName' => auth()->user()?->name ?? $participant?->display_name ?? 'Guest',
            'currentParticipantName' => $participant?->display_name ?? 'Guest',
            'publicLink' => $publicLink,
            'queueSoundUrl' => $queueSoundUrl,
            'cacodemonImageUrl' => \Illuminate\Support\Facades\Vite::asset('resources/images/cacodemon.png'),
            'messagesHistoryUrl' => $messagesHistoryUrl,
            'messagesHasMoreInitial' => $messagesHasMore ?? false,
            'messagesOldestId' => $messagesOldestId,
            'messagesPageSize' => $messagePageSize ?? 50,
            'queueItemUrlTemplate' => $queueItemUrlTemplate ?? null,
            'queueItemsBatchUrl' => $isOwner ? route('rooms.questions.batch', $room) : null,
            'queueChunkUrl' => $isOwner ? route('rooms.questions.chunk', $room) : null,
            'queuePageSize' => $queuePageSize ?? 50,
            'questionsPanelUrl' => route('rooms.questionsPanel', $room),
            'myQuestionsPanelUrl' => route('rooms.myQuestionsPanel', $room),
            'banStoreUrl' => route('rooms.bans.store', $room),
            'banDestroyUrlTemplate' => route('rooms.bans.destroy', [$room, '__BAN__']),
            'pollVoteUrlTemplate' => route('rooms.polls.vote', [$room, '__POLL__']),
            'roomIsClosed' => $isClosed,
            'viewerIsBanned' => $isBanned,
            'reactionUrlTemplate' => route('rooms.messages.reactions.toggle', [$room, '__MESSAGE__']),
            'deleteUrlTemplate' => route('rooms.messages.destroy', [$room, '__MESSAGE__']),
            'popularReactions' => $popularReactions,
            'isDevUser' => auth()->user()?->is_dev,
        ];
    @endphp
    <script type="application/json" id="roomPageConfig">
        @json($roomPageConfig)
    </script>
    @vite('resources/js/room-show.ts')
    @vite('resources/js/quick-responses.ts')
    @vite('resources/js/track-last-visited-room.ts')
</x-app-layout>
