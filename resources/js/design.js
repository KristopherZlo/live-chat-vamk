import * as QRCode from 'qrcode';

const THEME_KEY = 'lc-theme';
const QUEUE_SEEN_KEY_PREFIX = 'lc-queue-seen';
const QUEUE_SOUND_KEY = 'lc-queue-sound';
const QUEUE_SOUND_DEBUG_KEY = 'lc-queue-sound-debug';
const QUEUE_FILTER_KEY_PREFIX = 'lc-queue-filter';
const HOUR_MS = 60 * 60 * 1000;
const WHATS_NEW_STORAGE_KEY = 'lc-whats-new-version';

let queueSoundPlayer = null;
let queueSoundPlayerSrc = null;
let queueSoundPreference = true;
let queueSoundPrimed = false;
let queueSoundPrimeHandlerBound = false;

const IS_DEV = import.meta?.env?.DEV;

function isQueueSoundDebugEnabled() {
  if (!IS_DEV || typeof window === 'undefined') return false;
  if (window.__queueSoundDebug === true) return true;
  try {
    return localStorage.getItem(QUEUE_SOUND_DEBUG_KEY) === '1';
  } catch (e) {
    return false;
  }
}

function logQueueSound(...args) {
  if (!isQueueSoundDebugEnabled()) return;
  // eslint-disable-next-line no-console
  console.debug(...args);
}

function createQrModules(link, canvasSize, options = {}) {
  const text = typeof link === 'string' ? link.trim() : '';
  if (!text || !canvasSize) return null;

  const quietModules = Number.isFinite(options.quietModules)
    ? Math.max(0, Math.min(16, Math.floor(options.quietModules)))
    : 4;
  const errorCorrectionLevel = options.errorCorrectionLevel || 'H';

  let qr;
  try {
    qr = QRCode.create(text, { errorCorrectionLevel });
  } catch (error) {
    return null;
  }

  const baseCount = qr?.modules?.size;
  if (!Number.isFinite(baseCount) || baseCount <= 0 || typeof qr.modules.get !== 'function') {
    return null;
  }

  const moduleCount = baseCount + quietModules * 2;
  const moduleSize = canvasSize / moduleCount;
  const offset = (canvasSize - moduleSize * moduleCount) / 2;

  const modules = [];
  for (let row = 0; row < moduleCount; row += 1) {
    const rowValues = [];
    for (let col = 0; col < moduleCount; col += 1) {
      const baseRow = row - quietModules;
      const baseCol = col - quietModules;
      const isInside = baseRow >= 0 && baseCol >= 0 && baseRow < baseCount && baseCol < baseCount;
      rowValues.push(isInside ? !!qr.modules.get(baseRow, baseCol) : false);
    }
    modules.push(rowValues);
  }

  return {
    modules,
    moduleSize,
    moduleCount,
    offset,
  };
}

window.createQrModules = createQrModules;

function normalizeId(value) {
  const num = Number(value);
  return Number.isInteger(num) && num > 0 ? num : null;
}

function syncLogoutForms() {
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  if (!token) return;
  document.querySelectorAll('form[action$="logout"]').forEach((form) => {
    let input = form.querySelector('input[name="_token"]');
    if (!input) {
      input = document.createElement('input');
      input.type = 'hidden';
      input.name = '_token';
      form.appendChild(input);
    }
    input.value = token;
  });
}

function getQueuePanel(root = document) {
  if (root && typeof root.querySelector === 'function') {
    const found = root.querySelector('#queuePanel');
    if (found) return found;
  }
  return document.getElementById('queuePanel');
}

function getQueueStorageKey(queuePanel = getQueuePanel()) {
  if (!queuePanel) return null;
  const roomKey = queuePanel.dataset.roomSlug || queuePanel.dataset.roomId;
  const viewerId = queuePanel.dataset.viewerId || 'viewer';
  if (!roomKey) return null;
  return `${QUEUE_SEEN_KEY_PREFIX}:${roomKey}:${viewerId}`;
}

function getQueueFilterStorageKey(queuePanel = getQueuePanel()) {
  if (!queuePanel) return null;
  const roomKey = queuePanel.dataset.roomSlug || queuePanel.dataset.roomId;
  return roomKey ? `${QUEUE_FILTER_KEY_PREFIX}:${roomKey}` : null;
}

