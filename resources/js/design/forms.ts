import { showFlashNotification } from './flash';

const copyCounters = new Map<string, number>();
const copyJokes = [
  'SUPER MEGA COPY!',
  'DIVINE COPY!',
  'UNIVERSAL COPY!',
  'YOU FOUND THE ANSWER TO LIFE AND DEATH WITH YOUR COPYING',
  'HYPER COPY MODE!',
  'OMEGA COPY!',
  'GALACTIC COPY!',
  'ABSOLUTE COPY!',
  'TRANSCENDENT COPY!',
  'THE CLIPBOARD SURRENDERS',
  'COPY ENGINE OVERDRIVE',
  'MYTHIC COPY STREAK',
  'COSMIC COPY STREAK!',
  'REALITY IS BEING COPIED',
  'TIMELINE DUPLICATED',
  'MULTIVERSE-LEVEL COPYING',
  'COPY FORCE: MAXIMUM',
  'RED HOT COPYING!',
  'THE COPY PROPHECY IS REAL',
  'BEYOND LIMITS COPYING',
  'YOU ARE THE CLIPBOARD NOW',
  'TOTAL DOMINATION: COPY EDITION',
];
const MAX_COPY_COLOR_STEPS = copyJokes.length + 3;

const getCopyFeedback = (value: string): { message: string; count: number } => {
  const nextCount = (copyCounters.get(value) ?? 0) + 1;
  copyCounters.set(value, nextCount);
  if (nextCount === 1) return { message: 'Link copied to clipboard', count: nextCount };
  if (nextCount === 2) return { message: 'Double copy!', count: nextCount };
  if (nextCount === 3) return { message: 'Triple copy!', count: nextCount };
  const jokeIndex = nextCount - 4;
  const message = copyJokes[Math.min(jokeIndex, copyJokes.length - 1)];
  return { message, count: nextCount };
};

const getCopyTone = (count: number): { border: string; background: string; text: string } => {
  const step = Math.max(0, Math.min(count - 1, MAX_COPY_COLOR_STEPS));
  const t = step / MAX_COPY_COLOR_STEPS;
  const hue = Math.round(130 - 130 * t);
  return {
    border: `hsl(${hue} 85% 55%)`,
    background: `hsla(${hue} 45% 18% / 0.78)`,
    text: '#f8fafc',
  };
};

const applyCopyFlashStyle = (flash: HTMLElement, count: number): void => {
  const tone = getCopyTone(count);
  flash.style.background = tone.background;
  flash.style.borderColor = tone.border;
  flash.style.color = tone.text;
  const progress = flash.querySelector<HTMLElement>('.flash-progress span');
  if (progress) {
    progress.style.background = tone.border;
  }
};

export function setupCopyButtons(): void {
  document.querySelectorAll<HTMLElement>('[data-copy]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const value = btn.dataset.copy;
      if (!value) return;
      const notify = (success: boolean): void => {
        if (!success) {
          showFlashNotification('Unable to copy link', {
            type: 'danger',
            source: 'room-copy-link',
          });
          return;
        }
        const { message, count } = getCopyFeedback(value);
        const flash = showFlashNotification(message, {
          type: 'success',
          source: 'room-copy-link',
        });
        applyCopyFlashStyle(flash, count);
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

function fallbackCopy(value: string, notify: (success: boolean) => void): void {
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

export function setupChatEnterSubmit(): void {
  const form = document.getElementById('chat-form') as HTMLFormElement | null;
  const textarea = document.getElementById('chatInput') as HTMLTextAreaElement | null;
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

export function syncLogoutForms(): void {
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
  if (!token) return;
  document.querySelectorAll<HTMLFormElement>('form[action$="logout"]').forEach((form) => {
    let input = form.querySelector<HTMLInputElement>('input[name="_token"]');
    if (!input) {
      input = document.createElement('input');
      input.type = 'hidden';
      input.name = '_token';
      form.appendChild(input);
    }
    input.value = token;
  });
}

export function setupInlineEditors(root: Document | Element = document): void {
  const blocks = root.querySelectorAll<HTMLElement>('[data-inline-edit]');
  blocks.forEach((block) => {
    const trigger = block.querySelector<HTMLElement>('[data-inline-trigger]');
    const form = block.querySelector<HTMLElement>('.inline-edit-form');
    const display = block.querySelector<HTMLElement>('.inline-edit-display');
    if (!trigger || !form || !display) return;

    const cancel = block.querySelector<HTMLElement>('[data-inline-cancel]');
    const input = form.querySelector<HTMLInputElement | HTMLTextAreaElement>('input, textarea');

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
