const ONBOARDING_STORAGE_KEY = 'lc-onboarding-state';
const ONBOARDING_DEFAULT_STATE = {
    status: 'pending', // pending | active | skipped | completed
    stage: 'intro', // intro -> roomForm -> roomLive
    stepIndex: 0,
};

const ONBOARDING_STAGES = {
    intro: {
        route: 'dashboard',
        steps: [
            {
                id: 'create-room',
                selector: '[data-onboarding-target="dashboard-create-room"], [data-onboarding-target="create-room-nav"]',
                title: 'Start with your first room',
                body: 'Create a room to see the host view and try the chat with the question queue.',
                padding: 10,
            },
        ],
        next: 'roomForm',
        hint: {
            text: 'Ready? Click “Create room” to open the form and create your first room.',
            actionSelector: '[data-onboarding-target="dashboard-create-room"]',
        },
    },
    roomForm: {
        route: 'rooms.create',
        steps: [
            {
                id: 'room-title',
                selector: '[data-onboarding-target="room-title"]',
                title: 'Room title',
                body: 'Briefly describe the session topic so attendees know what they are joining.',
                padding: 8,
            },
            {
                id: 'room-description',
                selector: '[data-onboarding-target="room-description"]',
                title: 'Description and rules',
                body: 'Add guidance: what to ask, how to flag questions, links to materials.',
                padding: 8,
            },
            {
                id: 'room-submit',
                selector: '[data-onboarding-target="room-submit"]',
                title: 'Launch the room',
                body: 'Save the form — the room opens immediately and you see the host interface.',
                padding: 10,
            },
        ],
        next: 'roomLive',
        hint: {
            text: 'After saving you land in the new room. There we show how to work with questions.',
        },
    },
    roomLive: {
        route: 'rooms.public',
        requireOwner: true,
        steps: [
            {
                id: 'chat-reply',
                selector: '[data-onboarding-action="reply-demo"]',
                title: 'Reply in chat',
                body: 'Click “Reply” on the message to open the preview.',
                completeEvent: 'reply-open',
                padding: 12,
            },
            {
                id: 'chat-input',
                selector: '#chatInput',
                title: 'Write your answer',
                body: 'Type your message and send it.',
                completeEvent: 'reply-sent',
                padding: 12,
                placement: 'top',
                offsetY: -56,
            },
            {
                id: 'ban',
                selector: '[data-onboarding-action="ban-demo"]',
                title: 'Ban a disruptive user',
                body: 'Click “Ban participant” on the message to see how blocking works.',
                padding: 12,
                trackHighlight: true,
            },
            {
                id: 'unban',
                selector: '[data-onboarding-action="unban-demo"]',
                title: 'How to unban',
                body: 'Open the Bans tab and click “Unban” to let them back into chat.',
                padding: 10,
                prepare: openBansTab,
            },
            {
                id: 'queue-read',
                selector: '[data-onboarding-target="queue-panel"] .queue-item.queue-item-new',
                title: 'New question',
                body: 'We added a question with a sound cue. Click the card to mark it read.',
                padding: 12,
                prepare: ensureQueueDemoVisible,
                completeEvent: 'queue-read',
            },
            {
                id: 'queue-answer',
                selector: '[data-onboarding-action="queue-answered"]',
                title: 'Mark as answered',
                body: 'Now choose “Answered” to close the question and move it to history.',
                padding: 12,
                completeEvent: 'queue-answered',
            },
            {
                id: 'history-feedback',
                selector: '[data-onboarding-demo="history-feedback"] .rating-actions',
                title: 'Student feedback',
                body: 'Answered questions can get “clear/unclear” votes. Click to see the actions.',
                padding: 8,
                prepare: ensureHistoryOpen,
                completeEvent: 'feedback-given',
            },
            {
                id: 'qr',
                selector: '#qrButton',
                title: 'QR code for participants',
                body: 'Click QR to show the code and link for attendees to join.',
                padding: 10,
            },
        ],
        next: null,
    },
};

function readState() {
    const fallback = { ...ONBOARDING_DEFAULT_STATE };
    try {
        const raw = localStorage.getItem(ONBOARDING_STORAGE_KEY);
        if (!raw) return fallback;
        const parsed = JSON.parse(raw);
        if (parsed && typeof parsed === 'object') {
            return { ...fallback, ...parsed };
        }
    } catch (e) {
        /* ignore */
    }
    return fallback;
}

