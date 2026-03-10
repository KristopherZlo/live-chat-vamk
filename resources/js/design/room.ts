export function setupRoomDescriptions(root: Document | Element = document): void {
  const descriptions = root.querySelectorAll<HTMLElement>('[data-room-description-short][data-room-description-full]');
  descriptions.forEach((desc) => {
    if (desc.dataset.roomDescriptionBound === '1') return;
    desc.dataset.roomDescriptionBound = '1';
    const toggleButton = desc.closest<HTMLElement>('[data-room-description-block]')
      ?.querySelector<HTMLButtonElement>('[data-room-description-toggle]');

    const shortText = desc.dataset.roomDescriptionShort || desc.textContent || '';
    const fullText = desc.dataset.roomDescriptionFull || shortText;

    const apply = () => {
      const collapsed = desc.dataset.collapsed !== 'false';
      desc.classList.toggle('is-collapsed', collapsed);
      desc.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      desc.textContent = collapsed ? shortText : fullText;
      if (toggleButton) {
        toggleButton.textContent = collapsed ? 'Show more' : 'Show less';
      }
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
    if (toggleButton) {
      toggleButton.addEventListener('click', (event) => {
        event.preventDefault();
        toggle();
      });
    }

    apply();
  });
}

export function setupRoomColorPickers(root: Document | Element = document): void {
  const pickers = Array.from(root.querySelectorAll<HTMLElement>('[data-room-color-picker]'));
  if (!pickers.length) return;

  const doc = root instanceof Document ? root : root.ownerDocument || document;
  const colorKeys = ['ocean', 'mint', 'amber', 'rose', 'violet', 'teal', 'slate', 'coral'];
  const colorCardClassPrefix = 'room-card--color-';
  const colorDotClassPrefix = 'room-card-color-dot--';
  const getAllPickers = (): HTMLElement[] =>
    Array.from(doc.querySelectorAll<HTMLElement>('[data-room-color-picker]'));

  const setDotColor = (dot: Element | null, colorKey: string): void => {
    if (!dot) return;
    Array.from(dot.classList).forEach((className) => {
      if (className.startsWith(colorDotClassPrefix)) {
        dot.classList.remove(className);
      }
    });
    dot.classList.add(`${colorDotClassPrefix}${colorKey}`);
  };

  const applyPickerColor = (picker: HTMLElement, colorKey: string): void => {
    const normalizedColor = colorKeys.includes(colorKey) ? colorKey : 'default';
    const card = picker.closest<HTMLElement>('.room-card');

    if (card) {
      colorKeys.forEach((key) => card.classList.remove(`${colorCardClassPrefix}${key}`));
      if (normalizedColor !== 'default') {
        card.classList.add(`${colorCardClassPrefix}${normalizedColor}`);
      }
    }

    const triggerDot = picker.querySelector<HTMLElement>('[data-room-color-trigger] .room-card-color-dot');
    setDotColor(triggerDot, normalizedColor);

    picker.querySelectorAll<HTMLButtonElement>('.room-card-color-option').forEach((option) => {
      option.classList.toggle('is-active', option.value === normalizedColor);
    });
  };

  const closePicker = (picker: HTMLElement): void => {
    const trigger = picker.querySelector<HTMLButtonElement>('[data-room-color-trigger]');
    const menu = picker.querySelector<HTMLElement>('[data-room-color-menu]');
    if (!trigger || !menu || menu.hidden) return;
    menu.hidden = true;
    trigger.setAttribute('aria-expanded', 'false');
    picker.classList.remove('is-open');
  };

  const openPicker = (picker: HTMLElement): void => {
    const trigger = picker.querySelector<HTMLButtonElement>('[data-room-color-trigger]');
    const menu = picker.querySelector<HTMLElement>('[data-room-color-menu]');
    if (!trigger || !menu) return;

    getAllPickers().forEach((candidate) => {
      if (candidate !== picker) {
        closePicker(candidate);
      }
    });

    menu.hidden = false;
    trigger.setAttribute('aria-expanded', 'true');
    picker.classList.add('is-open');
  };

  pickers.forEach((picker) => {
    if (picker.dataset.roomColorBound === '1') return;
    picker.dataset.roomColorBound = '1';

    const trigger = picker.querySelector<HTMLButtonElement>('[data-room-color-trigger]');
    const menu = picker.querySelector<HTMLFormElement>('[data-room-color-menu]');
    if (!trigger || !menu) return;

    trigger.addEventListener('click', (event) => {
      event.preventDefault();

      if (menu.hidden) {
        openPicker(picker);
        return;
      }

      closePicker(picker);
    });

    picker.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') return;
      closePicker(picker);
      trigger.focus({ preventScroll: true });
    });

    menu.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (menu.dataset.busy === '1') return;

      const submitEvent = event as SubmitEvent;
      const submitter = submitEvent.submitter instanceof HTMLButtonElement
        ? submitEvent.submitter
        : null;
      const requestedColor = submitter?.value || 'default';

      const formData = new FormData(menu);
      formData.set('card_color', requestedColor);
      const payload = new URLSearchParams();
      formData.forEach((value, key) => {
        if (typeof value === 'string') {
          payload.append(key, value);
        }
      });

      const csrfToken = doc.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      const options = menu.querySelectorAll<HTMLButtonElement>('.room-card-color-option');
      menu.dataset.busy = '1';
      options.forEach((option) => {
        option.disabled = true;
      });

      try {
        const response = await fetch(menu.action, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
          },
          body: payload,
        });

        if (!response.ok) {
          return;
        }

        let appliedColor = requestedColor;
        const contentType = response.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
          const data = await response.json().catch(() => null);
          const nextColor = data?.room?.card_color;
          appliedColor = typeof nextColor === 'string' && nextColor.length ? nextColor : 'default';
        }

        applyPickerColor(picker, appliedColor);
        closePicker(picker);
      } catch (error) {
        console.error('Failed to update room card color', error);
      } finally {
        delete menu.dataset.busy;
        options.forEach((option) => {
          option.disabled = false;
        });
      }
    });
  });

  const rootKey = 'roomColorGlobalBound';
  if (doc.documentElement?.dataset[rootKey] !== '1') {
    doc.documentElement.dataset[rootKey] = '1';

    doc.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof Element) || target.closest('[data-room-color-picker]')) {
        return;
      }

      getAllPickers().forEach((picker) => closePicker(picker));
    });

    doc.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') return;
      getAllPickers().forEach((picker) => closePicker(picker));
    });
  }
}