function loadQueueSeenState(queuePanel = getQueuePanel()) {
  const storageKey = getQueueStorageKey(queuePanel);
  let seenIds = window.queueSeenQuestionIds;

  const needsLoad = !seenIds || window.queueSeenQuestionIdsKey !== storageKey;
  if (needsLoad) {
    seenIds = new Set();
    if (storageKey) {
      try {
        const stored = localStorage.getItem(storageKey);
        if (stored) {
          const parsed = JSON.parse(stored);
          if (Array.isArray(parsed)) {
            parsed.forEach((value) => {
              const id = normalizeId(value);
              if (id) {
                seenIds.add(id);
              }
            });
          }
        }
      } catch (e) {
        /* ignore */
      }
    }
    window.queueSeenQuestionIds = seenIds;
    window.queueSeenQuestionIdsKey = storageKey;
  }

  return { storageKey, seenIds };
}

function persistQueueSeenState(storageKey, seenIds) {
  if (!storageKey || !seenIds) return;
  try {
    localStorage.setItem(storageKey, JSON.stringify(Array.from(seenIds)));
  } catch (e) {
    /* ignore */
  }
}

function updateQueueBadge(queuePanel = getQueuePanel()) {
  if (!queuePanel) return;
  const doc = queuePanel.ownerDocument || document;
  const hasNew = queuePanel.querySelector('.queue-item.queue-item-new');
  let badge = doc.getElementById('queueNewBadge');

  queuePanel.classList.toggle('has-new', !!hasNew);

  if (hasNew && !badge) {
    badge = doc.createElement('span');
    badge.id = 'queueNewBadge';
    badge.className = 'queue-new-badge';
    badge.innerHTML = '<span>New</span>';
    const headerExtra = queuePanel.querySelector('.queue-header-extra');
    if (headerExtra) {
      headerExtra.prepend(badge);
    } else {
      queuePanel.prepend(badge);
    }
  } else if (!hasNew && badge) {
    badge.remove();
  }
}

function loadQueueSoundSetting() {
  let enabled = true;
  try {
    const stored = localStorage.getItem(QUEUE_SOUND_KEY);
    if (stored === 'off') {
      enabled = false;
    } else if (stored === 'on') {
      enabled = true;
    }
  } catch (e) {
    /* ignore */
  }
  queueSoundPreference = enabled;
  return enabled;
}

function persistQueueSoundSetting(enabled) {
  const value = enabled ? 'on' : 'off';
  try {
    localStorage.setItem(QUEUE_SOUND_KEY, value);
  } catch (e) {
    /* ignore */
  }
}

function isQueueSoundEnabled() {
  return typeof queueSoundPreference === 'boolean'
    ? queueSoundPreference
    : loadQueueSoundSetting();
}

function ensureQueueSoundPlayer(url) {
  const src = url || window.queueSoundUrl || queueSoundPlayerSrc;
  if (!src) return null;
  if (!queueSoundPlayer || queueSoundPlayerSrc !== src) {
    try {
      queueSoundPlayer = new Audio(src);
      queueSoundPlayer.preload = 'auto';
      queueSoundPlayerSrc = src;
      queueSoundPlayer.addEventListener('error', () => {
        logQueueSound('[queue-sound] audio error', queueSoundPlayer?.error);
      });
      queueSoundPlayer.addEventListener('canplaythrough', () => {
        logQueueSound('[queue-sound] audio canplaythrough');
      });
      queueSoundPlayer.addEventListener('play', () => {
        logQueueSound('[queue-sound] audio play event');
      });
    } catch (e) {
      queueSoundPlayer = null;
      logQueueSound('[queue-sound] create audio failed', e);
    }
  }
  return queueSoundPlayer;
}

function playQueueSound(url) {
  if (!isQueueSoundEnabled()) {
    logQueueSound('[queue-sound] disabled by user setting');
    return;
  }
  const player = ensureQueueSoundPlayer(url);
  if (!player) return;
  try {
    logQueueSound('[queue-sound] playQueueSound', {
      src: player.src,
      readyState: player.readyState,
      muted: player.muted,
      volume: player.volume,
    });
    player.currentTime = 0;
    player.play().catch((err) => {
      logQueueSound('[queue-sound] play() promise rejected', err);
    });
  } catch (e) {
    /* ignore */
  }
}

function primeQueueSound(url) {
  if (queueSoundPrimed) return;
  const player = ensureQueueSoundPlayer(url);
  if (!player) return;

  const previousMute = player.muted;
  const restore = () => {
    player.muted = previousMute;
  };

  try {
    player.muted = true;
    const attempt = player.play();
    if (attempt && typeof attempt.then === 'function') {
      attempt
        .then(() => {
          queueSoundPrimed = true;
          player.pause();
          player.currentTime = 0;
          logQueueSound('[queue-sound] prime succeeded');
        })
        .catch((err) => {
          logQueueSound('[queue-sound] prime failed', err);
        })
        .finally(restore);
    } else {
      restore();
    }
  } catch (e) {
    restore();
  }
}

