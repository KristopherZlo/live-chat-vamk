export function setupRoomDescriptions(root: Document | Element = document): void {
  const descriptions = root.querySelectorAll<HTMLElement>('[data-room-description]');
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

export function setupRoomDeleteModals(): void {
  const modals = Array.from(document.querySelectorAll<HTMLElement>('[data-room-delete-modal]'));
  const triggers = document.querySelectorAll<HTMLElement>('[data-room-delete-trigger]');
  if (!modals.length || !triggers.length) return;

  const syncState = (modal: HTMLElement): void => {
    const input = modal.querySelector<HTMLInputElement>('[data-room-delete-input]');
    const submit = modal.querySelector<HTMLButtonElement>('[data-room-delete-submit]');
    const expected = input ? input.dataset.roomTitle || '' : '';
    if (submit) {
      submit.disabled = !input || input.value !== expected;
    }
  };

  const closeModal = (modal: HTMLElement | null): void => {
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

  const openModal = (modal: HTMLElement | null): void => {
    if (!modal) return;
    modal.hidden = false;
    requestAnimationFrame(() => modal.classList.add('show'));
    document.body.classList.add('modal-open');
    const input = modal.querySelector<HTMLInputElement>('[data-room-delete-input]');
    if (input) {
      input.value = '';
      syncState(modal);
      input.focus({ preventScroll: true });
    }
  };

  modals.forEach((modal) => {
    const input = modal.querySelector<HTMLInputElement>('[data-room-delete-input]');
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

    modal.querySelectorAll<HTMLElement>('[data-room-delete-close]').forEach((btn) => {
      btn.addEventListener('click', () => closeModal(modal));
    });

    syncState(modal);
  });

  triggers.forEach((trigger) => {
    const modal = document.querySelector<HTMLElement>(
      `[data-room-delete-modal="${trigger.dataset.roomDeleteTrigger}"]`,
    );
    if (!modal) return;
    trigger.addEventListener('click', () => openModal(modal));
  });
}
