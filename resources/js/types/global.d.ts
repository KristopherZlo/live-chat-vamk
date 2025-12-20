import type Alpine from 'alpinejs';
import type axios from 'axios';
import type Echo from 'laravel-echo';
import type Pusher from 'pusher-js';
import type { createIcons, icons } from 'lucide';

type ReverbConfig = {
    key?: string;
    host?: string;
    port?: number | string;
    scheme?: string;
};

type LucideIcons = typeof icons;
type LucideCreateIcons = typeof createIcons;

type LucideGlobal = {
    createIcons?: LucideCreateIcons;
    icons?: LucideIcons;
};

type FlashNotificationOptions = {
    type?: 'success' | 'danger' | 'warning' | 'info' | string;
    source?: string;
    duration?: number;
};

declare global {
    interface Window {
        __reverbConfig?: ReverbConfig;
        __echoReady?: Promise<void>;
        __queueSoundDebug?: boolean;
        __networkStatusNotificationBound?: boolean;
        axios: typeof axios;
        Alpine: typeof Alpine;
        Pusher?: typeof Pusher;
        Echo?: Echo;
        lucide?: LucideGlobal;
        queueSoundUrl?: string;
        queueSeenQuestionIds?: Set<number>;
        queueSeenQuestionIdsKey?: string | null;
        maybeAutoloadQueue?: (force?: boolean) => void;
        createQrModules?: (
            link: string,
            canvasSize: number,
            options?: { quietModules?: number; errorCorrectionLevel?: string },
        ) => null | { modules: boolean[][]; moduleSize: number; moduleCount: number; offset: number };
        refreshLucideIcons?: (root?: Document | Element) => void;
        showFlashNotification?: (message: string, options?: FlashNotificationOptions) => HTMLElement;
        setupFlashMessages?: (root?: Document | Element) => void;
        setupNetworkStatusNotification?: () => void;
        markQueueHasNew?: () => void;
        playQueueSound?: (url?: string) => void;
        initQueueSoundPlayer?: (url?: string) => void;
        isQueueSoundEnabled?: () => boolean;
        setupQueueNewHandlers?: (root?: Document | Element) => void;
        setupQueueFilter?: (root?: Document | Element) => void;
        markQueueItemSeen?: (questionId: number | string, root?: Document | Element) => void;
        rebindQueuePanels?: (root?: Document | Element) => void;
    }
}

export {};
