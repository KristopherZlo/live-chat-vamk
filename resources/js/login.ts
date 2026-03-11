(() => {
    if (!document.body.classList.contains('login-page')) {
        return;
    }

    const authRouteFragments = ['/login', '/register', '/verify-email'];

    const isAuthPath = (pathname: string): boolean => {
        return authRouteFragments.some((fragment) => pathname.includes(fragment));
    };

    const clearClientErrors = (form: HTMLFormElement): void => {
        form.querySelectorAll('.auth-input-error--js').forEach((node) => node.remove());
    };

    const renderClientErrors = (form: HTMLFormElement, errors: Record<string, string[]>): void => {
        Object.entries(errors).forEach(([field, messages]) => {
            const input = form.querySelector<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>(`[name="${field}"]`);
            const fieldWrap = input?.closest('.auth-field');

            if (!fieldWrap || !messages.length) {
                return;
            }

            const list = document.createElement('ul');
            list.className = 'auth-input-error auth-input-error--js';

            messages.forEach((message) => {
                const item = document.createElement('li');
                item.textContent = message;
                list.appendChild(item);
            });

            fieldWrap.appendChild(list);
        });
    };

    const setSubmitting = (form: HTMLFormElement, submitting: boolean): void => {
        form.dataset.submitting = submitting ? '1' : '0';

        const submit = form.querySelector<HTMLButtonElement>('button[type="submit"]');
        if (!submit) {
            return;
        }

        if (!submit.dataset.originalText) {
            submit.dataset.originalText = submit.textContent ?? '';
        }

        submit.disabled = submitting;
        submit.textContent = submitting ? 'Creating account...' : submit.dataset.originalText;
    };

    const bindVerificationCodeInputs = (): void => {
        const form = document.querySelector<HTMLFormElement>('[data-verification-code-form]');
        if (!form || form.dataset.codeBound === '1') {
            return;
        }

        const hiddenInput = form.querySelector<HTMLInputElement>('[data-verification-code]');
        const digitInputs = Array.from(form.querySelectorAll<HTMLInputElement>('[data-code-digit]'));
        if (!hiddenInput || digitInputs.length === 0) {
            return;
        }

        const maxLength = digitInputs.length;
        form.dataset.codeBound = '1';

        const applyFromHidden = (): void => {
            const value = (hiddenInput.value ?? '').replace(/\D/g, '').slice(0, maxLength);
            for (let i = 0; i < maxLength; i += 1) {
                const char = value[i] ?? '';
                digitInputs[i].value = char;
                digitInputs[i].dataset.filled = char ? '1' : '0';
            }
        };

        const syncHidden = (): void => {
            hiddenInput.value = digitInputs.map((input) => input.value).join('');
            digitInputs.forEach((input) => {
                input.dataset.filled = input.value ? '1' : '0';
            });
        };

        const focusAt = (index: number): void => {
            if (index < 0 || index >= maxLength) {
                return;
            }
            digitInputs[index].focus();
            digitInputs[index].select();
        };

        digitInputs.forEach((input, index) => {
            input.addEventListener('input', () => {
                const numeric = input.value.replace(/\D/g, '');
                input.value = numeric.slice(-1);
                syncHidden();

                if (input.value && index < maxLength - 1) {
                    focusAt(index + 1);
                }
            });

            input.addEventListener('keydown', (event) => {
                if (event.key === 'Backspace' && !input.value && index > 0) {
                    focusAt(index - 1);
                    return;
                }

                if (event.key === 'ArrowLeft') {
                    event.preventDefault();
                    focusAt(index - 1);
                    return;
                }

                if (event.key === 'ArrowRight') {
                    event.preventDefault();
                    focusAt(index + 1);
                }
            });

            input.addEventListener('focus', () => {
                input.select();
            });
        });

        form.addEventListener('paste', (event) => {
            const pasted = event.clipboardData?.getData('text') ?? '';
            const numeric = pasted.replace(/\D/g, '').slice(0, maxLength);

            if (!numeric) {
                return;
            }

            event.preventDefault();
            for (let i = 0; i < maxLength; i += 1) {
                digitInputs[i].value = numeric[i] ?? '';
            }
            syncHidden();

            const nextIndex = Math.min(numeric.length, maxLength - 1);
            focusAt(nextIndex);
        });

        form.addEventListener('submit', () => {
            syncHidden();
        });

        applyFromHidden();
    };

    const bindResendCooldown = (): void => {
        const resendButton = document.querySelector<HTMLButtonElement>('[data-resend-button]');
        if (!resendButton || resendButton.dataset.cooldownBound === '1') {
            return;
        }

        resendButton.dataset.cooldownBound = '1';

        const timerLabel = document.querySelector<HTMLElement>('[data-resend-timer]');
        let remaining = Number.parseInt(resendButton.dataset.resendSeconds ?? '0', 10);

        if (!Number.isFinite(remaining) || remaining <= 0) {
            resendButton.disabled = false;
            if (timerLabel) {
                timerLabel.hidden = true;
                timerLabel.textContent = '';
            }
            return;
        }

        resendButton.disabled = true;

        const render = (): void => {
            if (!timerLabel) {
                return;
            }

            if (remaining > 0) {
                timerLabel.hidden = false;
                timerLabel.textContent = `available in ${remaining}s`;
                return;
            }

            timerLabel.hidden = true;
            timerLabel.textContent = '';
        };

        render();

        const intervalId = window.setInterval(() => {
            if (!resendButton.isConnected) {
                window.clearInterval(intervalId);
                return;
            }

            remaining -= 1;
            if (remaining <= 0) {
                remaining = 0;
                resendButton.disabled = false;
                resendButton.dataset.resendSeconds = '0';
                render();
                window.clearInterval(intervalId);
                return;
            }

            render();
        }, 1000);
    };

    const swapAuthPage = async (url: string, pushHistory: boolean): Promise<boolean> => {
        const currentPage = document.querySelector<HTMLElement>('.auth-page');
        if (!currentPage) {
            return false;
        }

        currentPage.classList.add('is-switching');

        try {
            const response = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                return false;
            }

            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const nextPage = doc.querySelector<HTMLElement>('.auth-page');

            if (!nextPage) {
                return false;
            }

            currentPage.replaceWith(nextPage);
            document.title = doc.title;

            if (pushHistory) {
                window.history.pushState({ authAsync: true }, '', url);
            }

            bindRegisterFormAsync();
            bindVerificationCodeInputs();
            bindResendCooldown();
            return true;
        } finally {
            const page = document.querySelector<HTMLElement>('.auth-page');
            page?.classList.remove('is-switching');
        }
    };

    const bindRegisterFormAsync = (): void => {
        const registerForm = document.querySelector<HTMLFormElement>('form[action*="/register"]');
        if (!registerForm || registerForm.dataset.asyncBound === '1') {
            return;
        }

        registerForm.dataset.asyncBound = '1';

        registerForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (registerForm.dataset.submitting === '1') {
                return;
            }

            clearClientErrors(registerForm);
            setSubmitting(registerForm, true);

            try {
                const response = await fetch(registerForm.action, {
                    method: 'POST',
                    body: new FormData(registerForm),
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (response.status === 422) {
                    const payload = await response.json() as { errors?: Record<string, string[]> };
                    renderClientErrors(registerForm, payload.errors ?? {});
                    return;
                }

                if (!response.ok) {
                    window.location.href = registerForm.action;
                    return;
                }

                const payload = await response.json() as { redirect?: string };
                const target = payload.redirect ?? '/verify-email';
                const swapped = await swapAuthPage(target, true);

                if (!swapped) {
                    window.location.href = target;
                }
            } catch {
                registerForm.submit();
            } finally {
                if (registerForm.isConnected) {
                    setSubmitting(registerForm, false);
                }
            }
        });
    };

    bindRegisterFormAsync();
    bindVerificationCodeInputs();
    bindResendCooldown();

    window.addEventListener('popstate', () => {
        if (!isAuthPath(window.location.pathname)) {
            return;
        }

        void swapAuthPage(window.location.pathname + window.location.search, false);
    });
})();