function persistState(nextState) {
    try {
        localStorage.setItem(ONBOARDING_STORAGE_KEY, JSON.stringify(nextState));
    } catch (e) {
        /* ignore */
    }
}

function updateState(patch) {
    const current = readState();
    const nextState = { ...current, ...patch };
    persistState(nextState);
    return nextState;
}

function getContext() {
    const body = document.body;
    const routeName = body?.dataset.routeName || '';
    const newUser = body?.dataset.onboardingNewUser === '1';
    const hasRooms = body?.dataset.onboardingHasRooms === '1';
    const userName = body?.dataset.userName || '';
    const roomRoot = document.querySelector('.room-page');
    const roomRole = roomRoot?.dataset.roomRole || '';

    return { body, routeName, newUser, hasRooms, userName, roomRole };
}

function isMobileViewport() {
    if (window.matchMedia && window.matchMedia('(max-width: 768px)').matches) {
        return true;
    }
    return window.innerWidth <= 768;
}

function overlayIsActive() {
    const overlay = document.querySelector('[data-onboarding-overlay]');
    return !!(overlay && overlay.classList.contains('active'));
}

function openUserMenu() {
    const menu = document.querySelector('.user-menu');
    if (!menu) return null;
    const wasOpen = menu.hasAttribute('open');
    menu.setAttribute('open', 'open');
    return () => {
        if (!wasOpen) {
            menu.removeAttribute('open');
        }
    };
}

function openBansTab() {
    const tabs = document.querySelectorAll('[data-chat-tab]');
    const panes = document.querySelectorAll('[data-chat-panel]');
    let target = null;
    tabs.forEach((btn) => {
        const isBans = btn.dataset.chatTab === 'bans';
        btn.classList.toggle('active', isBans);
        if (isBans) target = btn;
    });
    panes.forEach((pane) => {
        const isBans = pane.dataset.chatPanel === 'bans';
        pane.toggleAttribute('hidden', !isBans);
    });
    return () => {
        const chatBtn = document.querySelector('[data-chat-tab="chat"]');
        const chatPane = document.querySelector('[data-chat-panel="chat"]');
        tabs.forEach((btn) => btn.classList.toggle('active', btn === chatBtn));
        panes.forEach((pane) => pane.toggleAttribute('hidden', pane !== chatPane));
    };
}

function ensureHistoryOpen() {
    const layoutRoot = document.getElementById('layoutRoot');
    const historyPanel = document.getElementById('historyPanel');
    layoutRoot?.classList.remove('history-hidden');
    historyPanel?.classList.remove('hidden');
    document.querySelectorAll('[data-toggle-history]').forEach((btn) => {
        btn.classList.add('active');
        const label = btn.querySelector('span');
        if (label) label.textContent = 'Close history';
    });
    const historyTab = document.querySelector('[data-tab-target="history"]');
    if (historyTab) historyTab.classList.add('active');
}

function ensureHistoryList() {
    const historyPanel = document.getElementById('historyPanel');
    if (!historyPanel) return null;
    const body = historyPanel.querySelector('.panel-body');
    if (!body) return null;
    let list = historyPanel.querySelector('.history-list');
    if (!list) {
        list = document.createElement('ul');
        list.className = 'history-list';
        body.appendChild(list);
    }
    const empty = body.querySelector('.empty-state');
    if (empty) empty.remove();
    return list;
}

function findFirst(selector) {
    if (!selector) return null;
    return document.querySelector(selector);
}

function ensureQueueDemoVisible() {
    ensureHistoryOpen();
    const queuePanel = document.getElementById('queuePanel');
    if (!queuePanel) return null;
    queuePanel.classList.remove('hidden');
    const tab = document.querySelector('[data-tab-target="queue"]');
    if (tab) tab.classList.add('active');
    const historyTab = document.querySelector('[data-tab-target="history"]');
    if (historyTab) historyTab.classList.add('active');
    playQueueSoundSample();
}

function removeElement(el) {
    if (el && el.parentNode) {
        el.parentNode.removeChild(el);
    }
}

function playQueueSoundSample() {
    const src = window.queueSoundUrl || '/audio/new-question-sound.mp3';
    try {
        const audio = new Audio(src);
        audio.play().catch(() => {});
    } catch (e) {
        /* ignore */
    }
}