export function setupRoomSorting(root: Document | Element = document): void {
  const grids = Array.from(root.querySelectorAll<HTMLElement>('[data-rooms-grid][data-rooms-reorder-url]'));
  if (!grids.length) return;

  const doc = root instanceof Document ? root : root.ownerDocument || document;
  const csrfToken = doc.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  grids.forEach((grid) => {
    if (grid.dataset.roomSortingBound === '1') return;
    grid.dataset.roomSortingBound = '1';

    let draggedCard: HTMLElement | null = null;
    let placeholder: HTMLElement | null = null;
    let activeHandle: HTMLElement | null = null;
    let dragPointerId: number | null = null;
    let dragOffsetX = 0;
    let dragOffsetY = 0;
    let pendingPointer: { x: number; y: number } | null = null;
    let frameHandle = 0;
    let previousOrder: string[] = [];
    const cardAnimations = new WeakMap<HTMLElement, Animation>();

    const cards = (): HTMLElement[] =>
      Array.from(grid.querySelectorAll<HTMLElement>('[data-room-card][data-room-id]'));

    const serializeOrder = (): string[] =>
      cards()
        .map((card) => card.dataset.roomId || '')
        .filter((id) => id.length > 0);

    const getOrderSignature = (order: string[]): string => order.join(',');
    let lastPersistedOrder: string[] = serializeOrder();
    let lastPersistedSignature = getOrderSignature(lastPersistedOrder);
    let pendingPersistSignature: string | null = null;
    let persistRequestInFlight = false;
    let persistRetryTimer: number | null = null;
    let hasShownPersistThrottleNotice = false;

    const restoreOrder = (order: string[]): void => {
      const lookup = new Map<string, HTMLElement>();
      cards().forEach((card) => {
        const id = card.dataset.roomId;
        if (!id) return;
        lookup.set(id, card);
      });

      order.forEach((id) => {
        const card = lookup.get(id);
        if (card) {
          grid.appendChild(card);
        }
      });
    };

    const showSortNotice = (message: string, type: 'success' | 'danger'): void => {
      window.showFlashNotification?.(message, {
        type,
        source: 'rooms-sort-order',
        duration: type === 'success' ? 1800 : 4200,
      });
    };

    const snapshotCardRects = (): Map<HTMLElement, DOMRect> => {
      const rects = new Map<HTMLElement, DOMRect>();
      cards().forEach((card) => {
        if (card !== draggedCard) {
          rects.set(card, card.getBoundingClientRect());
        }
      });
      return rects;
    };

    const getClosestCardForPointer = (x: number, y: number): {
      anchor: HTMLElement;
      placeAfter: boolean;
    } | null => {
      const candidates = cards().filter((card) => card !== draggedCard);
      if (!candidates.length) return null;

      const hoveredCard = candidates.find((candidate) => {
        const rect = candidate.getBoundingClientRect();
        return x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom;
      });

      if (hoveredCard) {
        const rect = hoveredCard.getBoundingClientRect();
        const xRatio = rect.width > 0 ? (x - rect.left) / rect.width : 0.5;
        const yRatio = rect.height > 0 ? (y - rect.top) / rect.height : 0.5;
        const useVerticalSplit = yRatio < 0.25 || yRatio > 0.75;
        const placeAfter = useVerticalSplit
          ? y > rect.top + rect.height / 2
          : xRatio > 0.5;
        return {
          anchor: hoveredCard,
          placeAfter,
        };
      }

      let nearest: { distance: number; element: HTMLElement } | null = null;

      candidates.forEach((candidate) => {
        const rect = candidate.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;
        const distance = Math.hypot(centerX - x, (centerY - y) * 1.35);
        if (!nearest || distance < nearest.distance) {
          nearest = {
            distance,
            element: candidate,
          };
        }
      });

      if (!nearest) return null;

      const rect = nearest.element.getBoundingClientRect();
      const middleX = rect.left + rect.width / 2;
      const middleY = rect.top + rect.height / 2;
      const deltaX = x - middleX;
      const deltaY = y - middleY;
      const useVerticalSplit = Math.abs(deltaY) > Math.abs(deltaX) * 0.95;
      const placeAfter = useVerticalSplit ? deltaY > 0 : deltaX > 0;

      return {
        anchor: nearest.element,
        placeAfter,
      };
    };

    const animateCardReflow = (beforeRects: Map<HTMLElement, DOMRect>): void => {
      cards().forEach((card) => {
        if (card === draggedCard) return;
        const beforeRect = beforeRects.get(card);
        if (!beforeRect) return;

        const afterRect = card.getBoundingClientRect();
        const deltaX = beforeRect.left - afterRect.left;
        const deltaY = beforeRect.top - afterRect.top;
        if (Math.abs(deltaX) < 0.5 && Math.abs(deltaY) < 0.5) return;

        cardAnimations.get(card)?.cancel();
        const animation = card.animate(
          [
            { transform: `translate(${deltaX}px, ${deltaY}px)` },
            { transform: 'translate(0, 0)' },
          ],
          {
            duration: 220,
            easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
          },
        );
        cardAnimations.set(card, animation);
        animation.addEventListener('finish', () => {
          if (cardAnimations.get(card) === animation) {
            cardAnimations.delete(card);
          }
        });
        animation.addEventListener('cancel', () => {
          if (cardAnimations.get(card) === animation) {
            cardAnimations.delete(card);
          }
        });
      });
    };

    const applyDraggedCardPosition = (x: number, y: number): void => {
      if (!draggedCard) return;
      draggedCard.style.left = `${Math.round(x - dragOffsetX)}px`;
      draggedCard.style.top = `${Math.round(y - dragOffsetY)}px`;
    };

    const movePlaceholderToPointer = (x: number, y: number): void => {
      if (!placeholder || !draggedCard) return;
      const beforeRects = snapshotCardRects();
      const placement = getClosestCardForPointer(x, y);

      if (!placement) {
        if (grid.lastElementChild !== placeholder) {
          grid.appendChild(placeholder);
          animateCardReflow(beforeRects);
        }
        return;
      }

      let referenceNode: Element | null = placement.placeAfter
        ? placement.anchor.nextElementSibling
        : placement.anchor;
      while (referenceNode === draggedCard) {
        referenceNode = referenceNode.nextElementSibling;
      }
      if (referenceNode === placeholder || placeholder.nextElementSibling === referenceNode) {
        return;
      }
      grid.insertBefore(placeholder, referenceNode);
      animateCardReflow(beforeRects);
    };

    const processPendingPointer = (): void => {
      frameHandle = 0;
      if (!pendingPointer || !draggedCard) return;
      const pointer = pendingPointer;
      pendingPointer = null;
      applyDraggedCardPosition(pointer.x, pointer.y);
      movePlaceholderToPointer(pointer.x, pointer.y);
    };

    const queuePointerFrame = (): void => {
      if (frameHandle) return;
      frameHandle = window.requestAnimationFrame(processPendingPointer);
    };

    const signatureToPayload = (signature: string): number[] =>
      signature
        .split(',')
        .map((id) => Number(id))
        .filter((id) => Number.isInteger(id) && id > 0);

    const queuePersistOrder = (): void => {
      const signature = getOrderSignature(serializeOrder());
      if (!signature || signature === lastPersistedSignature) {
        return;
      }
      pendingPersistSignature = signature;
      if (!persistRequestInFlight && persistRetryTimer === null) {
        void flushPersistQueue();
      }
    };

    const schedulePersistRetry = (response: Response): void => {
      const retryAfterRaw = Number(response.headers.get('retry-after') || '');
      const delayMs = Number.isFinite(retryAfterRaw) && retryAfterRaw > 0
        ? Math.max(250, retryAfterRaw * 1000)
        : 1500;

      if (persistRetryTimer !== null) {
        window.clearTimeout(persistRetryTimer);
      }
      persistRetryTimer = window.setTimeout(() => {
        persistRetryTimer = null;
        void flushPersistQueue();
      }, delayMs);
    };

    const flushPersistQueue = async (): Promise<void> => {
      if (persistRequestInFlight || !pendingPersistSignature) return;
      if (!grid.dataset.roomsReorderUrl) {
        pendingPersistSignature = null;
        return;
      }

      const signature = pendingPersistSignature;
      pendingPersistSignature = null;

      if (!signature || signature === lastPersistedSignature) {
        if (pendingPersistSignature) {
          void flushPersistQueue();
        }
        return;
      }

      const payload = signatureToPayload(signature);
      if (!payload.length) return;

      persistRequestInFlight = true;
      try {
        const response = await fetch(grid.dataset.roomsReorderUrl, {
          method: 'PATCH',
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
          },
          body: JSON.stringify({
            room_order: payload,
          }),
        });

        if (response.status === 429) {
          pendingPersistSignature = getOrderSignature(serializeOrder());
          if (!hasShownPersistThrottleNotice) {
            showSortNotice('Too many reorder requests. Please wait a moment.', 'danger');
            hasShownPersistThrottleNotice = true;
          }
          schedulePersistRetry(response);
          return;
        }

        if (!response.ok) {
          restoreOrder(lastPersistedOrder);
          pendingPersistSignature = null;
          showSortNotice('Could not save room order.', 'danger');
          return;
        }

        lastPersistedSignature = signature;
        lastPersistedOrder = signature.split(',').filter((id) => id.length > 0);
        hasShownPersistThrottleNotice = false;
      } catch (error) {
        restoreOrder(lastPersistedOrder);
        pendingPersistSignature = null;
        console.error('Failed to persist room order', error);
        showSortNotice('Could not save room order.', 'danger');
      } finally {
        persistRequestInFlight = false;
        if (!persistRetryTimer && pendingPersistSignature) {
          void flushPersistQueue();
        }
      }
    };

    const clearSortingState = (): void => {
      grid.classList.remove('is-sorting');
      doc.body.classList.remove('is-room-sorting');
    };

    const cleanupDraggedCardStyles = (card: HTMLElement): void => {
      card.style.removeProperty('position');
      card.style.removeProperty('left');
      card.style.removeProperty('top');
      card.style.removeProperty('width');
      card.style.removeProperty('height');
      card.style.removeProperty('z-index');
      card.style.removeProperty('pointer-events');
      card.style.removeProperty('margin');
      card.style.removeProperty('transition');
      card.style.removeProperty('will-change');
      card.classList.remove('is-dragging', 'is-drag-floating');
    };

    const removePlaceholder = (): void => {
      if (!placeholder) return;
      placeholder.remove();
      placeholder = null;
    };

    const endPointerDrag = (): void => {
      if (!draggedCard) return;

      if (frameHandle) {
        window.cancelAnimationFrame(frameHandle);
        frameHandle = 0;
      }
      pendingPointer = null;

      if (placeholder?.parentElement === grid) {
        grid.insertBefore(draggedCard, placeholder);
      }
      removePlaceholder();

      const hasChanged = previousOrder.join(',') !== serializeOrder().join(',');
      cleanupDraggedCardStyles(draggedCard);

      if (activeHandle && dragPointerId !== null && activeHandle.hasPointerCapture(dragPointerId)) {
        activeHandle.releasePointerCapture(dragPointerId);
      }
      activeHandle = null;
      dragPointerId = null;
      draggedCard = null;
      clearSortingState();

      if (hasChanged) {
        queuePersistOrder();
      }
    };

    const handlePointerMove = (event: PointerEvent): void => {
      if (!draggedCard || dragPointerId === null || event.pointerId !== dragPointerId) return;
      event.preventDefault();
      pendingPointer = { x: event.clientX, y: event.clientY };
      queuePointerFrame();
    };

    const handlePointerUp = (event: PointerEvent): void => {
      if (dragPointerId === null || event.pointerId !== dragPointerId) return;
      event.preventDefault();
      endPointerDrag();
    };

    const handlePointerCancel = (event: PointerEvent): void => {
      if (dragPointerId === null || event.pointerId !== dragPointerId) return;
      endPointerDrag();
    };

    const handleWindowBlur = (): void => {
      if (!draggedCard) return;
      endPointerDrag();
    };

    const startPointerDrag = (card: HTMLElement, handle: HTMLElement, event: PointerEvent): void => {
      if (draggedCard || event.button !== 0) return;

      const rect = card.getBoundingClientRect();
      previousOrder = serializeOrder();
      draggedCard = card;
      activeHandle = handle;
      dragPointerId = event.pointerId;
      dragOffsetX = event.clientX - rect.left;
      dragOffsetY = event.clientY - rect.top;

      placeholder = doc.createElement('div');
      placeholder.className = 'room-card-sort-placeholder';
      placeholder.style.height = `${Math.round(rect.height)}px`;
      placeholder.style.width = `${Math.round(rect.width)}px`;
      grid.insertBefore(placeholder, card);

      card.classList.add('is-dragging', 'is-drag-floating');
      card.style.position = 'fixed';
      card.style.left = `${Math.round(rect.left)}px`;
      card.style.top = `${Math.round(rect.top)}px`;
      card.style.width = `${Math.round(rect.width)}px`;
      card.style.height = `${Math.round(rect.height)}px`;
      card.style.zIndex = '1300';
      card.style.pointerEvents = 'none';
      card.style.margin = '0';
      card.style.transition = 'none';
      card.style.willChange = 'left, top';
      grid.classList.add('is-sorting');
      doc.body.classList.add('is-room-sorting');

      if (!handle.hasPointerCapture(event.pointerId)) {
        handle.setPointerCapture(event.pointerId);
      }

      pendingPointer = { x: event.clientX, y: event.clientY };
      queuePointerFrame();
    };

    cards().forEach((card) => {
      if (card.dataset.roomSortCardBound === '1') return;
      card.dataset.roomSortCardBound = '1';
      card.setAttribute('draggable', 'false');

      const handle = card.querySelector<HTMLElement>('[data-room-sort-handle]');
      if (!handle) return;

      handle.addEventListener('pointerdown', (event) => {
        if (!(event instanceof PointerEvent)) return;
        event.preventDefault();
        startPointerDrag(card, handle, event);
      });
    });

    doc.addEventListener('pointermove', handlePointerMove, { passive: false, capture: true });
    doc.addEventListener('pointerup', handlePointerUp, { passive: false, capture: true });
    doc.addEventListener('pointercancel', handlePointerCancel, { passive: false, capture: true });
    window.addEventListener('blur', handleWindowBlur);

    grid.addEventListener('dragstart', (event) => {
      if (!draggedCard) {
        event.preventDefault();
        return;
      }
      event.preventDefault();
    });
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
