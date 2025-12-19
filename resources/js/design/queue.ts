const QUEUE_SEEN_KEY_PREFIX = 'lc-queue-seen';
const QUEUE_FILTER_KEY_PREFIX = 'lc-queue-filter';

type QueueRoot = Document | Element;

function normalizeId(value: unknown): number | null {
  const num = Number(value);
  return Number.isInteger(num) && num > 0 ? num : null;
}

function getQueuePanel(root: QueueRoot = document): HTMLElement | null {
  if (root && typeof root.querySelector === 'function') {
    const found = root.querySelector<HTMLElement>('#queuePanel');
    if (found) return found;
  }
  return document.getElementById('queuePanel');
}

function getQueueStorageKey(queuePanel: HTMLElement | null = getQueuePanel()): string | null {
  if (!queuePanel) return null;
  const roomKey = queuePanel.dataset.roomSlug || queuePanel.dataset.roomId;
  const viewerId = queuePanel.dataset.viewerId || 'viewer';
  if (!roomKey) return null;
  return `${QUEUE_SEEN_KEY_PREFIX}:${roomKey}:${viewerId}`;
}

function getQueueFilterStorageKey(queuePanel: HTMLElement | null = getQueuePanel()): string | null {
  if (!queuePanel) return null;
  const roomKey = queuePanel.dataset.roomSlug || queuePanel.dataset.roomId;
  return roomKey ? `${QUEUE_FILTER_KEY_PREFIX}:${roomKey}` : null;
}

function loadQueueSeenState(queuePanel: HTMLElement | null = getQueuePanel()): {
  storageKey: string | null;
  seenIds: Set<number>;
} {
  const storageKey = getQueueStorageKey(queuePanel);
  let seenIds: Set<number> | undefined = window.queueSeenQuestionIds;

  const needsLoad = !seenIds || window.queueSeenQuestionIdsKey !== storageKey;
  if (needsLoad) {
    seenIds = new Set<number>();
    if (storageKey) {
      try {
        const stored = localStorage.getItem(storageKey);
        if (stored) {
          const parsed = JSON.parse(stored);
          if (Array.isArray(parsed)) {
            parsed.forEach((value) => {
              const id = normalizeId(value);
              if (id) {
                seenIds.add(id);
              }
            });
          }
        }
      } catch (e) {
        /* ignore */
      }
    }
    window.queueSeenQuestionIds = seenIds;
    window.queueSeenQuestionIdsKey = storageKey;
  }

  return { storageKey, seenIds: seenIds ?? new Set<number>() };
}

function persistQueueSeenState(storageKey: string | null, seenIds: Set<number>): void {
  if (!storageKey || !seenIds) return;
  try {
    localStorage.setItem(storageKey, JSON.stringify(Array.from(seenIds)));
  } catch (e) {
    /* ignore */
  }
}

function updateQueueBadge(queuePanel: HTMLElement | null = getQueuePanel()): void {
  if (!queuePanel) return;
  const doc = queuePanel.ownerDocument || document;
  const hasNew = queuePanel.querySelector('.queue-item.queue-item-new');
  let badge = doc.getElementById('queueNewBadge');

  queuePanel.classList.toggle('has-new', !!hasNew);

  if (hasNew && !badge) {
    badge = doc.createElement('span');
    badge.id = 'queueNewBadge';
    badge.className = 'queue-new-badge';
    badge.innerHTML = '<span>New</span>';
    const headerExtra = queuePanel.querySelector<HTMLElement>('.queue-header-extra');
    if (headerExtra) {
      headerExtra.prepend(badge);
    } else {
      queuePanel.prepend(badge);
    }
  } else if (!hasNew && badge) {
    badge.remove();
  }
}