function setupSoundPriming(url) {
  if (queueSoundPrimed || queueSoundPrimeHandlerBound) return;
  queueSoundPrimeHandlerBound = true;

  const handler = () => {
    primeQueueSound(url);
    if (queueSoundPrimed) {
      ['pointerdown', 'touchstart', 'keydown'].forEach((eventName) => {
        window.removeEventListener(eventName, handler);
      });
      queueSoundPrimeHandlerBound = false;
    }
  };

  ['pointerdown', 'touchstart', 'keydown'].forEach((eventName) => {
    window.addEventListener(eventName, handler, { passive: true });
  });
}

function setupQueueSoundToggle() {
  const toggles = document.querySelectorAll('[data-queue-sound-toggle]');
  if (!toggles.length) return;

  let enabled = loadQueueSoundSetting();

  const syncUI = () => {
    toggles.forEach((btn) => {
      const stateEl = btn.querySelector('[data-sound-state]');
      if (stateEl) {
        stateEl.textContent = enabled ? 'On' : 'Off';
      }
      btn.classList.toggle('off', !enabled);
    });
  };

  toggles.forEach((btn) => {
    btn.addEventListener('click', () => {
      enabled = !enabled;
      queueSoundPreference = enabled;
      persistQueueSoundSetting(enabled);
      syncUI();
      if (enabled) {
        playQueueSound(window.queueSoundUrl);
      }
    });
  });

  syncUI();
}

function initQueueSoundPlayer(url) {
  ensureQueueSoundPlayer(url);
  // Try to prime immediately (muted play), then also bind user-gesture priming as fallback.
  primeQueueSound(url);
  setupSoundPriming(url);
}

function refreshLucideIcons(root = document) {
  if (!window.lucide) return;

  const target = root instanceof Element
    ? root
    : (root && root.documentElement) || document;

  if (typeof window.lucide.createIcons === 'function') {
    try {
      window.lucide.createIcons({ icons: window.lucide.icons }, target);
      return;
    } catch (e) {
      /* fallback to manual rendering below */
    }
  }

  const doc = target.ownerDocument || document;
  const icons = window.lucide.icons || {};
  const nodes = typeof target.querySelectorAll === 'function'
    ? target.querySelectorAll('[data-lucide]')
    : [];

  nodes.forEach((node) => {
    const name = node.getAttribute('data-lucide');
    const iconDef = icons[name];
    if (!iconDef || typeof iconDef.toSvg !== 'function') return;

    const wrapper = doc.createElement('div');
    wrapper.innerHTML = iconDef.toSvg();
    const svg = wrapper.firstElementChild;
    if (!svg) return;

    const attrs = node.getAttributeNames();
    attrs.forEach((attr) => {
      if (attr === 'data-lucide') return;
      const value = node.getAttribute(attr);
      if (value !== null) {
        svg.setAttribute(attr, value);
      }
    });
    svg.classList.add(...node.classList);
    node.replaceWith(svg);
  });
}
window.refreshLucideIcons = refreshLucideIcons;

function applyTheme(theme) {
  const normalized = theme === 'dark' ? 'dark' : 'light';
  document.body.dataset.theme = normalized;
  document.documentElement.dataset.theme = normalized;
  document.documentElement.style.backgroundColor = normalized === 'dark' ? '#000000' : '#ffffff';
  try {
    localStorage.setItem(THEME_KEY, normalized);
  } catch (e) {
    /* ignore */
  }
  document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
    btn.setAttribute('aria-pressed', normalized === 'dark');
  });
  refreshLucideIcons();
}

function initTheme() {
  let preferred = 'light';
  try {
    const stored = localStorage.getItem(THEME_KEY);
    if (stored === 'dark' || stored === 'light') {
      preferred = stored;
    } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
      preferred = 'dark';
    }
  } catch (e) {
    /* ignore */
  }
  applyTheme(preferred);
}

function setupThemeToggle() {
  document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const next = document.body.dataset.theme === 'dark' ? 'light' : 'dark';
      applyTheme(next);
    });
  });
}

function setupCopyButtons() {
  document.querySelectorAll('[data-copy]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const value = btn.dataset.copy;
      if (!value) return;
      const notify = (success) => {
        showFlashNotification(success ? 'Link copied to clipboard' : 'Unable to copy link', {
          type: success ? 'success' : 'danger',
          source: 'room-copy-link',
        });
      };

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(value)
          .then(() => notify(true))
          .catch(() => {
            fallbackCopy(value, notify);
          });
      } else {
        fallbackCopy(value, notify);
      }
      btn.classList.add('pulse');
      setTimeout(() => btn.classList.remove('pulse'), 300);
    });
  });
}

