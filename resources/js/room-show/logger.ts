type RoomLogger = {
    debug: (...args: unknown[]) => void;
    warn: (...args: unknown[]) => void;
    error: (...args: unknown[]) => void;
};

type RoomLoggerOptions = {
    debugEnabled?: () => boolean;
};

const IS_DEV: boolean = import.meta.env.DEV;

export function isRoomDebugEnabled(): boolean {
    if (!IS_DEV || typeof window === 'undefined') {
        return false;
    }

    try {
        return window.localStorage.getItem('lc-room-debug') === '1';
    } catch (error) {
        return false;
    }
}

export function createRoomLogger(options: RoomLoggerOptions = {}): RoomLogger {
    const debugEnabled = options.debugEnabled ?? isRoomDebugEnabled;

    return {
        debug: (...args: unknown[]) => {
            if (!debugEnabled()) {
                return;
            }

            console.debug(...args);
        },
        warn: (...args: unknown[]) => {
            console.warn(...args);
        },
        error: (...args: unknown[]) => {
            console.error(...args);
        },
    };
}

export const roomLogger = createRoomLogger();
