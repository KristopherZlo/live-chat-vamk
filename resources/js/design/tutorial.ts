const TUTORIAL_DISMISS_KEY = 'lc-tutorial-dismissed';

function hasDismissedTutorial(): boolean {
  try {
    return localStorage.getItem(TUTORIAL_DISMISS_KEY) === '1';
  } catch (error) {
    return false;
  }
}

function dismissTutorial(): void {
  try {
    localStorage.setItem(TUTORIAL_DISMISS_KEY, '1');
  } catch (error) {
    /* ignore */
  }
}

export function setupTutorialModal(root: Document | Element = document): void {
  const modal = root.querySelector<HTMLElement>('[data-tutorial-modal]');
  if (!modal) return;
  if (modal.dataset.tutorialBound === '1') return;
  modal.dataset.tutorialBound = '1';

  const iframe = modal.querySelector<HTMLIFrameElement>('[data-tutorial-iframe]');
  const videoUrl = modal.dataset.tutorialVideoUrl || '';
  const autoShow = modal.dataset.tutorialAutoshow === '1';
  let isOpen = false;

  const setVideoActive = (active: boolean): void => {
    if (!iframe || !videoUrl) return;
    if (active) {
      if (iframe.src !== videoUrl) {
        iframe.src = videoUrl;
      }
      return;
    }
    iframe.removeAttribute('src');
  };

  const openModal = (force = false): void => {
    if (isOpen) return;
    if (!force) {
      if (!autoShow || hasDismissedTutorial()) return;
      if (document.querySelector('.modal-overlay.show')) return;
    }
    modal.hidden = false;
    requestAnimationFrame(() => {
      modal.classList.add('show');
      if (typeof modal.focus === 'function') {
        modal.focus({ preventScroll: true });
      }
    });
    document.body.classList.add('modal-open');
    setVideoActive(true);
    isOpen = true;
  };

  const closeModal = (persist = true): void => {
    if (!isOpen) return;
    modal.classList.remove('show');
    setVideoActive(false);
    setTimeout(() => {
      modal.hidden = true;
      const hasShowing = document.querySelector<HTMLElement>('.modal-overlay.show');
      if (!hasShowing) {
        document.body.classList.remove('modal-open');
      }
    }, 140);
    if (persist) {
      dismissTutorial();
    }
    isOpen = false;
  };

  root.querySelectorAll<HTMLElement>('[data-tutorial-open]').forEach((trigger) => {
    if (trigger.dataset.tutorialOpenBound === '1') return;
    trigger.dataset.tutorialOpenBound = '1';
    trigger.addEventListener('click', (event) => {
      event.preventDefault();
      openModal(true);
    });
  });

  modal.querySelectorAll<HTMLElement>('[data-tutorial-close]').forEach((button) => {
    button.addEventListener('click', () => closeModal(true));
  });

  modal.querySelectorAll<HTMLElement>('[data-tutorial-skip]').forEach((button) => {
    button.addEventListener('click', () => closeModal(true));
  });

  modal.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeModal(true);
    }
  });

  openModal(false);
}