function ensureDemoRoomData(ctx) {
    const state = readState();
    if (state.status === 'completed' || state.status === 'skipped') return;
    if (ctx.routeName !== 'rooms.public' || ctx.roomRole !== 'owner') return;

    // chat message with reply + ban
    const chatList = document.getElementById('chatMessages');
    if (chatList && !chatList.querySelector('[data-onboarding-demo="message"]')) {
        const li = document.createElement('li');
        li.className = 'message';
        li.dataset.onboardingDemo = 'message';
        li.innerHTML = `
            <div class="message-avatar colorized" style="background: #2563eb; color: #fff; border-color: transparent;">JD</div>
            <div class="message-body">
                <form class="message-ban-form" data-onboarding-demo="ban-form">
                    <button type="button" class="message-ban-btn" data-onboarding-action="ban-demo" title="Ban participant">
                        <i data-lucide="gavel"></i>
                    </button>
                </form>
                <div class="message-header">
                    <span class="message-author">Student</span>
                    <div class="message-meta">
                        <span>12:00</span>
                        <span class="message-badge message-badge-question">To host</span>
                    </div>
                </div>
                <div class="message-text">Can you slow down on slide 3?</div>
                <div class="message-actions">
                    <button type="button" class="msg-action" data-onboarding-action="reply-demo">
                        <i data-lucide="corner-up-right"></i>
                        <span>Reply</span>
                    </button>
                </div>
            </div>
        `;
        const banBtn = li.querySelector('[data-onboarding-action="ban-demo"]');
        if (banBtn) {
            banBtn.style.transition = 'none';
            banBtn.style.animation = 'none';
            banBtn.style.transform = 'none';
        }
        chatList.appendChild(li);
    }

    // queue item (new question)
    const queuePanel = document.getElementById('queuePanel');
    if (queuePanel) {
        let queueList = queuePanel.querySelector('.queue-list');
        if (!queueList) {
            queueList = document.createElement('ul');
            queueList.className = 'queue-list';
            queuePanel.querySelector('.panel-body')?.appendChild(queueList);
        }
        let created = false;
        if (!queueList.querySelector('[data-onboarding-demo="queue"]')) {
            const li = document.createElement('li');
            li.className = 'queue-item queue-item-new';
            li.dataset.questionId = '999001';
            li.dataset.status = 'new';
            li.dataset.onboardingDemo = 'queue';
            li.innerHTML = `
                <div class="question-header">
                    <div class="question-meta">
                        <span class="message-author">New guest</span>
                        <span class="message-meta">just now</span>
                    </div>
                </div>
                <div class="question-text">How do we submit feedback after the lecture?</div>
                <div class="question-actions">
                    <div class="queue-controls">
                        <button type="button" class="queue-action queue-action-answered" data-onboarding-action="queue-answered">Answered</button>
                        <button type="button" class="queue-action queue-action-ignored" data-onboarding-action="queue-ignored">Ignore</button>
                        <button type="button" class="queue-action queue-action-later" data-onboarding-action="queue-later">Later</button>
                    </div>
                    <span class="panel-subtitle">Click the card to mark as read</span>
                </div>
            `;
            li.addEventListener('click', () => {
                li.classList.remove('queue-item-new');
                window.dispatchEvent(new CustomEvent('onboarding:demo', { detail: { event: 'queue-read' } }));
            });
            queueList.prepend(li);
            created = true;
        } else {
            const demo = queueList.querySelector('[data-onboarding-demo="queue"]');
            demo.classList.add('queue-item-new');
        }
        if (created) {
            playQueueSoundSample();
        }
    }

    // history item with feedback
    const historyList = document.querySelector('#historyPanel .history-list');
    // history demo is created after marking "Answered"

    // bans list demo
    const bansPane = document.querySelector('.chat-pane-bans');
    if (bansPane && !bansPane.querySelector('[data-onboarding-demo="ban-item"]')) {
        let list = bansPane.querySelector('.ban-list');
        if (!list) {
            list = document.createElement('ul');
            list.className = 'ban-list';
            bansPane.querySelector('.moderation-block')?.appendChild(list);
        }
        const li = document.createElement('li');
        li.className = 'ban-item';
        li.dataset.onboardingDemo = 'ban-item';
        li.innerHTML = `
            <div>
                <div class="ban-name">Demo student</div>
                <div class="ban-meta">Banned just now</div>
            </div>
            <button type="button" class="btn btn-sm btn-ghost" data-onboarding-action="unban-demo">
                <i data-lucide="undo-2"></i>
                <span>Unban</span>
            </button>
        `;
        list.prepend(li);
    }

    ensureHistoryOpen();
    if (window.lucide?.createIcons) {
        window.lucide.createIcons();
    }
}