function fallbackCopy(value, notify) {
  try {
    const textarea = document.createElement('textarea');
    textarea.value = value;
    textarea.setAttribute('readonly', 'true');
    textarea.style.position = 'absolute';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    textarea.remove();
    if (typeof notify === 'function') notify(true);
  } catch (e) {
    if (typeof notify === 'function') notify(false);
  }
}

function setupMobileMenu() {
  const menuOverlay = document.getElementById('mobileMenu');
  const menuBtn = document.getElementById('mobileMenuBtn');
  const menuFab = document.getElementById('mobileMenuBottom');
  const menuTabs = document.getElementById('mobileMenuTabsBtn');
  const closeBtns = document.querySelectorAll('[data-close-menu]');

  const setOpen = (open) => {
    if (!menuOverlay) return;
    menuOverlay.classList.toggle('show', open);
  };

  const toggle = () => setOpen(!menuOverlay?.classList.contains('show'));

  [menuBtn, menuFab, menuTabs].forEach((btn) => {
    if (btn) btn.addEventListener('click', toggle);
  });

  if (menuOverlay) {
    menuOverlay.addEventListener('click', (e) => {
      if (e.target === menuOverlay) {
        setOpen(false);
      }
    });
  }

  closeBtns.forEach((btn) => btn.addEventListener('click', () => setOpen(false)));
}

function setupUserMenus() {
  const menus = Array.from(document.querySelectorAll('details.user-menu'));
  if (!menus.length) return;

  menus.forEach((menu) => {
    const summary = menu.querySelector('summary');
    if (!summary) return;
    summary.addEventListener('click', () => {
      menus.forEach((other) => {
        if (other !== menu) {
          other.removeAttribute('open');
        }
      });
    });
  });

  document.addEventListener('click', (event) => {
    menus.forEach((menu) => {
      if (!menu.open) return;
      if (!menu.contains(event.target)) {
        menu.removeAttribute('open');
      }
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    menus.forEach((menu) => {
      if (menu.open) {
        menu.removeAttribute('open');
      }
    });
  });
}

function setupMobileTabs() {
  const tabButtons = document.querySelectorAll('[data-tab-target]');
  if (!tabButtons.length) return;

  const initial = Array.from(tabButtons).find((btn) => btn.classList.contains('active'));
  let active = initial ? initial.dataset.tabTarget : tabButtons[0].dataset.tabTarget;
  const MOBILE_BREAKPOINT = 768;

  const sync = () => {
    const panels = document.querySelectorAll('[data-mobile-panel]');
    if (!panels.length) return;
    const isMobile = window.innerWidth <= MOBILE_BREAKPOINT;
    panels.forEach((panel) => {
      const match = panel.dataset.mobilePanel === active;
      panel.classList.toggle('mobile-active', !isMobile || match);
      panel.hidden = isMobile ? !match : false;
    });
    tabButtons.forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.tabTarget === active);
    });
  };

  tabButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      active = btn.dataset.tabTarget;
      sync();
    });
  });

  window.addEventListener('resize', sync);
  sync();
}

function setupChatEnterSubmit() {
  const form = document.getElementById('chat-form');
  const textarea = document.getElementById('chatInput');
  if (!form || !textarea) return;

  form.addEventListener('submit', () => {
    textarea.value = '';
    textarea.style.height = '';
  });

  textarea.addEventListener('keydown', (event) => {
    if (event.key === ' ' || event.key === 'Enter') {
      event.stopPropagation();
    }
    const isEnter = event.key === 'Enter';
    if (!isEnter || event.shiftKey || event.isComposing) return;
    event.preventDefault();
    if (typeof form.requestSubmit === 'function') {
      form.requestSubmit();
    } else {
      form.submit();
    }
  });
}

