import { refreshLucideIcons } from './icons';

const THEME_KEY = 'lc-theme';

type ThemeMode = 'dark' | 'light';

export function applyTheme(theme: string): void {
  const normalized = theme === 'dark' ? 'dark' : 'light';
  document.body.dataset.theme = normalized;
  document.documentElement.dataset.theme = normalized;
  document.documentElement.style.backgroundColor = normalized === 'dark' ? '#000000' : '#ffffff';
  try {
    localStorage.setItem(THEME_KEY, normalized);
  } catch (e) {
    /* ignore */
  }
  document.querySelectorAll<HTMLElement>('[data-theme-toggle]').forEach((btn) => {
    btn.setAttribute('aria-pressed', normalized === 'dark' ? 'true' : 'false');
  });
  refreshLucideIcons();
}

export function initTheme(): void {
  let preferred: ThemeMode = 'light';
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

export function setupThemeToggle(): void {
  document.querySelectorAll<HTMLElement>('[data-theme-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const next = document.body.dataset.theme === 'dark' ? 'light' : 'dark';
      applyTheme(next);
    });
  });
}
