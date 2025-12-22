const QUEUE_SOUND_KEY = 'lc-queue-sound';
const QUEUE_SOUND_SOURCE_KEY = 'lc-queue-sound-src';
const QUEUE_SOUND_DEBUG_KEY = 'lc-queue-sound-debug';

let queueSoundPlayer: HTMLAudioElement | null = null;
let queueSoundPlayerSrc: string | null = null;
let queueSoundPreference = true;
let queueSoundPrimed = false;
let queueSoundPrimeHandlerBound = false;

const IS_DEV: boolean = import.meta.env.DEV;

function isQueueSoundDebugEnabled(): boolean {
  if (!IS_DEV || typeof window === 'undefined') return false;
  if (window.__queueSoundDebug === true) return true;
  try {
    return localStorage.getItem(QUEUE_SOUND_DEBUG_KEY) === '1';
  } catch (e) {
    return false;
  }
}

function logQueueSound(...args: unknown[]): void {
  if (!isQueueSoundDebugEnabled()) return;
  // eslint-disable-next-line no-console
  console.debug(...args);
}

function readQueueSoundSource(): string | null {
  try {
    const stored = localStorage.getItem(QUEUE_SOUND_SOURCE_KEY);
    return stored && stored.trim().length > 0 ? stored : null;
  } catch (e) {
    return null;
  }
}

export function resolveQueueSoundUrl(defaultUrl?: string): string | null {
  return readQueueSoundSource() || defaultUrl || null;
}

export function applyQueueSoundSource(defaultUrl?: string): string | null {
  const resolved = resolveQueueSoundUrl(defaultUrl);
  if (resolved && typeof window !== 'undefined') {
    window.queueSoundUrl = resolved;
  }
  return resolved;
}

export function setQueueSoundSource(url?: string | null): void {
  const trimmed = typeof url === 'string' ? url.trim() : '';
  try {
    if (trimmed) {
      localStorage.setItem(QUEUE_SOUND_SOURCE_KEY, trimmed);
    } else {
      localStorage.removeItem(QUEUE_SOUND_SOURCE_KEY);
    }
  } catch (e) {
    /* ignore */
  }
  queueSoundPlayer = null;
  queueSoundPlayerSrc = null;
  if (trimmed && typeof window !== 'undefined') {
    window.queueSoundUrl = trimmed;
  }
}

export function loadQueueSoundSetting(): boolean {
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

function persistQueueSoundSetting(enabled: boolean): void {
  const value = enabled ? 'on' : 'off';
  try {
    localStorage.setItem(QUEUE_SOUND_KEY, value);
  } catch (e) {
    /* ignore */
  }
}

export function isQueueSoundEnabled(): boolean {
  return typeof queueSoundPreference === 'boolean'
    ? queueSoundPreference
    : loadQueueSoundSetting();
}

function ensureQueueSoundPlayer(url?: string): HTMLAudioElement | null {
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

export function playQueueSound(url?: string): void {
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

function primeQueueSound(url?: string): void {
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

export function setupSoundPriming(url?: string): void {
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

export function setupQueueSoundToggle(): void {
  const toggles = document.querySelectorAll<HTMLElement>('[data-queue-sound-toggle]');
  if (!toggles.length) return;

  let enabled = loadQueueSoundSetting();

  const syncUI = () => {
    toggles.forEach((btn) => {
      const stateEl = btn.querySelector<HTMLElement>('[data-sound-state]');
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

export function initQueueSoundPlayer(url?: string): void {
  ensureQueueSoundPlayer(url);
  // Try to prime immediately (muted play), then also bind user-gesture priming as fallback.
  primeQueueSound(url);
  setupSoundPriming(url);
}

export function setupQueueSoundSelect(): void {
  const select = document.querySelector<HTMLSelectElement>('[data-queue-sound-select]');
  if (!select) return;

  const previewButton = document.querySelector<HTMLElement>('[data-queue-sound-preview]');
  const noticeMessage = select.dataset.queueSoundNotice || 'Sound selection saved.';
  const resolved = resolveQueueSoundUrl(window.queueSoundUrl);
  if (resolved) {
    const hasOption = Array.from(select.options).some((option) => option.value === resolved);
    if (hasOption) {
      select.value = resolved;
    }
  }

  select.addEventListener('change', () => {
    const nextUrl = select.value;
    setQueueSoundSource(nextUrl);
    initQueueSoundPlayer(nextUrl);
    if (typeof window.showFlashNotification === 'function') {
      window.showFlashNotification(noticeMessage, { type: 'success', source: 'queue-sound-select' });
    }
  });

  if (previewButton) {
    previewButton.addEventListener('click', () => {
      const previewUrl = select.value || window.queueSoundUrl;
      if (previewUrl) {
        playQueueSound(previewUrl);
      }
    });
  }
}
