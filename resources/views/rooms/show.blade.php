<x-app-layout>
    @php
        $publicLink = route('rooms.public', $room->slug);
        $isFinished = $room->status === 'finished';
    @endphp

    <div class="{{ $isOwner ? 'role-teacher' : 'role-student' }}">
        <div class="panel room-header">
            <div class="panel-header">
                <div class="panel-title">
                    <i data-lucide="messages-square"></i>
                    <div>
                        <div class="room-name">{{ $room->title }}</div>
                        <div class="room-code">Room code: {{ $room->slug }}</div>
                        @if($room->description)
                            <div class="panel-subtitle">{{ $room->description }}</div>
                        @endif
                    </div>
                    <span class="status-pill status-{{ $room->status }}">{{ ucfirst($room->status) }}</span>
                </div>
                <div class="panel-actions">
                    <button class="btn btn-sm btn-ghost" type="button" data-copy="{{ $publicLink }}">Copy link</button>
                    <button class="btn btn-sm btn-ghost" type="button" id="qrButton">
                        <i data-lucide="qr-code"></i>
                        <span>Show QR-code</span>
                    </button>
                </div>
            </div>
        </div>

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
                        <span>Live chat</span>
                    </div>
                    <div class="panel-subtitle">Ask and discuss during the lecture.</div>
                </div>

                <ol class="chat-messages messages-container" id="chatMessages">
                    @forelse($messages as $message)
                        @php
                            $isOwnerMessage = $message->user && $message->user_id === $room->user_id;
                            $authorName = $message->user?->name ?? $message->participant?->display_name ?? 'Guest';
                            $initials = \Illuminate\Support\Str::of($authorName)->substr(0, 2)->upper();
                            $isOutgoing = $isOwner ? $isOwnerMessage : ($participant && $message->participant && $message->participant->id === $participant->id);
                            $isQuestionMessage = (bool) $message->question;
                            $replyTo = $message->replyTo;
                        @endphp
                        <li class="message {{ $isOutgoing ? 'message--outgoing' : '' }} {{ $isQuestionMessage ? 'message--question' : '' }}">
                            <div class="message-avatar">{{ $initials }}</div>
                            <div class="message-body">
                                <div class="message-header">
                                    <span class="message-author">{{ $authorName }}</span>
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
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="message">
                            <div class="message-body">
                                <div class="message-text">No messages yet.</div>
                            </div>
                        </li>
                    @endforelse
                </ol>

                @if(!$isFinished)
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
                            <div class="chat-input-row">
                                <textarea
                                    name="content"
                                    id="chatInput"
                                    class="chat-textarea"
                                    placeholder="Type your message..."
                                    rows="1"
                                    required
                                ></textarea>
                                <input type="hidden" name="reply_to_id" id="replyToId" value="">
                                <button type="submit" class="send-btn" id="sendButton" title="Send message">
                                    <i data-lucide="send"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                @else
                    <div class="panel-footer">
                        This room is finished. Messages are read-only.
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
                    <img id="qrImage" alt="QR code" width="140" height="140" loading="lazy">
                </div>
                <div class="qr-info">
                    <div class="panel-subtitle">Public link</div>
                    <a href="{{ $publicLink }}" class="qr-link" target="_blank" rel="noreferrer">{{ $publicLink }}</a>
                    <div class="qr-footer">
                        <button class="btn btn-sm btn-ghost" type="button" data-copy="{{ $publicLink }}">Copy link</button>
                        <a class="btn btn-sm btn-primary" id="qrDownload" href="#" download="room-{{ $room->slug }}-qr.png">Download</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const roomId = {{ $room->id }};
                const currentUserId = @json(auth()->id());
                const currentParticipantId = @json($participant?->id);
                const publicLink = @json($publicLink);
                const questionsPanel = document.getElementById('questions-panel');
                const questionsPanelUrl = @json(route('rooms.questionsPanel', $room));
                const myQuestionsPanel = document.getElementById('myQuestionsPanel');
                const myQuestionsPanelUrl = @json(route('rooms.myQuestionsPanel', $room));
                let queueNeedsNew = false;
                let questionsPollTimer = null;
                let myQuestionsPollTimer = null;
                const qrButton = document.getElementById('qrButton');
                const qrOverlay = document.getElementById('qrOverlay');
                const qrClose = document.getElementById('qrClose');
                const qrImage = document.getElementById('qrImage');
                const qrDownload = document.getElementById('qrDownload');
                const chatContainer = document.querySelector('.messages-container');
                const csrfMeta = document.querySelector('meta[name=\"csrf-token\"]');
                const replyToInput = document.getElementById('replyToId');
                const replyPreview = document.getElementById('replyPreview');
                const replyPreviewAuthor = document.getElementById('replyPreviewAuthor');
                const replyPreviewText = document.getElementById('replyPreviewText');
                const replyPreviewCancel = document.getElementById('replyPreviewCancel');

                const buildQrUrl = (link) => 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=' + encodeURIComponent(link);

                function openQr() {
                    if (!qrOverlay) return;
                    qrOverlay.classList.add('show');
                    qrOverlay.setAttribute('aria-hidden', 'false');
                    if (qrImage && !qrImage.src) {
                        const qrUrl = buildQrUrl(publicLink);
                        qrImage.src = qrUrl;
                        if (qrDownload) {
                            qrDownload.href = qrUrl;
                        }
                    }
                }

                function closeQr() {
                    if (!qrOverlay) return;
                    qrOverlay.classList.remove('show');
                    qrOverlay.setAttribute('aria-hidden', 'true');
                }

                if (qrButton) {
                    qrButton.addEventListener('click', openQr);
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

                function bindQueueInteractions(scope = document) {
                    if (!scope) return;
                    if (typeof window.rebindQueuePanels === 'function') {
                        window.rebindQueuePanels(scope);
                    }
                }
                if (questionsPanel) {
                    bindQueueInteractions();
                }

                const submitRemoteForm = async (form, onDone) => {
                    const formData = new FormData(form);
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
                        const hasNewItems = questionsPanel.querySelector('.queue-item[data-status=\"new\"]');
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
                        const btn = event.target.closest('[data-reply-id]');
                        if (!btn) return;
                        event.preventDefault();
                        setReplyContext(btn.dataset.replyAuthor, btn.dataset.replyText, btn.dataset.replyId);
                        chatContainer.scrollTop = chatContainer.scrollHeight;
                    });
                }

                if (window.Echo) {
                    const channelName = 'room.' + roomId;
                    window.Echo.channel(channelName)
                        .listen('MessageSent', (e) => {
                            const container = document.querySelector('.messages-container');
                            if (!container) return;

                            const isOutgoing = (currentUserId && e.author.user_id && Number(currentUserId) === Number(e.author.user_id))
                                || (currentParticipantId && e.author.participant_id && Number(currentParticipantId) === Number(e.author.participant_id));
                            const wrapper = document.createElement('li');
                            wrapper.classList.add('message');
                            if (isOutgoing) {
                                wrapper.classList.add('message--outgoing');
                            }
                            if (e.as_question) {
                                wrapper.classList.add('message--question');
                            }
                            const isOwnerAuthor = e.author.type === 'owner';
                            const time = new Date(e.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                            const replyHtml = e.reply_to ? `<div class="message-reply"><span class="reply-author">${e.reply_to.author || 'Guest'}</span><span class="reply-text">${e.reply_to.content || ''}</span></div>` : '';

                            wrapper.innerHTML = `
                                <div class="message-avatar">${(e.author.name || '??').slice(0,2).toUpperCase()}</div>
                                <div class="message-body">
                                    <div class="message-header">
                                        <span class="message-author">${e.author.name}</span>
                                        <div class="message-meta">
                                            <span>${time}</span>
                                            ${isOwnerAuthor ? '<span class="message-badge message-badge-teacher">Host</span>' : ''}
                                            ${e.as_question ? '<span class="message-badge message-badge-question">To host</span>' : ''}
                                            ${replyHtml ? '<span class="message-badge">Reply</span>' : ''}
                                        </div>
                                    </div>
                                    ${replyHtml}
                                    <div class="message-text">${e.content}</div>
                                    <div class="message-actions">
                                        <button type="button" class="msg-action">
                                            <i data-lucide="corner-up-right"></i>
                                            <span>Reply</span>
                                        </button>
                                    </div>
                                </div>`;

                            const replyBtn = wrapper.querySelector('.msg-action');
                            if (replyBtn) {
                                replyBtn.dataset.replyId = e.id;
                                replyBtn.dataset.replyAuthor = e.author.name || 'Guest';
                                replyBtn.dataset.replyText = e.content || '';
                            }

                            container.appendChild(wrapper);
                            container.scrollTop = container.scrollHeight;
                            if (window.refreshLucideIcons) {
                                window.refreshLucideIcons();
                            }
                        })
                        .listen('QuestionCreated', () => {
                            if (questionsPanel) {
                                queueNeedsNew = true;
                                reloadQuestionsPanel();
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
                if (chatForm) {
                    chatForm.addEventListener('submit', async (event) => {
                        event.preventDefault();

                        const formData = new FormData(chatForm);
                        const url = chatForm.action;

                        try {
                            const response = await fetch(url, {
                                method: 'POST',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': formData.get('_token'),
                                },
                                body: formData,
                            });

                            if (!response.ok) {
                                console.error('Send message failed', response.status);
                                return;
                            }

                            const textarea = chatForm.querySelector('textarea[name="content"]');
                            if (textarea) {
                                textarea.value = '';
                                textarea.style.height = 'auto';
                            }
                            const questionCheckbox = chatForm.querySelector('input[name="as_question"]');
                            if (questionCheckbox) {
                                questionCheckbox.checked = false;
                            }
                            clearReplyContext();
                        } catch (e) {
                            console.error('Send message error', e);
                        }
                    });
                }

                if (chatContainer) {
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                }
            });
        </script>
    @endpush
</x-app-layout>
