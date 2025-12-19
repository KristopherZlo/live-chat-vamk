const WHATS_NEW_STORAGE_KEY = 'lc-whats-new-version';

function normalizeVersionSegments(value?: string | null): number[] {
  if (!value) {
    return [];
  }
  return value
    .split(/[^0-9]+/)
    .filter((segment) => segment.length)
    .map((segment) => Number.parseInt(segment, 10) || 0);
}

function compareVersions(a: string, b: string): number {
  const segmentsA = normalizeVersionSegments(a);
  const segmentsB = normalizeVersionSegments(b);
  const length = Math.max(segmentsA.length, segmentsB.length);
  for (let i = 0; i < length; i += 1) {
    const valueA = typeof segmentsA[i] === 'number' ? segmentsA[i] : 0;
    const valueB = typeof segmentsB[i] === 'number' ? segmentsB[i] : 0;
    if (valueA > valueB) return 1;
    if (valueA < valueB) return -1;
  }
  return 0;
}

function readStoredWhatsNewVersion(): string | null {
  try {
    return localStorage.getItem(WHATS_NEW_STORAGE_KEY);
  } catch (error) {
    return null;
  }
}

function persistWhatsNewVersion(version: string): void {
  if (!version) return;
  try {
    localStorage.setItem(WHATS_NEW_STORAGE_KEY, version);
  } catch (error) {
    /* ignore */
  }
}

function shouldShowWhatsNewModal(version: string): boolean {
  if (!version) return false;
  const storedVersion = readStoredWhatsNewVersion();
  if (!storedVersion) return true;
  return compareVersions(version, storedVersion) === 1;
}

export function setupWhatsNewModal(root: Document | Element = document): void {
  const modal = root.querySelector<HTMLElement>('[data-whats-new-modal]');
  if (!modal) return;

  const version = modal.dataset.whatsNewVersion;
  if (!version || !shouldShowWhatsNewModal(version)) {
    return;
  }

  let closed = false;

  const closeModal = () => {
    if (closed || !modal || modal.hasAttribute('hidden')) return;
    closed = true;
    modal.classList.remove('show');
    setTimeout(() => {
      modal.hidden = true;
      const hasShowing = document.querySelector<HTMLElement>('.modal-overlay.show');
      if (!hasShowing) {
        document.body.classList.remove('modal-open');
      }
    }, 140);
    persistWhatsNewVersion(version);
  };

  const openModal = () => {
    modal.hidden = false;
    requestAnimationFrame(() => {
      modal.classList.add('show');
      if (typeof modal.focus === 'function') {
        modal.focus({ preventScroll: true });
      }
    });
    document.body.classList.add('modal-open');
  };

  modal.querySelectorAll('[data-whats-new-close]').forEach((button) => {
    button.addEventListener('click', () => closeModal());
  });

  openModal();
}
