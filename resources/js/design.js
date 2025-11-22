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
  const queueItems = root.querySelectorAll('.queue-item');
  if (!queueItems.length) return;

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
    item.addEventListener('click', () => {
      if (!item.classList.contains('queue-item-new')) return;
      item.classList.remove('queue-item-new');
      removeBadgeIfCleared();
    });
  });
}

function markQueueHasNew() {
  const queuePanel = document.getElementById('queuePanel');
  if (queuePanel) {
    queuePanel.classList.add('has-new');
    const firstItem = queuePanel.querySelector('.queue-item');
    if (firstItem) {
      firstItem.classList.add('queue-item-new');
    }
    if (!document.getElementById('queueNewBadge')) {
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

document.addEventListener('DOMContentLoaded', () => {
  initTheme();
  setupThemeToggle();
  setupCopyButtons();
  setupMobileMenu();
  setupUserMenus();
  setupMobileTabs();
  setupHistoryOpener();
  setupQueueNewHandlers();
  refreshLucideIcons();
});

window.rebindQueuePanels = (root = document) => {
  setupHistoryOpener(root);
  setupQueueNewHandlers(root);
  refreshLucideIcons();
};

window.markQueueHasNew = markQueueHasNew;
