const THEME_KEY = 'lc-theme';
const QUEUE_SEEN_KEY_PREFIX = 'lc-queue-seen';
const QUEUE_SOUND_KEY = 'lc-queue-sound';

let queueSoundPlayer = null;
let queueSoundPlayerSrc = null;
let queueSoundPreference = true;

function normalizeId(value) {
  const num = Number(value);
  return Number.isInteger(num) && num > 0 ? num : null;
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
  const roomId = queuePanel.dataset.roomId;
  const viewerId = queuePanel.dataset.viewerId || 'viewer';
  if (!roomId) return null;
  return `${QUEUE_SEEN_KEY_PREFIX}:${roomId}:${viewerId}`;
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
  const hasNew = queuePanel.querySelector('.queue-item.queue-item-new');
  let badge = document.getElementById('queueNewBadge');

  queuePanel.classList.toggle('has-new', !!hasNew);

  if (hasNew && !badge) {
    badge = document.createElement('span');
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
    } catch (e) {
      queueSoundPlayer = null;
    }
  }
  return queueSoundPlayer;
}

function playQueueSound(url) {
  if (!isQueueSoundEnabled()) return;
  const player = ensureQueueSoundPlayer(url);
  if (!player) return;
  try {
    player.currentTime = 0;
    player.play().catch(() => {});
  } catch (e) {
    /* ignore */
  }
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
    });
  });

  syncUI();
}

function initQueueSoundPlayer(url) {
  ensureQueueSoundPlayer(url);
}

function refreshLucideIcons() {
  if (window.lucide && typeof window.lucide.createIcons === 'function') {
    window.lucide.createIcons();
  }
}
window.refreshLucideIcons = refreshLucideIcons;

function applyTheme(theme) {
  const normalized = theme === 'dark' ? 'dark' : 'light';
  document.body.dataset.theme = normalized;
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
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(value).catch(() => {});
      }
      btn.classList.add('pulse');
      setTimeout(() => btn.classList.remove('pulse'), 300);
    });
  });
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

function setupHistoryOpener(root = document) {
  const buttons = root.querySelectorAll('[data-toggle-history]');
  if (!buttons.length) return;

  const layoutRoot = document.getElementById('layoutRoot');
  const historyPanel = document.getElementById('historyPanel');

  let historyVisible = true;

  const applyVisibility = () => {
    if (layoutRoot) layoutRoot.classList.toggle('history-hidden', !historyVisible);
    if (historyPanel) historyPanel.classList.toggle('hidden', !historyVisible);

    buttons.forEach((b) => b.classList.toggle('active', historyVisible));

    // mobile tab sync
    const historyTab = document.querySelector('[data-tab-target="history"]');
    if (historyTab) {
      historyTab.classList.toggle('active', historyVisible);
    }
  };

  const toggle = () => {
    historyVisible = !historyVisible;
    applyVisibility();
    if (historyVisible && historyPanel) {
      historyPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };

  buttons.forEach((btn) => btn.addEventListener('click', toggle));
  applyVisibility();
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

  const markSeen = (id) => {
    if (!id || seenIds.has(id)) return;
    seenIds.add(id);
    persistQueueSeenState(storageKey, seenIds);
  };

  queueItems.forEach((item) => {
    const id = normalizeId(item.dataset.questionId);
    const isNewStatus = item.classList.contains('queue-item-new');
    const isSeen = id && seenIds.has(id);

    if (!isNewStatus) {
      item.classList.remove('queue-item-new');
    } else if (id && !isSeen) {
      item.classList.add('queue-item-new');
    } else if (id && isSeen) {
      item.classList.remove('queue-item-new');
    }

    item.addEventListener('click', () => {
      if (!id || !item.classList.contains('queue-item-new')) return;
      item.classList.remove('queue-item-new');
      markSeen(id);
      updateQueueBadge(queuePanel);
    });
  });

  updateQueueBadge(queuePanel);
}

function markQueueHasNew() {
  const queuePanel = document.getElementById('queuePanel');
  if (queuePanel) {
    setupQueueNewHandlers(queuePanel);
  }
}

function setupFlashMessages(root = document) {
  const flashes = root.querySelectorAll('[data-flash]');
  flashes.forEach((flash) => {
    const closeBtn = flash.querySelector('[data-flash-close]');
    const hide = () => {
      flash.classList.add('hidden');
      setTimeout(() => flash.remove(), 300);
    };
    if (closeBtn) {
      closeBtn.addEventListener('click', hide);
    }
    setTimeout(hide, 3500);
  });
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

document.addEventListener('DOMContentLoaded', () => {
  loadQueueSoundSetting();
  initTheme();
  setupThemeToggle();
  setupCopyButtons();
  setupMobileMenu();
  setupUserMenus();
  setupQueueSoundToggle();
  setupMobileTabs();
  setupChatEnterSubmit();
  setupHistoryOpener();
  setupQueueNewHandlers();
  setupFlashMessages();
  setupInlineEditors();
  refreshLucideIcons();
});

window.rebindQueuePanels = (root = document) => {
  setupHistoryOpener(root);
  setupQueueNewHandlers(root);
  setupFlashMessages(root);
  refreshLucideIcons();
};

window.markQueueHasNew = markQueueHasNew;
window.playQueueSound = playQueueSound;
window.initQueueSoundPlayer = initQueueSoundPlayer;
window.isQueueSoundEnabled = isQueueSoundEnabled;
