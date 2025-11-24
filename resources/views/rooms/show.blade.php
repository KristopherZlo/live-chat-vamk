<x-app-layout>
    @php
        $publicLink = route('rooms.public', $room->slug);
        $isClosed = $room->status !== 'active';
        $queueSoundUrl = \Illuminate\Support\Facades\Vite::asset('resources/audio/new-question-sound.mp3');
    @endphp
    @php
        $avatarPalette = ['#2563eb', '#0ea5e9', '#6366f1', '#8b5cf6', '#14b8a6', '#f97316', '#f59e0b', '#10b981', '#ef4444'];
        $avatarColor = function (string $name = 'Guest') use ($avatarPalette) {
            $hash = crc32($name);
            $index = abs((int) $hash) % count($avatarPalette);
            return $avatarPalette[$index];
        };
    @endphp

    <div class="{{ $isOwner ? 'role-teacher' : 'role-student' }} room-page">
        <div class="panel room-header">
            @php
                $hasLongDescription = $room->description && \Illuminate\Support\Str::length($room->description) > 255;
                $roomDescription = $room->description ?: 'Add a description';
            @endphp
            <div class="panel-header room-header-bar">
                <div class="room-header-main">
                    <div class="panel-title">
                        <i data-lucide="messages-square"></i>
                        <div class="room-title-stack">
                            <div class="inline-editable" data-inline-edit>
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
                            <div class="room-code">Room code: {{ $room->slug }}</div>
                        </div>
                    </div>

                    <div class="inline-editable room-description-block" data-inline-edit>
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
                        @endif
                    </div>
                </div>
                <div class="room-header-aside">
                    <span class="status-pill status-{{ $room->status }} room-status">{{ ucfirst($room->status) }}</span>
                    @if($isOwner)
                        <div class="panel-actions">
                            <button class="btn btn-sm btn-ghost" type="button" data-copy="{{ $publicLink }}">Copy link</button>
                            <button class="btn btn-sm btn-ghost" type="button" id="qrButton">
                                <i data-lucide="qr-code"></i>
                                <span>Show QR-code</span>
                            </button>
                        </div>
                    @endif
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
                        @endphp
                        <li class="message {{ $isOutgoing ? 'message--outgoing' : '' }} {{ $isQuestionMessage ? 'message--question' : '' }}">
                            <div class="message-avatar colorized" style="background: {{ $avatarBg }}; color: #fff; border-color: transparent;">{{ $initials }}</div>
                            <div class="message-body">
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
                        <li class="message message-empty" data-empty-message>
                            <div class="message-body">
                                <div class="message-text">No messages yet.</div>
                            </div>
                        </li>
                    @endforelse
                </ol>

                @if(!$isClosed)
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
                        This room is closed. Messages are read-only.
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
                        <img src="{{ Vite::asset('resources/icons/logo_black.svg') }}" class="qr-logo-img" alt="Ghost Room logo">
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

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const roomId = {{ $room->id }};
                const isOwnerUser = @json($isOwner);
                const currentUserId = @json(auth()->id());
                const currentParticipantId = @json($participant?->id);
                const publicLink = @json($publicLink);
                const queueSoundUrl = @json($queueSoundUrl);
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
                  const qrCanvas = document.getElementById('qrCanvas');
                const chatContainer = document.querySelector('.messages-container');
                const csrfMeta = document.querySelector('meta[name=\"csrf-token\"]');
                const replyToInput = document.getElementById('replyToId');
                const replyPreview = document.getElementById('replyPreview');
                const replyPreviewAuthor = document.getElementById('replyPreviewAuthor');
                const replyPreviewText = document.getElementById('replyPreviewText');
                const replyPreviewCancel = document.getElementById('replyPreviewCancel');
                const avatarPalette = ['#2563eb', '#0ea5e9', '#6366f1', '#8b5cf6', '#14b8a6', '#f97316', '#f59e0b', '#10b981', '#ef4444'];
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

                if (queueSoundUrl) {
                    window.queueSoundUrl = queueSoundUrl;
                    if (typeof window.initQueueSoundPlayer === 'function') {
                        window.initQueueSoundPlayer(queueSoundUrl);
                    }
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
                  });
                  themeObserver.observe(document.body, { attributes: true, attributeFilter: ['data-theme'] });

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
                        const hasNewItems = questionsPanel.querySelector('.queue-item.queue-item-new');
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
                            removeEmptyMessageState();

                            const isOutgoing = (currentUserId && e.author.user_id && Number(currentUserId) === Number(e.author.user_id))
                                || (currentParticipantId && e.author.participant_id && Number(currentParticipantId) === Number(e.author.participant_id));
                            const authorNameRaw = e.author?.name || 'Guest';
                            const authorName = escapeHtml(authorNameRaw);
                            const content = escapeHtml(e.content || '');
                            const replyAuthor = escapeHtml(e.reply_to?.author || 'Guest');
                            const replyContent = escapeHtml(e.reply_to?.content || '');
                            const avatarColor = avatarColorFromName(e.author.name);
                            const wrapper = document.createElement('li');
                            wrapper.classList.add('message');
                            if (isOutgoing) {
                                wrapper.classList.add('message--outgoing');
                            }
                            if (e.as_question) {
                                wrapper.classList.add('message--question');
                            }
                            const isOwnerAuthor = Boolean(e.author.is_owner);
                            const time = new Date(e.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                            const devBadge = e.author.is_dev ? '<span class="message-badge message-badge-dev">dev</span>' : '';
                            const replyHtml = e.reply_to ? `<div class="message-reply"><span class="reply-author">${replyAuthor}</span><span class="reply-text">${replyContent}</span></div>` : '';
                            const initials = escapeHtml((authorNameRaw || '??').slice(0,2).toUpperCase());

                            wrapper.innerHTML = `
                                <div class="message-avatar colorized" style="background:${avatarColor}; color:#fff; border-color:transparent;">${initials}</div>
                                <div class="message-body">
                                    <div class="message-header">
                                        <span class="message-author">${authorName}${devBadge}</span>
                                        <div class="message-meta">
                                            <span>${time}</span>
                                            ${isOwnerAuthor ? '<span class="message-badge message-badge-teacher">Host</span>' : ''}
                                            ${e.as_question ? '<span class="message-badge message-badge-question">To host</span>' : ''}
                                            ${replyHtml ? '<span class="message-badge">Reply</span>' : ''}
                                        </div>
                                    </div>
                                    ${replyHtml}
                                    <div class="message-text">${content}</div>
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
                                replyBtn.dataset.replyAuthor = authorNameRaw || 'Guest';
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

                            removeEmptyMessageState();
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