function addHistoryFeedbackDemo(historyList) {
    const list = historyList || ensureHistoryList();
    if (!list) return null;
    const existing = list.querySelector('[data-onboarding-demo="history-feedback"]');
    if (existing) return existing;
    const li = document.createElement('li');
    li.className = 'history-item';
    li.dataset.onboardingDemo = 'history-feedback';
    li.innerHTML = `
        <div class="question-header">
            <div class="question-meta">
                <span class="message-author">Guest</span>
                <span class="message-meta">today 11:40</span>
            </div>
            <span class="status-pill status-answered">Answered</span>
        </div>
        <div class="question-text">Will slides be shared afterwards?</div>
        <div class="rating">
            <span class="rating-label">Students feedback:</span>
            <div class="rating-actions" data-onboarding-demo="feedback-actions">
                <button type="button" class="rating-pill rating-pill-ok" data-onboarding-action="feedback-clear">clear</button>
                <button type="button" class="rating-pill rating-pill-bad" data-onboarding-action="feedback-unclear">unclear</button>
            </div>
        </div>
    `;
    list.prepend(li);
    return li;
}

function moveQueueItemToHistory(queueItem) {
    if (!queueItem) return null;
    const historyList = ensureHistoryList();
    if (!historyList) return null;
    const text = queueItem.querySelector('.question-text')?.textContent?.trim() || 'Question';
    const author = queueItem.querySelector('.message-author')?.textContent?.trim() || 'Guest';
    const li = document.createElement('li');
    li.className = 'history-item';
    li.dataset.onboardingDemo = 'history-feedback';
    li.innerHTML = `
        <div class="question-header">
            <div class="question-meta">
                <span class="message-author">${author}</span>
                <span class="message-meta">just now</span>
            </div>
            <span class="status-pill status-answered">Answered</span>
        </div>
        <div class="question-text">${text}</div>
        <div class="rating">
            <span class="rating-label">Students feedback:</span>
            <div class="rating-actions" data-onboarding-demo="feedback-actions">
                <button type="button" class="rating-pill rating-pill-ok" data-onboarding-action="feedback-clear">clear</button>
                <button type="button" class="rating-pill rating-pill-bad" data-onboarding-action="feedback-unclear">unclear</button>
            </div>
        </div>
    `;
    historyList.prepend(li);
    return li;
}

function showReplyPreviewDemo() {
    const preview = document.getElementById('replyPreview');
    const author = document.getElementById('replyPreviewAuthor');
    const text = document.getElementById('replyPreviewText');
    if (!preview || !author || !text) return;
    author.textContent = 'Student';
    text.textContent = 'Can you slow down on slide 3?';
    preview.hidden = false;
    preview.dataset.onboardingDemo = 'reply';
}

function showDemoFlash(message, type = 'info') {
    const flash = document.createElement('div');
    flash.className = `flash flash-${type}`;
    flash.dataset.flash = '1';
    flash.innerHTML = `
        <span>${message}</span>
        <button class="icon-btn flash-close" type="button" aria-label="Close">
            <i data-lucide="x"></i>
        </button>
    `;
    document.querySelector('.chat-panel .panel-body')?.prepend(flash);
    setTimeout(() => flash.remove(), 3500);
}

