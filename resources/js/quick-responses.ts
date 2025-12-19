const STORAGE_KEY = 'gr:quickResponses';
const MAX_RESPONSES = 3;
const FALLBACK_RESPONSES = [
    'What was unclear in the last topic?',
    'Where should we start the lecture?',
    'Any questions at this point?',
];

const parseJson = (value: unknown): unknown | null => {
    if (typeof value !== 'string') {
        return null;
    }
    try {
        return JSON.parse(value);
    } catch (error) {
        console.error('Failed to parse quick response defaults', error);
        return null;
    }
};

const normalizeResponses = (items: unknown = []): string[] => {
    const normalized: string[] = [];
    if (!Array.isArray(items)) {
        return normalized;
    }
    items.forEach((raw) => {
        const value = String(raw ?? '').trim();
        if (value && normalized.length < MAX_RESPONSES) {
            normalized.push(value);
        }
    });
    return normalized;
};

const readStoredResponses = (): string[] | null => {
    if (typeof window.localStorage === 'undefined') {
        return null;
    }
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) {
            return null;
        }
        const parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) {
            return null;
        }
        return normalizeResponses(parsed);
    } catch (error) {
        console.error('Unable to read quick responses', error);
        return null;
    }
};

const saveResponses = (responses: string[]): void => {
    if (typeof window.localStorage === 'undefined') {
        return;
    }
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(responses));
    } catch (error) {
        console.error('Unable to persist quick responses', error);
    }
};

const setModalVisibility = (modal: HTMLElement | null, visible: boolean): void => {
    if (!modal) {
        return;
    }
    if (visible) {
        modal.classList.add('show');
        modal.removeAttribute('hidden');
        modal.setAttribute('aria-hidden', 'false');
    } else {
        modal.classList.remove('show');
        modal.setAttribute('hidden', '');
        modal.setAttribute('aria-hidden', 'true');
    }
};

const sendQuickResponse = (
    message: string,
    input: HTMLInputElement | HTMLTextAreaElement | null,
    form: HTMLFormElement | null,
): void => {
    if (!input || !form) {
        return;
    }
    const trimmed = String(message || '').trim();
    if (!trimmed) {
        return;
    }
    input.focus();
    input.value = trimmed;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    setTimeout(() => {
        input.focus();
    }, 0);
};

document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector<HTMLElement>('[data-quick-responses]');
    if (!container) {
        return;
    }

    const parsedDefaults = parseJson(container.dataset.defaultResponses);
    const defaultResponses = normalizeResponses(parsedDefaults ?? FALLBACK_RESPONSES);
    const stored = readStoredResponses();
    let responses = stored ?? defaultResponses;

    const buttonsHost = container.querySelector<HTMLElement>('[data-quick-responses-buttons]');
    const settingsButton = container.querySelector<HTMLElement>('[data-quick-responses-settings]');
    const modal = document.querySelector<HTMLElement>('[data-quick-responses-modal]');
    const form = modal?.querySelector<HTMLFormElement>('[data-quick-responses-form]');
    const list = form?.querySelector<HTMLElement>('[data-quick-responses-list]');
    const addButton = form?.querySelector<HTMLButtonElement>('[data-quick-response-add]');
    const chatInput = document.getElementById('chatInput') as HTMLTextAreaElement | null;
    const chatForm = document.getElementById('chat-form') as HTMLFormElement | null;

    if (!buttonsHost) {
        return;
    }

    const renderButtons = (): void => {
        if (!buttonsHost) return;
        buttonsHost.innerHTML = '';
        responses.forEach((message, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'quick-responses__button';
            button.textContent = message;
            button.setAttribute('data-quick-response-btn', '1');
            button.setAttribute('aria-label', `Send quick response ${index + 1}: ${message}`);
            button.addEventListener('click', () => {
                sendQuickResponse(message, chatInput, chatForm);
            });
            buttonsHost.appendChild(button);
        });
    };

    renderButtons();

    const updateAddButtonState = (): void => {
        if (!addButton || !list) return;
        const atLimit = list.children.length >= MAX_RESPONSES;
        addButton.disabled = atLimit;
        addButton.hidden = atLimit;
    };

    const renumberRows = (): void => {
        if (!list) return;
        Array.from(list.querySelectorAll<HTMLElement>('.input-label')).forEach((label, idx) => {
            label.textContent = `Message ${idx + 1}`;
        });
    };

    const addRow = (value = ''): HTMLInputElement | null => {
        if (!list || list.children.length >= MAX_RESPONSES) return null;
        const row = document.createElement('div');
        row.className = 'quick-response-row';

        const label = document.createElement('label');
        label.className = 'input-field';

        const title = document.createElement('span');
        title.className = 'input-label';
        title.textContent = `Message ${list.children.length + 1}`;

        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'quick-responses-input';
        input.setAttribute('data-quick-response-input', '1');
        input.maxLength = 200;
        input.placeholder = 'Type a quick reply';
        input.value = value;
        // Keep quick response inputs from triggering global shortcuts when typing space/enter.
        input.addEventListener('keydown', (event) => {
            if (event.key === ' ' || event.key === 'Enter') {
                event.stopPropagation();
            }
        });

        label.appendChild(title);
        label.appendChild(input);

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'quick-response-remove';
        removeBtn.title = 'Remove response';
        removeBtn.innerHTML = '<i data-lucide="trash-2"></i>';
        removeBtn.addEventListener('click', () => {
            row.remove();
            renumberRows();
            updateAddButtonState();
        });

        row.appendChild(label);
        row.appendChild(removeBtn);
        list.appendChild(row);

        if (window.refreshLucideIcons) {
            window.refreshLucideIcons(row);
        }

        return input;
    };

    const openModal = (): void => {
        if (!modal || !list) {
            return;
        }
        list.innerHTML = '';
        const values = responses.length ? responses : [''];
        values.forEach((value) => addRow(value));
        updateAddButtonState();
        renumberRows();
        setModalVisibility(modal, true);
        setTimeout(() => {
            const firstInput = list.querySelector<HTMLInputElement>('input');
            firstInput?.focus();
        }, 0);
    };

    const closeModal = (): void => {
        setModalVisibility(modal, false);
        if (chatInput) {
            chatInput.focus();
        }
    };

    addButton?.addEventListener('click', () => {
        const input = addRow('');
        updateAddButtonState();
        renumberRows();
        if (input) {
            input.focus();
        }
    });

    settingsButton?.addEventListener('click', openModal);

    const closeButtons = modal
        ? Array.from(modal.querySelectorAll<HTMLElement>('[data-quick-responses-modal-close]'))
        : [];
    closeButtons.forEach((button) => button.addEventListener('click', closeModal));

    modal?.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal?.classList.contains('show')) {
            closeModal();
        }
    });

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        const updated = list
            ? Array.from(list.querySelectorAll<HTMLInputElement>('[data-quick-response-input]'))
                .map((input) => String(input.value || '').trim())
            : [];
        responses = normalizeResponses(updated);
        saveResponses(responses);
        renderButtons();
        closeModal();
    });
});