export function setupQueueFilter(root: QueueRoot = document): void {
  const queuePanel = getQueuePanel(root);
  if (!queuePanel) return;
  const filter = queuePanel.querySelector<HTMLSelectElement>('[data-queue-filter]');
  if (!filter) return;
  const list = queuePanel.querySelector<HTMLElement>('.queue-list');
  const isRemoteQueue = queuePanel.dataset.queueRemote === '1';
  if (!isRemoteQueue && !list) return;

  const emptyState = queuePanel.querySelector<HTMLElement>('[data-queue-filter-empty]');
  const storageKey = getQueueFilterStorageKey(queuePanel);
  const dispatchFilterChange = (value: string, meta: Record<string, unknown> = {}) => {
    const event = new CustomEvent('queue:filter-change', {
      detail: { value, ...meta },
      bubbles: true,
    });
    queuePanel.dispatchEvent(event);
  };

  const loadStoredFilter = (): string | null => {
    if (!storageKey) return null;
    try {
      const stored = localStorage.getItem(storageKey);
      return typeof stored === 'string' && stored.length ? stored : null;
    } catch (e) {
      return null;
    }
  };

  const persistFilter = (value: string): void => {
    if (!storageKey) return;
    try {
      localStorage.setItem(storageKey, value);
    } catch (e) {
      /* ignore */
    }
  };

  const applyFilter = (meta: Record<string, unknown> = {}) => {
    const value = (filter.value || 'new').toLowerCase();
    if (isRemoteQueue) {
      dispatchFilterChange(value, meta);
      return;
    }
    if (!list) return;

    const items = Array.from(list.querySelectorAll<HTMLElement>('.queue-item'));
    let visible = 0;

    items.forEach((item) => {
      const status = (item.dataset.status || '').toLowerCase();
      const matches = value === 'all' ? true : status === value;
      item.hidden = !matches;
      if (matches) visible += 1;
    });

    if (emptyState) {
      emptyState.hidden = visible > 0;
    }
    list.classList.toggle('queue-list-filter-empty', visible === 0);
    if (typeof window.maybeAutoloadQueue === 'function') {
      window.maybeAutoloadQueue(true);
    }
    dispatchFilterChange(value, meta);
  };

  const stored = loadStoredFilter();
  if (stored) {
    const option = Array.from(filter.options).find((opt) => (opt.value || '').toLowerCase() === stored.toLowerCase());
    if (option) {
      filter.value = option.value;
    }
  }
  if (filter.dataset.queueFilterBound === '1') {
    if (!isRemoteQueue) {
      applyFilter({ initial: false });
    }
    return;
  }

  filter.dataset.queueFilterBound = '1';
  filter.addEventListener('change', () => {
    persistFilter(filter.value || 'new');
    applyFilter({ initial: false });
  });
  applyFilter({ initial: true });
}

export function setupQueueNewHandlers(root: QueueRoot = document): void {
  const queuePanel = getQueuePanel(root);
  const scope: QueueRoot = queuePanel || root;
  const queueItems = scope.querySelectorAll<HTMLElement>('.queue-item');
  const { storageKey, seenIds } = loadQueueSeenState(queuePanel);
  if (!queueItems.length) {
    updateQueueBadge(queuePanel);
    return;
  }

  const persistSeen = (id: number | null): boolean => {
    if (!id || seenIds.has(id)) return false;
    seenIds.add(id);
    persistQueueSeenState(storageKey, seenIds);
    return true;
  };

  const markSeen = (id: number | null): void => {
    if (!persistSeen(id)) return;
    updateQueueBadge(queuePanel);
  };

  queueItems.forEach((item) => {
    const id = normalizeId(item.dataset.questionId);
    const status = (item.dataset.status || '').toLowerCase();
    const isNewStatus = status === 'new';
    const isSeen = id !== null && seenIds.has(id);

    if (!isNewStatus || isSeen) {
      item.classList.remove('queue-item-new');
    } else if (isNewStatus) {
      item.classList.add('queue-item-new');
    }

    item.addEventListener('click', () => {
      if (!id || !item.classList.contains('queue-item-new')) return;
      item.classList.remove('queue-item-new');
      markSeen(id);
    });
  });

  updateQueueBadge(queuePanel);
}

export function markQueueItemSeen(questionId: number | string, root: QueueRoot = document): void {
  const queuePanel = getQueuePanel(root);
  const { storageKey, seenIds } = loadQueueSeenState(queuePanel);
  const id = normalizeId(questionId);
  if (!id || !seenIds || seenIds.has(id)) return;

  seenIds.add(id);
  persistQueueSeenState(storageKey, seenIds);

  if (queuePanel) {
    const item = queuePanel.querySelector<HTMLElement>(`.queue-item[data-question-id="${id}"]`);
    if (item) {
      item.classList.remove('queue-item-new');
    }
    updateQueueBadge(queuePanel);
  }
}

export function markQueueHasNew(): void {
  const queuePanel = document.getElementById('queuePanel');
  if (queuePanel) {
    setupQueueNewHandlers(queuePanel);
  }
}