function setupQueueFilter(root = document) {
  const queuePanel = getQueuePanel(root);
  if (!queuePanel) return;
  const filter = queuePanel.querySelector('[data-queue-filter]');
  if (!filter) return;
  const list = queuePanel.querySelector('.queue-list');
  const isRemoteQueue = queuePanel.dataset.queueRemote === '1';
  if (!isRemoteQueue && !list) return;

  const emptyState = queuePanel.querySelector('[data-queue-filter-empty]');
  const storageKey = getQueueFilterStorageKey(queuePanel);
  const dispatchFilterChange = (value, meta = {}) => {
    const event = new CustomEvent('queue:filter-change', {
      detail: { value, ...meta },
      bubbles: true,
    });
    queuePanel.dispatchEvent(event);
  };

  const loadStoredFilter = () => {
    if (!storageKey) return null;
    try {
      const stored = localStorage.getItem(storageKey);
      return typeof stored === 'string' && stored.length ? stored : null;
    } catch (e) {
      return null;
    }
  };

  const persistFilter = (value) => {
    if (!storageKey) return;
    try {
      localStorage.setItem(storageKey, value);
    } catch (e) {
      /* ignore */
    }
  };

  const applyFilter = (meta = {}) => {
    const value = (filter.value || 'new').toLowerCase();
    if (isRemoteQueue) {
      dispatchFilterChange(value, meta);
      return;
    }

    const items = Array.from(list.querySelectorAll('.queue-item'));
    let visible = 0;

    items.forEach((item) => {
      const status = (item.dataset.status || '').toLowerCase();
      const matches = value === 'all' ? true : status === value;
      item.hidden = !matches;
      if (matches) visible += 1;
    });

    if (emptyState) {
      emptyState.hidden = visible > 0;
    }
    list.classList.toggle('queue-list-filter-empty', visible === 0);
    if (typeof window.maybeAutoloadQueue === 'function') {
      window.maybeAutoloadQueue(true);
    }
    dispatchFilterChange(value, meta);
  };

  const stored = loadStoredFilter();
  if (stored) {
    const option = Array.from(filter.options).find((opt) => (opt.value || '').toLowerCase() === stored.toLowerCase());
    if (option) {
      filter.value = option.value;
    }
  }
  if (filter.dataset.queueFilterBound === '1') {
    if (!isRemoteQueue) {
      applyFilter({ initial: false });
    }
    return;
  }

  filter.dataset.queueFilterBound = '1';
  filter.addEventListener('change', () => {
    persistFilter(filter.value || 'new');
    applyFilter({ initial: false });
  });
  applyFilter({ initial: true });
}

function setupQueueNewHandlers(root = document) {
  const queuePanel = getQueuePanel(root);
  const scope = queuePanel || root;
  const queueItems = scope.querySelectorAll('.queue-item');
  const { storageKey, seenIds } = loadQueueSeenState(queuePanel);
  if (!queueItems.length) {
    updateQueueBadge(queuePanel);
    return;
  }

  const persistSeen = (id) => {
    if (!id || seenIds.has(id)) return false;
    seenIds.add(id);
    persistQueueSeenState(storageKey, seenIds);
    return true;
  };

  const markSeen = (id) => {
    if (!persistSeen(id)) return;
    updateQueueBadge(queuePanel);
  };

  queueItems.forEach((item) => {
    const id = normalizeId(item.dataset.questionId);
    const status = (item.dataset.status || '').toLowerCase();
    const isNewStatus = status === 'new';
    const isSeen = id && seenIds.has(id);

    if (!isNewStatus || isSeen) {
      item.classList.remove('queue-item-new');
    } else if (isNewStatus) {
      item.classList.add('queue-item-new');
    }

    item.addEventListener('click', () => {
      if (!id || !item.classList.contains('queue-item-new')) return;
      item.classList.remove('queue-item-new');
      markSeen(id);
    });
  });

  updateQueueBadge(queuePanel);
}

function markQueueItemSeen(questionId, root = document) {
  const queuePanel = getQueuePanel(root);
  const { storageKey, seenIds } = loadQueueSeenState(queuePanel);
  const id = normalizeId(questionId);
  if (!id || !seenIds || seenIds.has(id)) return;

  seenIds.add(id);
  persistQueueSeenState(storageKey, seenIds);

  if (queuePanel) {
    const item = queuePanel.querySelector(`.queue-item[data-question-id="${id}"]`);
    if (item) {
      item.classList.remove('queue-item-new');
    }
    updateQueueBadge(queuePanel);
  }
}

function markQueueHasNew() {
  const queuePanel = document.getElementById('queuePanel');
  if (queuePanel) {
    setupQueueNewHandlers(queuePanel);
  }
}

function ensureFlashContainer() {
  let container = document.querySelector('.flash-toaster');
  if (!container) {
    container = document.createElement('div');
    container.className = 'flash-toaster';
    document.body.appendChild(container);
  }
  return container;
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  }[char] ?? char));
}

