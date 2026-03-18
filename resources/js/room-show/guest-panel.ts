type LoggerLike = {
    warn: (...args: unknown[]) => void;
    error: (...args: unknown[]) => void;
};

type GuestAccessControllerOptions = {
    isOwnerUser: boolean;
    chatInputWrapper: Element | null;
    escapeHtml: (value: string) => string;
    windowRef?: Window;
    reloadDelayMs?: number;
};

type GuestAccessController = {
    showBanState: (messageText?: string) => void;
    handleGuestAccessRevoked: (messageText?: string) => boolean;
};

type SubmitRemoteFormOptions = {
    form: HTMLFormElement;
    csrfToken: string;
    handleGuestAccessRevoked: (messageText?: string) => boolean;
    logger: LoggerLike;
    onDone?: () => void;
    windowRef?: Window;
};

type MyQuestionsPanelControllerOptions = {
    panel: HTMLElement | null;
    panelUrl?: string | null;
    logger: LoggerLike;
    handleGuestAccessRevoked: (messageText?: string) => boolean;
    refreshIcons?: (() => void) | null;
    confirmQuestionDelete: () => Promise<boolean>;
    submitRemoteForm: (form: HTMLFormElement, onDone?: () => void) => Promise<boolean>;
    windowRef?: Window;
    pollIntervalMs?: number;
};

type MyQuestionsPanelController = {
    reload: () => Promise<void>;
    startPolling: () => void;
    bind: () => void;
};

export function createGuestAccessController(options: GuestAccessControllerOptions): GuestAccessController {
    const windowRef = options.windowRef ?? window;
    const reloadDelayMs = options.reloadDelayMs ?? 150;

    const showBanState = (messageText = 'Access to this room was revoked. Reloading...') => {
        if (!options.chatInputWrapper) {
            return;
        }

        options.chatInputWrapper.innerHTML = `
            <div class="flash flash-danger">
                <span>${options.escapeHtml(messageText)}</span>
            </div>
        `;
    };

    const handleGuestAccessRevoked = (messageText = 'Access to this room was revoked. Reloading...') => {
        if (options.isOwnerUser) {
            return false;
        }

        showBanState(messageText);
        windowRef.setTimeout(() => windowRef.location.reload(), reloadDelayMs);

        return true;
    };

    return {
        showBanState,
        handleGuestAccessRevoked,
    };
}

function buildFormData(form: HTMLFormElement): FormData {
    const view = form.ownerDocument?.defaultView;
    if (view && typeof view.FormData === 'function') {
        return new view.FormData(form);
    }

    return new FormData(form);
}

export async function submitRemoteForm(options: SubmitRemoteFormOptions): Promise<boolean> {
    const formData = buildFormData(options.form);
    let method = (options.form.getAttribute('method') || 'POST').toUpperCase();
    const override = formData.get('_method');
    if (override) {
        method = override.toString().toUpperCase();
    }

    const token = formData.get('_token') || options.csrfToken || '';
    const windowRef = options.windowRef ?? window;
    const actionAttr = (options.form.getAttribute('action') || '').trim();
    const actionUrl = actionAttr ? new URL(actionAttr, windowRef.location.href) : null;

    if (!actionUrl) {
        options.logger.warn('Remote form skipped: missing action');

        return false;
    }

    if (actionUrl.pathname === windowRef.location.pathname || /\/r\/[^/]+/.test(actionUrl.pathname)) {
        options.logger.warn('Remote form skipped: action points to room page', actionUrl.pathname);

        return false;
    }

    try {
        const response = await fetch(actionUrl.toString(), {
            method,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token,
            },
            credentials: 'same-origin',
            body: formData,
        });
        const ok = response.status >= 200 && response.status < 400;

        if (!ok) {
            if (response.status === 403) {
                options.handleGuestAccessRevoked();
            }

            options.logger.error('Remote form failed', response.status);

            return false;
        }

        if (typeof options.onDone === 'function') {
            options.onDone();
        }

        return true;
    } catch (error) {
        options.logger.error('Remote form error', error);

        return false;
    }
}

export function createMyQuestionsPanelController(options: MyQuestionsPanelControllerOptions): MyQuestionsPanelController {
    const panel = options.panel;
    const panelUrl = options.panelUrl ?? null;
    const windowRef = options.windowRef ?? window;
    const pollIntervalMs = options.pollIntervalMs ?? 6000;
    let pollTimer: number | null = null;

    const reload = async () => {
        if (!panel || !panelUrl) {
            return;
        }

        try {
            const response = await fetch(panelUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                if (response.status === 403) {
                    options.handleGuestAccessRevoked();

                    return;
                }

                options.logger.error('Failed to refresh my questions panel', response.status);

                return;
            }

            const html = await response.text();
            panel.innerHTML = html;
            options.refreshIcons?.();
        } catch (error) {
            options.logger.error('Refresh my questions panel error', error);
        }
    };

    const startPolling = () => {
        if (!panel || pollTimer !== null) {
            return;
        }

        pollTimer = windowRef.setInterval(reload, pollIntervalMs);
    };

    const bind = () => {
        if (!panel) {
            return;
        }

        panel.addEventListener('submit', async (event) => {
            const target = event.target;
            if (!(target instanceof HTMLFormElement)) {
                return;
            }

            if (target.dataset.remote !== 'my-questions-panel') {
                return;
            }

            event.preventDefault();

            const methodAttr = (target.getAttribute('method') || 'POST').toUpperCase();
            const methodOverride = (target.querySelector('input[name="_method"]')?.value || '').toUpperCase();
            const effectiveMethod = methodOverride || methodAttr;
            const isDeleteAction = effectiveMethod === 'DELETE';
            const isQuestionDelete = isDeleteAction || target.dataset.questionDelete === '1';

            if (isQuestionDelete) {
                const confirmed = await options.confirmQuestionDelete();
                if (!confirmed) {
                    return;
                }
            }

            await options.submitRemoteForm(target, reload);
        });
    };

    return {
        reload,
        startPolling,
        bind,
    };
}
