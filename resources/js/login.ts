(() => {
    if (!document.body.classList.contains('login-page')) {
        return;
    }

    type AuthMode = 'login' | 'register';

    const shell = document.querySelector<HTMLElement>('[data-auth-shell]');
    if (!shell) {
        return;
    }

    const forms = Array.from(shell.querySelectorAll<HTMLFormElement>('[data-auth-form]'));
    const switchers = Array.from(shell.querySelectorAll<HTMLAnchorElement>('[data-auth-switch]'));
    const tabs = switchers.filter((el) => el.closest('.auth-switch') !== null);
    const title = shell.querySelector<HTMLElement>('[data-auth-title]');
    const subtitle = shell.querySelector<HTMLElement>('[data-auth-subtitle]');

    const labels: Record<AuthMode, { title: string; subtitle: string }> = {
        login: {
            title: 'Sign in',
            subtitle: 'Use your existing account credentials.',
        },
        register: {
            title: 'Create account',
            subtitle: 'A valid invite code is required to create an account.',
        },
    };

    const getModeFromPath = (): AuthMode => {
        return window.location.pathname.toLowerCase().includes('/register') ? 'register' : 'login';
    };

    const firstFocusable = (form: HTMLFormElement): HTMLInputElement | null => {
        return form.querySelector<HTMLInputElement>('input:not([type="hidden"])');
    };

    const setMode = (nextMode: AuthMode, options?: { pushHistory?: boolean; focus?: boolean }): void => {
        const pushHistory = options?.pushHistory ?? false;
        const focusField = options?.focus ?? false;

        shell.dataset.authMode = nextMode;

        forms.forEach((form) => {
            const isActive = form.dataset.authForm === nextMode;
            form.classList.toggle('is-active', isActive);
            form.hidden = !isActive;

            if (isActive && focusField) {
                const target = firstFocusable(form);
                target?.focus();
            }
        });

        tabs.forEach((tab) => {
            const isActive = tab.dataset.authSwitch === nextMode;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');

            if (pushHistory && isActive) {
                const href = tab.getAttribute('href');
                if (href && href !== window.location.pathname) {
                    window.history.pushState({ authMode: nextMode }, '', href);
                }
            }
        });

        if (title) {
            title.textContent = labels[nextMode].title;
        }

        if (subtitle) {
            subtitle.textContent = labels[nextMode].subtitle;
        }
    };

    const safeMode = (rawMode: string | null | undefined): AuthMode => {
        return rawMode === 'register' ? 'register' : 'login';
    };

    switchers.forEach((switcher) => {
        switcher.addEventListener('click', (event) => {
            if (event.defaultPrevented) {
                return;
            }

            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            const nextMode = safeMode(switcher.dataset.authSwitch);
            event.preventDefault();
            setMode(nextMode, { pushHistory: true, focus: true });
        });
    });

    window.addEventListener('popstate', () => {
        setMode(getModeFromPath(), { pushHistory: false, focus: false });
    });

    const initialMode = safeMode(shell.dataset.authMode ?? getModeFromPath());
    setMode(initialMode, { pushHistory: false, focus: false });
})();