function setupFlashMessages(root = document) {
  const toaster = ensureFlashContainer();
  const flashes = [];
  if (root instanceof Element && root.matches('[data-flash]')) {
    flashes.push(root);
  }
  flashes.push(...Array.from(root.querySelectorAll('[data-flash]')));
  flashes.forEach((flash) => {
    if (!flash.classList.contains('flash-toast')) {
      flash.classList.add('flash-toast');
    }
    if (!flash.parentElement || !flash.parentElement.classList.contains('flash-toaster')) {
      toaster.appendChild(flash);
    }
    const duration = Number(flash.dataset.flashDuration || '4500');
    flash.dataset.flashDuration = String(duration);
    if (!flash.querySelector('.flash-progress')) {
      const bar = document.createElement('div');
      bar.className = 'flash-progress';
      bar.innerHTML = '<span></span>';
      flash.appendChild(bar);
    }
    const progress = flash.querySelector('.flash-progress');
    if (progress) {
      progress.style.setProperty('--flash-duration', `${duration}ms`);
    }
    const closeBtn = flash.querySelector('[data-flash-close]');
    let createdClose = false;
    if (!closeBtn) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'icon-btn flash-close';
      btn.setAttribute('aria-label', 'Close');
      btn.dataset.flashClose = '1';
      btn.innerHTML = '<i data-lucide="x"></i>';
      flash.appendChild(btn);
      createdClose = true;
    }
    const hide = () => {
      flash.classList.add('hidden');
      setTimeout(() => flash.remove(), 250);
    };
    const close = flash.querySelector('[data-flash-close]');
    if (close) {
      close.addEventListener('click', hide, { once: true });
    }
    if (duration > 0) {
      setTimeout(hide, duration);
    }
    if (createdClose && window.refreshLucideIcons) {
      refreshLucideIcons(flash);
    }
  });
}

function showFlashNotification(message, options = {}) {
  const { type = 'success', source, duration = 4500 } = options;
  const container = ensureFlashContainer();
  const normalizedMessage = String(message ?? '');
  const safeMessage = escapeHtml(normalizedMessage);
  if (source) {
    const existing = container.querySelector(`[data-flash-source="${source}"]`);
    if (existing) {
      const span = existing.querySelector('.flash-text');
      if (span) {
        span.textContent = normalizedMessage;
      }
      existing.className = `flash flash-${type} flash-toast`;
      existing.dataset.flashDuration = String(duration);
      const bar = existing.querySelector('.flash-progress');
      if (bar) {
        bar.style.setProperty('--flash-duration', `${duration}ms`);
      }
      setupFlashMessages(existing);
      if (window.refreshLucideIcons) {
        refreshLucideIcons(existing);
      }
      return existing;
    }
  }

  const flash = document.createElement('div');
  flash.className = `flash flash-${type} flash-toast`;
  flash.dataset.flash = '1';
  flash.dataset.flashDuration = String(duration);
  if (source) flash.dataset.flashSource = source;

  flash.innerHTML = `
    <div class="flash-body">
      <div class="flash-kicker">System notification</div>
      <div class="flash-text">${safeMessage}</div>
    </div>
    <button class="icon-btn flash-close" type="button" aria-label="Close" data-flash-close>
      <i data-lucide="x"></i>
    </button>
    <div class="flash-progress"><span></span></div>
  `;

  container.appendChild(flash);
  setupFlashMessages(flash);
  if (window.refreshLucideIcons) {
    refreshLucideIcons(flash);
  }
  return flash;
}

const NETWORK_STATUS_OFFLINE_SOURCE = 'network-status-offline';
const NETWORK_STATUS_ONLINE_SOURCE = 'network-status-online';
const NETWORK_STATUS_OFFLINE_MESSAGE = 'Internet connection lost. Some features may be unavailable.';
const NETWORK_STATUS_ONLINE_MESSAGE = 'Internet connection restored.';

function clearNetworkOfflineNotice() {
  const container = document.querySelector('.flash-toaster');
  if (!container) return;
  const offlineNotice = container.querySelector(`[data-flash-source="${NETWORK_STATUS_OFFLINE_SOURCE}"]`);
  if (offlineNotice) {
    offlineNotice.remove();
  }
}

function setupNetworkStatusNotification() {
  if (typeof window === 'undefined' || window.__networkStatusNotificationBound) {
    return;
  }
  window.__networkStatusNotificationBound = true;

  const showOfflineNotice = () => {
    showFlashNotification(NETWORK_STATUS_OFFLINE_MESSAGE, {
      type: 'danger',
      duration: 0,
      source: NETWORK_STATUS_OFFLINE_SOURCE,
    });
  };

  const showOnlineNotice = () => {
    clearNetworkOfflineNotice();
    showFlashNotification(NETWORK_STATUS_ONLINE_MESSAGE, {
      type: 'success',
      duration: 3500,
      source: NETWORK_STATUS_ONLINE_SOURCE,
    });
  };

  if (typeof navigator !== 'undefined' && !navigator.onLine) {
    showOfflineNotice();
  }

  window.addEventListener('offline', showOfflineNotice);
  window.addEventListener('online', showOnlineNotice);
}

