import { refreshLucideIcons } from './icons';

const NETWORK_STATUS_OFFLINE_SOURCE = 'network-status-offline';
const NETWORK_STATUS_ONLINE_SOURCE = 'network-status-online';
const NETWORK_STATUS_OFFLINE_MESSAGE = 'Internet connection lost. Some features may be unavailable.';
const NETWORK_STATUS_ONLINE_MESSAGE = 'Internet connection restored.';
const flashTimers = new WeakMap<HTMLElement, number>();

function ensureFlashContainer(): HTMLElement {
  let container = document.querySelector<HTMLElement>('.flash-toaster');
  if (!container) {
    container = document.createElement('div');
    container.className = 'flash-toaster';
    document.body.appendChild(container);
  }
  return container;
}

function escapeHtml(value: unknown): string {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  }[char] ?? char));
}

export function setupFlashMessages(root: Document | Element = document): void {
  const toaster = ensureFlashContainer();
  const flashes: HTMLElement[] = [];
  if (root instanceof Element && root.matches('[data-flash]')) {
    flashes.push(root as HTMLElement);
  }
  flashes.push(...Array.from(root.querySelectorAll<HTMLElement>('[data-flash]')));
  flashes.forEach((flash) => {
    const clearTimer = () => {
      const timer = flashTimers.get(flash);
      if (timer) {
        clearTimeout(timer);
        flashTimers.delete(flash);
      }
    };
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
    const progress = flash.querySelector<HTMLElement>('.flash-progress');
    if (progress) {
      progress.innerHTML = '<span></span>';
      progress.style.setProperty('--flash-duration', `${duration}ms`);
    }
    const closeBtn = flash.querySelector<HTMLElement>('[data-flash-close]');
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
    clearTimer();
    const hide = () => {
      clearTimer();
      flash.classList.add('hidden');
      setTimeout(() => flash.remove(), 250);
    };
    const close = flash.querySelector<HTMLElement>('[data-flash-close]');
    if (close) {
      if (!flash.dataset.flashBound) {
        close.addEventListener('click', hide, { once: true });
        flash.dataset.flashBound = '1';
      }
    }
    if (duration > 0) {
      clearTimer();
      const timer = window.setTimeout(hide, duration);
      flashTimers.set(flash, timer);
    }
    if (createdClose) {
      refreshLucideIcons(flash);
    }
  });
}

type FlashNotificationOptions = {
  type?: string;
  source?: string;
  duration?: number;
};

export function showFlashNotification(message: string, options: FlashNotificationOptions = {}): HTMLElement {
  const { type = 'success', source, duration = 4500 } = options;
  const container = ensureFlashContainer();
  const normalizedMessage = String(message ?? '');
  const safeMessage = escapeHtml(normalizedMessage);
  if (source) {
    const existing = container.querySelector<HTMLElement>(`[data-flash-source="${source}"]`);
    if (existing) {
      const span = existing.querySelector<HTMLElement>('.flash-text');
      if (span) {
        span.textContent = normalizedMessage;
      }
      existing.className = `flash flash-${type} flash-toast`;
      existing.dataset.flashDuration = String(duration);
      const bar = existing.querySelector<HTMLElement>('.flash-progress');
      if (bar) {
        bar.style.setProperty('--flash-duration', `${duration}ms`);
      }
      setupFlashMessages(existing);
      refreshLucideIcons(existing);
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
  refreshLucideIcons(flash);
  return flash;
}

function clearNetworkOfflineNotice(): void {
  const container = document.querySelector<HTMLElement>('.flash-toaster');
  if (!container) return;
  const offlineNotice = container.querySelector(`[data-flash-source="${NETWORK_STATUS_OFFLINE_SOURCE}"]`);
  if (offlineNotice) {
    offlineNotice.remove();
  }
}

export function setupNetworkStatusNotification() {
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
