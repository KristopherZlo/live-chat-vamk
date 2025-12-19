export function setupMobileMenu(): void {
  const menuOverlay = document.getElementById('mobileMenu');
  const menuBtn = document.getElementById('mobileMenuBtn');
  const menuFab = document.getElementById('mobileMenuBottom');
  const menuTabs = document.getElementById('mobileMenuTabsBtn');
  const closeBtns = document.querySelectorAll<HTMLElement>('[data-close-menu]');

  const setOpen = (open: boolean): void => {
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

export function setupUserMenus(): void {
  const menus = Array.from(document.querySelectorAll<HTMLDetailsElement>('details.user-menu'));
  if (!menus.length) return;

  menus.forEach((menu) => {
    const summary = menu.querySelector<HTMLElement>('summary');
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

export function setupMobileTabs(): void {
  const tabButtons = document.querySelectorAll<HTMLElement>('[data-tab-target]');
  if (!tabButtons.length) return;

  const initial = Array.from(tabButtons).find((btn) => btn.classList.contains('active'));
  let active = initial?.dataset.tabTarget || tabButtons[0].dataset.tabTarget || '';
  const MOBILE_BREAKPOINT = 768;

  const sync = () => {
    const panels = document.querySelectorAll<HTMLElement>('[data-mobile-panel]');
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
