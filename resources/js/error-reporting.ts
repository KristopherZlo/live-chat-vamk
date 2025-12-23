type ErrorMetadata = Record<string, unknown>;

type ErrorPayload = {
    message: string;
    url: string;
    severity?: 'error' | 'warning' | 'info';
    source?: 'error' | 'unhandledrejection';
    stack?: string;
    line?: number;
    column?: number;
    metadata?: ErrorMetadata;
};

const MAX_REPORTS = 10;
let reportCount = 0;

const getMeta = (name: string): string => {
    const node = document.querySelector<HTMLMetaElement>(`meta[name="${name}"]`);
    return node?.content ?? '';
};

const buildEndpoint = (): string | null => {
    const baseUrl = getMeta('app-base-url') || window.location.origin;
    if (!baseUrl) {
        return null;
    }
    return `${baseUrl.replace(/\/$/, '')}/client-errors`;
};

const buildMetadata = (): ErrorMetadata | undefined => {
    const pageRequestId = getMeta('request-id');
    if (!pageRequestId) {
        return undefined;
    }
    return { page_request_id: pageRequestId };
};

const sendReport = (payload: ErrorPayload): void => {
    if (reportCount >= MAX_REPORTS) {
        return;
    }

    const endpoint = buildEndpoint();
    const csrfToken = getMeta('csrf-token');
    if (!endpoint || !csrfToken) {
        return;
    }

    reportCount += 1;

    fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        keepalive: true,
        body: JSON.stringify(payload),
    }).catch(() => {
        // Avoid noisy console output for telemetry failures.
    });
};

const normalizeStack = (stack: unknown): string | undefined => {
    if (typeof stack === 'string' && stack.trim()) {
        return stack;
    }
    return undefined;
};

const normalizeMessage = (value: unknown): string => {
    if (typeof value === 'string' && value.trim()) {
        return value;
    }
    return String(value ?? 'Unknown error');
};

const handleWindowError = (event: ErrorEvent): void => {
    const message = normalizeMessage(event.message);
    const payload: ErrorPayload = {
        message,
        url: event.filename || window.location.href,
        line: typeof event.lineno === 'number' ? event.lineno : undefined,
        column: typeof event.colno === 'number' ? event.colno : undefined,
        stack: normalizeStack(event.error?.stack),
        severity: 'error',
        source: 'error',
        metadata: buildMetadata(),
    };

    sendReport(payload);
};

const handleUnhandledRejection = (event: PromiseRejectionEvent): void => {
    const reason = (event as PromiseRejectionEvent).reason;
    const message = normalizeMessage(reason?.message ?? reason);
    const payload: ErrorPayload = {
        message,
        url: window.location.href,
        stack: normalizeStack(reason?.stack),
        severity: 'error',
        source: 'unhandledrejection',
        metadata: buildMetadata(),
    };

    sendReport(payload);
};

if (typeof window !== 'undefined') {
    window.addEventListener('error', (event) => {
        try {
            handleWindowError(event);
        } catch {
            // Swallow telemetry errors to avoid recursive failure.
        }
    });

    window.addEventListener('unhandledrejection', (event) => {
        try {
            handleUnhandledRejection(event);
        } catch {
            // Swallow telemetry errors to avoid recursive failure.
        }
    });
}