function setupDemoActionHandlers() {
    const chatForm = document.getElementById('chat-form');
    if (chatForm) {
        chatForm.addEventListener('submit', (event) => {
            const preview = document.getElementById('replyPreview');
            const chatInput = document.getElementById('chatInput');
            const state = readState();
            const isRoomLiveOnboarding = state.status === 'active' && state.stage === 'roomLive';
            const isOverlay = overlayIsActive();
            const isDemoReply = preview && preview.dataset.onboardingDemo === 'reply';
            const shouldBlockSubmit = isDemoReply || isOverlay || isRoomLiveOnboarding || window.__lcOnboardingActive;
            if (shouldBlockSubmit) {
                event.preventDefault();
                showDemoFlash('Reply sent (demo).', 'success');
                if (chatInput) {
                    chatInput.value = '';
                }
                if (preview) {
                    preview.hidden = true;
                    delete preview.dataset.onboardingDemo;
                }
                window.dispatchEvent(new CustomEvent('onboarding:demo', { detail: { event: 'reply-sent' } }));
                return;
            }
        });
    }

    document.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-onboarding-action]');
        if (!btn) return;
        const action = btn.dataset.onboardingAction;

        if (action === 'reply-demo') {
            event.preventDefault();
            showReplyPreviewDemo();
            window.dispatchEvent(new CustomEvent('onboarding:demo', { detail: { event: 'reply-open' } }));
        }

        if (action === 'ban-demo') {
            event.preventDefault();
            showDemoFlash('Participant banned (demo). Check Bans tab.', 'danger');
            ensureDemoRoomData(getContext());
        }

        if (action === 'unban-demo') {
            event.preventDefault();
            showDemoFlash('Participant unbanned (demo).', 'success');
        }

        if (action === 'queue-answered') {
            event.preventDefault();
            const queuePanel = document.getElementById('queuePanel');
            const demoQueue = queuePanel?.querySelector('[data-onboarding-demo="queue"]');
            const historyList = ensureHistoryList();
            const historyItem = moveQueueItemToHistory(demoQueue) || addHistoryFeedbackDemo(historyList);
            if (demoQueue) {
                demoQueue.remove();
            }
            ensureHistoryOpen();
            if (historyItem) {
                historyItem.classList.add('onboarding-history-highlight');
                historyItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(() => historyItem.classList.remove('onboarding-history-highlight'), 2500);
            }
            showDemoFlash('Marked as answered (demo). Question moved to History; you can review answered questions there.', 'success');
            window.dispatchEvent(new CustomEvent('onboarding:demo', { detail: { event: 'queue-answered' } }));
        }
        if (action === 'queue-ignored') {
            event.preventDefault();
            showDemoFlash('Marked as ignored (demo).', 'info');
        }
        if (action === 'queue-later') {
            event.preventDefault();
            showDemoFlash('Marked for later (demo).', 'info');
        }

        if (action === 'feedback-clear') {
            event.preventDefault();
            showDemoFlash('Students marked as clear (demo).', 'success');
            window.dispatchEvent(new CustomEvent('onboarding:demo', { detail: { event: 'feedback-given' } }));
        }
        if (action === 'feedback-unclear') {
            event.preventDefault();
            showDemoFlash('Students marked as unclear (demo).', 'danger');
            window.dispatchEvent(new CustomEvent('onboarding:demo', { detail: { event: 'feedback-given' } }));
        }
    });
}

function removeCallouts() {
    document.querySelectorAll('.onboarding-callout, .onboarding-hint').forEach(removeElement);
}

function showCallout({ title, text, primaryLabel, secondaryLabel, onPrimary, onSecondary }) {
    removeCallouts();
    const box = document.createElement('div');
    box.className = 'onboarding-callout';
    box.innerHTML = `
        <div class="onboarding-callout__eyebrow">Onboarding</div>
        <h3 class="onboarding-callout__title">${title}</h3>
        <p class="onboarding-callout__text">${text}</p>
        <div class="onboarding-callout__actions"></div>
    `;

    const actions = box.querySelector('.onboarding-callout__actions');

    if (secondaryLabel && typeof onSecondary === 'function') {
        const secondaryBtn = document.createElement('button');
        secondaryBtn.type = 'button';
        secondaryBtn.className = 'onboarding-btn ghost';
        secondaryBtn.textContent = secondaryLabel;
        secondaryBtn.addEventListener('click', () => {
            onSecondary();
            removeElement(box);
        });
        actions.appendChild(secondaryBtn);
    }

    if (primaryLabel && typeof onPrimary === 'function') {
        const primaryBtn = document.createElement('button');
        primaryBtn.type = 'button';
        primaryBtn.className = 'onboarding-btn primary';
        primaryBtn.textContent = primaryLabel;
        primaryBtn.addEventListener('click', () => {
            onPrimary();
            removeElement(box);
        });
        actions.appendChild(primaryBtn);
    }

    document.body.appendChild(box);
}

function showHint(text, action) {
    removeCallouts();
    const hint = document.createElement('div');
    hint.className = 'onboarding-hint';

    const label = document.createElement('div');
    label.className = 'onboarding-hint__text';
    label.textContent = text;
    hint.appendChild(label);

    if (action?.label && typeof action.onClick === 'function') {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'onboarding-btn primary';
        btn.textContent = action.label;
        btn.addEventListener('click', action.onClick);
        hint.appendChild(btn);
    }

    document.body.appendChild(hint);

    setTimeout(() => removeElement(hint), 9000);
}

