const THEME_KEY = 'lc-theme';

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

  let active = tabButtons[0].dataset.tabTarget;
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
  window.queueSeenQuestionIds = window.queueSeenQuestionIds || new Set();
  const queueItems = root.querySelectorAll('.queue-item');
  if (!queueItems.length) return;

  if (window.queueSeenQuestionIds.size === 0) {
    queueItems.forEach((item) => {
      const id = Number(item.dataset.questionId || 0);
      if (id) {
        window.queueSeenQuestionIds.add(id);
      }
    });
  }

  const removeBadgeIfCleared = () => {
    const hasNew = document.querySelector('.queue-item.queue-item-new');
    const badge = document.getElementById('queueNewBadge');
    const queuePanel = document.getElementById('queuePanel');
    if (!hasNew && badge) {
      badge.remove();
    }
    if (!hasNew && queuePanel) {
      queuePanel.classList.remove('has-new');
    }
  };

  queueItems.forEach((item) => {
    const id = Number(item.dataset.questionId || 0);
    const isNewStatus = item.dataset.status === 'new';
    if (id && !window.queueSeenQuestionIds.has(id) && isNewStatus) {
      item.classList.add('queue-item-new');
      window.queueSeenQuestionIds.add(id);
    } else if (id && window.queueSeenQuestionIds.has(id)) {
      item.classList.remove('queue-item-new');
    }

    item.addEventListener('click', () => {
      if (!item.classList.contains('queue-item-new')) return;
      item.classList.remove('queue-item-new');
      removeBadgeIfCleared();
    });
  });

  removeBadgeIfCleared();
}

function markQueueHasNew() {
  const queuePanel = document.getElementById('queuePanel');
  if (queuePanel) {
    setupQueueNewHandlers(queuePanel);
    const hasNew = queuePanel.querySelector('.queue-item.queue-item-new');
    queuePanel.classList.toggle('has-new', !!hasNew);
    if (hasNew && !document.getElementById('queueNewBadge')) {
      const badge = document.createElement('span');
      badge.id = 'queueNewBadge';
      badge.className = 'queue-new-badge';
      badge.innerHTML = '<span>New</span>';
      const headerExtra = queuePanel.querySelector('.queue-header-extra');
      if (headerExtra) {
        headerExtra.prepend(badge);
      } else {
        queuePanel.prepend(badge);
      }
    }
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
  initTheme();
  setupThemeToggle();
  setupCopyButtons();
  setupMobileMenu();
  setupUserMenus();
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