// Expose for console/tests
if (typeof window !== 'undefined') {
  window.showFlashNotification = showFlashNotification;
  window.setupFlashMessages = setupFlashMessages;
  window.setupNetworkStatusNotification = setupNetworkStatusNotification;
  setupNetworkStatusNotification();
}

function setupInlineEditors(root = document) {
  const blocks = root.querySelectorAll('[data-inline-edit]');
  blocks.forEach((block) => {
    const trigger = block.querySelector('[data-inline-trigger]');
    const form = block.querySelector('.inline-edit-form');
    const display = block.querySelector('.inline-edit-display');
    if (!trigger || !form || !display) return;

    const cancel = block.querySelector('[data-inline-cancel]');
    const input = form.querySelector('input, textarea');

    const show = () => {
      display.hidden = true;
      form.hidden = false;
      trigger.classList.add('active');
      if (input) {
        input.focus();
        if (typeof input.select === 'function') {
          input.select();
        }
      }
    };

    const hide = () => {
      form.hidden = true;
      display.hidden = false;
      trigger.classList.remove('active');
    };

    trigger.addEventListener('click', (event) => {
      event.preventDefault();
      show();
    });

    if (cancel) {
      cancel.addEventListener('click', (event) => {
        event.preventDefault();
        hide();
      });
    }
  });
}

function setupRoomDescriptions(root = document) {
  const descriptions = root.querySelectorAll('[data-room-description]');
  descriptions.forEach((desc) => {
    if (desc.dataset.roomDescriptionBound === '1') return;
    desc.dataset.roomDescriptionBound = '1';

    const apply = () => {
      const collapsed = desc.dataset.collapsed !== 'false';
      desc.classList.toggle('is-collapsed', collapsed);
      desc.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    };

    const toggle = () => {
      const collapsed = desc.dataset.collapsed !== 'false';
      desc.dataset.collapsed = collapsed ? 'false' : 'true';
      apply();
    };

    desc.addEventListener('click', toggle);
    desc.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' && event.key !== ' ') return;
      event.preventDefault();
      toggle();
    });

    apply();
  });
}

function setupRoomDeleteModals() {
  const modals = Array.from(document.querySelectorAll('[data-room-delete-modal]'));
  const triggers = document.querySelectorAll('[data-room-delete-trigger]');
  if (!modals.length || !triggers.length) return;

  const syncState = (modal) => {
    const input = modal.querySelector('[data-room-delete-input]');
    const submit = modal.querySelector('[data-room-delete-submit]');
    const expected = input ? input.dataset.roomTitle || '' : '';
    if (submit) {
      submit.disabled = !input || input.value !== expected;
    }
  };

  const closeModal = (modal) => {
    if (!modal || modal.hasAttribute('hidden')) return;
    modal.classList.remove('show');
    setTimeout(() => {
      modal.hidden = true;
      const anyOpen = modals.some((m) => !m.hasAttribute('hidden'));
      if (!anyOpen) {
        document.body.classList.remove('modal-open');
      }
    }, 140);
  };

  const openModal = (modal) => {
    if (!modal) return;
    modal.hidden = false;
    requestAnimationFrame(() => modal.classList.add('show'));
    document.body.classList.add('modal-open');
    const input = modal.querySelector('[data-room-delete-input]');
    if (input) {
      input.value = '';
      syncState(modal);
      input.focus({ preventScroll: true });
    }
  };

  modals.forEach((modal) => {
    const input = modal.querySelector('[data-room-delete-input]');
    if (input) {
      input.addEventListener('input', () => syncState(modal));
    }

    modal.addEventListener('click', (event) => {
      if (event.target === modal) {
        closeModal(modal);
      }
    });

    modal.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        closeModal(modal);
      }
    });

    modal.querySelectorAll('[data-room-delete-close]').forEach((btn) => {
      btn.addEventListener('click', () => closeModal(modal));
    });

    syncState(modal);
  });

  triggers.forEach((trigger) => {
    const modal = document.querySelector(
      `[data-room-delete-modal="${trigger.dataset.roomDeleteTrigger}"]`,
    );
    if (!modal) return;
    trigger.addEventListener('click', () => openModal(modal));
  });
}

function normalizeVersionSegments(value) {
  if (!value) {
    return [];
  }
  return value
    .split(/[^0-9]+/)
    .filter((segment) => segment.length)
    .map((segment) => Number.parseInt(segment, 10) || 0);
}

function compareVersions(a, b) {
  const segmentsA = normalizeVersionSegments(a);
  const segmentsB = normalizeVersionSegments(b);
  const length = Math.max(segmentsA.length, segmentsB.length);
  for (let i = 0; i < length; i += 1) {
    const valueA = typeof segmentsA[i] === 'number' ? segmentsA[i] : 0;
    const valueB = typeof segmentsB[i] === 'number' ? segmentsB[i] : 0;
    if (valueA > valueB) return 1;
    if (valueA < valueB) return -1;
  }
  return 0;
}