function ensureOverlay() {
    let overlay = document.querySelector('[data-onboarding-overlay]');
    if (overlay) return overlay;

    overlay = document.createElement('div');
    overlay.className = 'onboarding-overlay';
    overlay.dataset.onboardingOverlay = '1';
    overlay.setAttribute('hidden', 'hidden');
    overlay.innerHTML = `
        <div class="onboarding-backdrop"></div>
        <div class="onboarding-highlight"><div class="onboarding-pulse"></div></div>
        <div class="onboarding-tooltip">
            <div class="onboarding-tooltip__eyebrow">Step-by-step tour</div>
            <h3 class="onboarding-tooltip__title"></h3>
            <p class="onboarding-tooltip__text"></p>
            <div class="onboarding-progress"></div>
            <div class="onboarding-tooltip__actions">
                <button type="button" class="onboarding-btn ghost onboarding-skip" data-onboarding-skip>Skip</button>
                <div class="onboarding-tooltip__nav">
                    <button type="button" class="onboarding-btn ghost" data-onboarding-prev>Back</button>
                    <button type="button" class="onboarding-btn primary" data-onboarding-next>Next</button>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);
    return overlay;
}

function focusOnTarget(target, padding = 12, highlightEl) {
    if (!target || !highlightEl) return null;
    const rect = target.getBoundingClientRect();
    const width = Math.max(rect.width + padding * 2, 40);
    const height = Math.max(rect.height + padding * 2, 40);
    const left = Math.max(rect.left - padding, 8);
    const top = Math.max(rect.top - padding, 8);

    highlightEl.style.width = `${width}px`;
    highlightEl.style.height = `${height}px`;
    highlightEl.style.transform = `translate(${left}px, ${top}px)`;

    return { rect, width, height, left, top };
}

function positionTooltip(tooltip, anchor, preferred = 'bottom', offsetY = 0) {
    if (!tooltip || !anchor) return;
    tooltip.style.left = '0px';
    tooltip.style.top = '0px';
    const tooltipRect = tooltip.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const spacing = 14;

    let top = preferred === 'top'
        ? anchor.top - tooltipRect.height - spacing
        : anchor.top + anchor.height + spacing;

    if (top + tooltipRect.height > viewportHeight - 12) {
        top = anchor.top - tooltipRect.height - spacing;
    }
    if (top < 12) {
        top = anchor.top + anchor.height + spacing;
    }
    top += offsetY;

    let left = anchor.left;
    if (left + tooltipRect.width > viewportWidth - 12) {
        left = viewportWidth - tooltipRect.width - 12;
    }
    if (left < 12) {
        left = 12;
    }

    tooltip.style.left = `${left}px`;
    tooltip.style.top = `${top}px`;
}

function runStage(stageKey, ctx, state) {
    const stage = ONBOARDING_STAGES[stageKey];
    if (!stage || !Array.isArray(stage.steps) || !stage.steps.length) {
        return false;
    }

    if (stage.requireOwner && ctx.roomRole !== 'owner') {
        updateState({ status: 'completed', stepIndex: 0, stage: stageKey });
        return false;
    }

    if (ctx.routeName !== stage.route) {
        return false;
    }

    removeCallouts();

    const overlay = ensureOverlay();
    const highlight = overlay.querySelector('.onboarding-highlight');
    const tooltip = overlay.querySelector('.onboarding-tooltip');
    const titleEl = overlay.querySelector('.onboarding-tooltip__title');
    const textEl = overlay.querySelector('.onboarding-tooltip__text');
    const progressEl = overlay.querySelector('.onboarding-progress');
    const nextBtn = overlay.querySelector('[data-onboarding-next]');
    const prevBtn = overlay.querySelector('[data-onboarding-prev]');
    const skipBtn = overlay.querySelector('[data-onboarding-skip]');

    let stepIndex = Math.min(state.stepIndex || 0, stage.steps.length - 1);
    let activeTarget = null;
    let cleanupStep = null;
    let activeStep = null;
    let targetClickCleanup = null;
    let mutationObserver = null;
    let completionListener = null;
    let trackingFrame = null;
    window.__lcOnboardingActive = true;

    const stopTracking = () => {
        if (trackingFrame) {
            cancelAnimationFrame(trackingFrame);
            trackingFrame = null;
        }
    };

    const detach = () => {
        window.removeEventListener('resize', reposition);
        window.removeEventListener('scroll', reposition, true);
        document.removeEventListener('keydown', onKeyDown);
        if (cleanupStep) {
            cleanupStep();
            cleanupStep = null;
        }
        if (targetClickCleanup) {
            targetClickCleanup();
            targetClickCleanup = null;
        }
        if (activeTarget) {
            activeTarget.classList.remove('onboarding-target');
            activeTarget = null;
        }
        if (mutationObserver) {
            mutationObserver.disconnect();
            mutationObserver = null;
        }
        if (completionListener) {
            window.removeEventListener('onboarding:demo', completionListener);
            completionListener = null;
        }
        stopTracking();
    };

    const closeOverlay = () => {
        detach();
        overlay.classList.remove('active');
        overlay.setAttribute('hidden', 'hidden');
        document.body.classList.remove('onboarding-locked');
        window.__lcOnboardingActive = false;
    };

    const finishStage = () => {
        const nextStage = stage.next;
        if (nextStage) {
            updateState({ status: 'active', stage: nextStage, stepIndex: 0 });
            closeOverlay();
            if (stage.hint?.text) {
                const actionSelector = stage.hint.actionSelector;
                const actionTarget = actionSelector ? findFirst(actionSelector) : null;
                showHint(stage.hint.text, actionTarget ? {
                    label: 'Go',
                    onClick: () => actionTarget.click(),
                } : null);
            }
        } else {
            updateState({ status: 'completed', stepIndex: 0, stage: stageKey });
            closeOverlay();
            showHint('Tour finished. Follow the tips in chat and the question queue.');
        }
    };

    const onSkip = () => {
        updateState({ status: 'skipped', stepIndex: 0 });
        closeOverlay();
    };

    const bindTargetAdvance = (target, index) => {
        if (!target) return;
        if (targetClickCleanup) {
            targetClickCleanup();
            targetClickCleanup = null;
        }
        const handler = () => {
            const isLast = index >= stage.steps.length - 1;
            if (isLast) {
                finishStage();
            } else {
                goTo(index + 1);
            }
        };
        target.addEventListener('click', handler, { capture: true });
        targetClickCleanup = () => target.removeEventListener('click', handler, { capture: true });
    };

    const reposition = () => {
        if (!activeTarget || !activeStep) return;
        const geometry = focusOnTarget(activeTarget, activeStep.padding || 12, highlight);
        if (geometry) {
            positionTooltip(tooltip, geometry, activeStep.placement || 'bottom', activeStep.offsetY || 0);
        }
    };

    const startTracking = () => {
        stopTracking();
        const tick = () => {
            reposition();
            trackingFrame = requestAnimationFrame(tick);
        };
        trackingFrame = requestAnimationFrame(tick);
    };

    const onKeyDown = (event) => {
        if (event.key === 'Escape') {
            onSkip();
        } else if (event.key === 'ArrowRight') {
            goTo(stepIndex + 1);
        } else if (event.key === 'ArrowLeft') {
            goTo(stepIndex - 1);
        }
    };

    const goTo = (index) => {
        if (index >= stage.steps.length) {
            finishStage();
            return;
        }
        if (index < 0) {
            return;
        }

        stopTracking();

        const step = stage.steps[index];
        if (cleanupStep) {
            cleanupStep();
            cleanupStep = null;
        }

        if (typeof step.prepare === 'function') {
            cleanupStep = step.prepare() || null;
        }

        const target = step.selector ? document.querySelector(step.selector) : null;
        if (!target || !target.getBoundingClientRect().width) {
            if (cleanupStep) {
                cleanupStep();
                cleanupStep = null;
            }
            goTo(index + 1);
            return;
        }

        activeTarget = target;
        activeStep = step;
        stepIndex = index;
        updateState({ status: 'active', stage: stageKey, stepIndex });

        target.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });

        titleEl.textContent = step.title || '';
        textEl.textContent = step.body || '';
        progressEl.innerHTML = `<span class="onboarding-dot" aria-hidden="true"></span> Step ${index + 1} of ${stage.steps.length}`;
        nextBtn.textContent = index === stage.steps.length - 1 ? 'Done' : 'Next';

        if (step.completeEvent) {
            nextBtn.disabled = true;
            if (completionListener) {
                window.removeEventListener('onboarding:demo', completionListener);
            }
            completionListener = (evt) => {
                if (evt?.detail?.event === step.completeEvent) {
                    nextBtn.disabled = false;
                    goTo(index + 1);
                }
            };
            window.addEventListener('onboarding:demo', completionListener);
        } else {
            nextBtn.disabled = false;
            bindTargetAdvance(target, index);
        }

        requestAnimationFrame(() => {
            reposition();
            if (step.trackHighlight) {
                startTracking();
            }
        });

        if (!mutationObserver) {
            mutationObserver = new MutationObserver(() => {
                requestAnimationFrame(reposition);
            });
            mutationObserver.observe(document.body, { childList: true, subtree: true });
        }
    };

    nextBtn?.addEventListener('click', () => goTo(stepIndex + 1));
    prevBtn?.addEventListener('click', () => goTo(stepIndex - 1));
    skipBtn?.addEventListener('click', onSkip);
    document.addEventListener('keydown', onKeyDown);
    window.addEventListener('resize', reposition);
    window.addEventListener('scroll', reposition, true);

    overlay.removeAttribute('hidden');
    overlay.classList.add('active');
    document.body.classList.add('onboarding-locked');

    goTo(stepIndex);
    return true;
}

function showResume(state, stageKey, ctx) {
    const stage = ONBOARDING_STAGES[stageKey];
    if (!stage) return;
    let actionSelector = '';
    if (stage.route === 'dashboard') {
        actionSelector = 'a[href*="dashboard"]';
    } else if (stage.route === 'rooms.create') {
        actionSelector = '[data-onboarding-target="dashboard-create-room"], [data-onboarding-target="create-room-nav"]';
    }
    const actionTarget = actionSelector ? findFirst(actionSelector) : null;
    showCallout({
        title: 'Resume the tour?',
        text: 'We kept your progress. Open the right screen to continue the tips.',
        primaryLabel: actionTarget ? 'Go' : 'OK',
        secondaryLabel: 'Reset',
        onPrimary: () => {
            if (actionTarget) {
                actionTarget.click();
            } else if (stage.route === ctx.routeName) {
                runStage(stageKey, ctx, state);
            }
        },
        onSecondary: () => {
            updateState({ status: 'skipped', stepIndex: 0 });
        },
    });
}

function bootstrapOnboarding() {
    const ctx = getContext();
    if (isMobileViewport()) {
        return;
    }

    let state = readState();

    if (!ctx.hasRooms && (state.status === 'completed' || state.status === 'skipped')) {
        state = updateState({
            status: ONBOARDING_DEFAULT_STATE.status,
            stage: ONBOARDING_DEFAULT_STATE.stage,
            stepIndex: ONBOARDING_DEFAULT_STATE.stepIndex,
        });
    }

    const activeOnboarding = state.status === 'active';

    if (!ctx.newUser && !activeOnboarding) {
        if (state.status !== 'completed' && state.status !== 'skipped') {
            state = updateState({ status: 'skipped', stepIndex: 0 });
        }
        return;
    }

    if (state.status === 'completed' || state.status === 'skipped') {
        return;
    }

    const shouldInjectRoomLiveDemo = state.status === 'active'
        && state.stage === 'roomLive'
        && ctx.routeName === ONBOARDING_STAGES.roomLive.route
        && ctx.roomRole === 'owner';

    if (shouldInjectRoomLiveDemo) {
        ensureDemoRoomData(ctx);
    }

    setupDemoActionHandlers();

    const currentStage = ONBOARDING_STAGES[state.stage] ?? ONBOARDING_STAGES.intro;

    if (state.status === 'active') {
        const started = runStage(state.stage, ctx, state);
        state = readState();
        if (started) {
            return;
        }
        if (state.status === 'completed' || state.status === 'skipped') {
            return;
        }
        showResume(state, state.stage, ctx);
        return;
    }

    if (!ctx.newUser) {
        return;
    }

    if (ctx.routeName === currentStage.route) {
        showCallout({
            title: ctx.userName ? `${ctx.userName}, want a quick tour?` : 'Want a quick tour?',
            text: 'We will help you create the first room, enable question sounds, and handle moderation.',
            primaryLabel: 'Start tour',
            secondaryLabel: 'Not now',
            onPrimary: () => {
                state = updateState({ status: 'active', stage: 'intro', stepIndex: 0 });
                runStage('intro', ctx, state);
            },
            onSecondary: () => {
                state = updateState({ status: 'skipped' });
            },
        });
    }
}

document.addEventListener('DOMContentLoaded', bootstrapOnboarding);
