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
                </div>
            </div>
        </div>

    <nav class="mobile-tabs" id="mobileTabs" aria-label="Sections">
      <button class="mobile-tab-btn active" data-tab-target="chat">Chat</button>
      @if($isOwner)
        <button class="mobile-tab-btn" data-tab-target="queue">Queue</button>
        <button class="mobile-tab-btn" data-tab-target="history">History</button>
      @else
        <button class="mobile-tab-btn" data-tab-target="questions">Questions</button>
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
                        @endphp
                        <li class="message {{ $isOutgoing ? 'message--outgoing' : '' }}">
                            <div class="message-avatar">{{ $initials }}</div>
                            <div class="message-body">
                                <div class="message-header">
                                    <span class="message-author">{{ $authorName }}</span>
                                    <div class="message-meta">
                                        <span>{{ $message->created_at->format('H:i') }}</span>
                                        @if($isOwnerMessage)
                                            <span class="message-badge message-badge-teacher">Host</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="message-text">{{ $message->content }}</div>
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
                            <div class="chat-input-row">
                                <textarea
                                    name="content"
                                    id="chatInput"
                                    class="chat-textarea"
                                    placeholder="Type your message..."
                                    rows="1"
                                    required
                                ></textarea>
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
                <section class="panel student-panel mobile-panel" data-mobile-panel="questions">
                    <div class="panel-header">
                        <div>
                            <div class="panel-title">
                                <i data-lucide="help-circle"></i>
                                <span>My questions</span>
                            </div>
                            <div class="panel-subtitle">Questions sent to the host</div>
                        </div>
                        <span class="queue-action">{{ isset($myQuestions) ? $myQuestions->count() : 0 }}</span>
                    </div>
                    <div class="panel-body">
                        @if(isset($myQuestions) && $myQuestions->isNotEmpty())
                            <ul class="questions-list">
                                @foreach($myQuestions as $question)
                                    @php
                                        $myRating = optional($question->ratings->first())->rating;
                                    @endphp
                                    <li class="question-item">
                                        <div class="question-header">
                                            <div class="question-meta">
                                                <span class="message-meta">{{ $question->created_at->format('H:i') }}</span>
                                                <span class="status-pill status-{{ $question->status }}">{{ ucfirst($question->status) }}</span>
                                            </div>
                                            @if($room->status !== 'finished')
                                                <form method="POST" action="{{ route('questions.participantDelete', $question) }}" onsubmit="return confirm('Delete this question?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                                                </form>
                                            @endif
                                        </div>
                                        <div class="question-text">{{ $question->content }}</div>
                                        @if($question->status === 'answered')
                                            <div class="rating">
                                                <span class="rating-label">Was this useful?</span>
                                                <div class="rating-options">
                                                    <form method="POST" action="{{ route('questions.rate', $question) }}">
                                                        @csrf
                                                        <input type="hidden" name="rating" value="1">
                                                        <button class="rating-pill rating-pill-ok {{ $myRating === 1 ? 'active' : '' }}" type="submit">Yes</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('questions.rate', $question) }}">
                                                        @csrf
                                                        <input type="hidden" name="rating" value="-1">
                                                        <button class="rating-pill rating-pill-bad {{ $myRating === -1 ? 'active' : '' }}" type="submit">No</button>
                                                    </form>
                                                </div>
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-muted">You have not asked any questions yet.</p>
                        @endif
                    </div>
                    <div class="panel-footer">
                        <span>Only you can see these.</span>
                        <span class="panel-subtitle">{{ isset($myQuestions) ? $myQuestions->count() : 0 }} total</span>
                    </div>
                </section>
            @endif
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const roomId = {{ $room->id }};
                const questionsPanel = document.getElementById('questions-panel');
                const questionsPanelUrl = @json($isOwner ? route('rooms.questionsPanel', $room) : null);
                let queueNeedsNew = false;

                function bindQueueInteractions(scope = document) {
                    if (typeof window.rebindQueuePanels === 'function') {
                        window.rebindQueuePanels(scope);
                    }
                }
                bindQueueInteractions();

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
                        if (queueNeedsNew && typeof window.markQueueHasNew === 'function') {
                            window.markQueueHasNew();
                            queueNeedsNew = false;
                        }
                    } catch (e) {
                        console.error('Refresh questions panel error', e);
                    }
                }

                if (window.Echo) {
                    const channelName = 'room.' + roomId;
                    window.Echo.channel(channelName)
                        .listen('MessageSent', (e) => {
                            const container = document.querySelector('.messages-container');
                            if (!container) return;

                            const wrapper = document.createElement('li');
                            wrapper.classList.add('message');
                            const isOwnerAuthor = e.author.type === 'owner';
                            const time = new Date(e.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                            wrapper.innerHTML = `
                                <div class="message-avatar">${(e.author.name || '??').slice(0,2).toUpperCase()}</div>
                                <div class="message-body">
                                    <div class="message-header">
                                        <span class="message-author">${e.author.name}</span>
                                        <div class="message-meta">
                                            <span>${time}</span>
                                            ${isOwnerAuthor ? '<span class="message-badge message-badge-teacher">Host</span>' : ''}
                                        </div>
                                    </div>
                                    <div class="message-text">${e.content}</div>
                                </div>`;

                            container.appendChild(wrapper);
                            container.scrollTop = container.scrollHeight;
                            if (window.refreshLucideIcons) {
                                window.refreshLucideIcons();
                            }
                        })
                        .listen('QuestionCreated', () => {
                            queueNeedsNew = true;
                            reloadQuestionsPanel();
                        })
                        .listen('QuestionUpdated', reloadQuestionsPanel);
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
                        } catch (e) {
                            console.error('Send message error', e);
                        }
                    });
                }
            });
        </script>
    @endpush
</x-app-layout>