function readStoredWhatsNewVersion() {
  try {
    return localStorage.getItem(WHATS_NEW_STORAGE_KEY);
  } catch (error) {
    return null;
  }
}

function persistWhatsNewVersion(version) {
  if (!version) return;
  try {
    localStorage.setItem(WHATS_NEW_STORAGE_KEY, version);
  } catch (error) {
    /* ignore */
  }
}

function shouldShowWhatsNewModal(version) {
  if (!version) return false;
  const storedVersion = readStoredWhatsNewVersion();
  if (!storedVersion) return true;
  return compareVersions(version, storedVersion) === 1;
}

function setupWhatsNewModal(root = document) {
  const modal = root.querySelector('[data-whats-new-modal]');
  if (!modal) return;

  const version = modal.dataset.whatsNewVersion;
  if (!version || !shouldShowWhatsNewModal(version)) {
    return;
  }

  let closed = false;

  const closeModal = () => {
    if (closed || !modal || modal.hasAttribute('hidden')) return;
    closed = true;
    modal.classList.remove('show');
    setTimeout(() => {
      modal.hidden = true;
      const hasShowing = document.querySelector('.modal-overlay.show');
      if (!hasShowing) {
        document.body.classList.remove('modal-open');
      }
    }, 140);
    persistWhatsNewVersion(version);
  };

  const openModal = () => {
    modal.hidden = false;
    requestAnimationFrame(() => {
      modal.classList.add('show');
      if (typeof modal.focus === 'function') {
        modal.focus({ preventScroll: true });
      }
    });
    document.body.classList.add('modal-open');
  };

  modal.querySelectorAll('[data-whats-new-close]').forEach((button) => {
    button.addEventListener('click', () => closeModal());
  });

  openModal();
}

function getGreetingByHour(date = new Date()) {
  const hour = date.getHours();
  if (hour >= 5 && hour < 12) return 'Good morning';
  if (hour >= 12 && hour < 17) return 'Good afternoon';
  if (hour >= 17 && hour < 22) return 'Good evening';
  return 'Good night';
}

function updateDashboardGreeting() {
  const greetingEl = document.getElementById('dashboardGreeting');
  if (!greetingEl) return;
  const name = greetingEl.dataset.username || '';
  const greeting = getGreetingByHour();
  greetingEl.textContent = name ? `${greeting}, ${name}` : greeting;
}

function scheduleGreetingRefresh() {
  if (!document.getElementById('dashboardGreeting')) return;
  const now = new Date();
  const msElapsedThisHour = now.getMinutes() * 60 * 1000 + now.getSeconds() * 1000 + now.getMilliseconds();
  const msToNextHour = Math.max(HOUR_MS - msElapsedThisHour, 0);

  setTimeout(() => {
    updateDashboardGreeting();
    setInterval(updateDashboardGreeting, HOUR_MS);
  }, msToNextHour);
}

document.addEventListener('DOMContentLoaded', () => {
  loadQueueSoundSetting();
  initTheme();
  setupThemeToggle();
  setupCopyButtons();
  setupMobileMenu();
  setupUserMenus();
  setupQueueSoundToggle();
  setupSoundPriming(window.queueSoundUrl);
  setupMobileTabs();
  setupChatEnterSubmit();
  setupQueueFilter();
  setupQueueNewHandlers();
  setupFlashMessages();
  syncLogoutForms();
  setupInlineEditors();
  setupRoomDescriptions();
  setupRoomDeleteModals();
  setupWhatsNewModal();
  updateDashboardGreeting();
  scheduleGreetingRefresh();
  refreshLucideIcons();
});

window.rebindQueuePanels = (root = document) => {
  const doc = root?.ownerDocument || root;
  const isExternalDoc = doc && doc.defaultView && doc.defaultView !== window;

  if (!isExternalDoc) {
    setupFlashMessages(root);
    setupRoomDescriptions(root);
    syncLogoutForms();
  }

  setupQueueFilter(root);
  setupQueueNewHandlers(root);
  refreshLucideIcons(root);
};

window.markQueueHasNew = markQueueHasNew;
window.playQueueSound = playQueueSound;
window.initQueueSoundPlayer = initQueueSoundPlayer;
window.isQueueSoundEnabled = isQueueSoundEnabled;
window.setupQueueNewHandlers = setupQueueNewHandlers;
window.setupQueueFilter = setupQueueFilter;
window.markQueueItemSeen = markQueueItemSeen;
