import type { RoomMeta, StoredRoom } from './types/rooms';

const STORAGE_KEY = 'gr:lastVisitedRooms';
const MAX_ROOMS = 9;

const sanitize = (value: unknown): string => String(value ?? '').trim();
const truncate = (value: string, maxLength = 128): string => {
    if (value.length <= maxLength) {
        return value;
    }
    return `${value.slice(0, maxLength).trimEnd()}...`;
};
const toRoomMeta = (value: StoredRoom): RoomMeta => ({
    slug: sanitize(value?.slug),
    title: sanitize(value?.title),
    description: sanitize(value?.description),
    owner: sanitize(value?.owner),
});

document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.localStorage === 'undefined') {
        return;
    }

    const panel = document.querySelector('[data-last-visited-panel]');
    const listEl = panel?.querySelector('[data-last-visited-list]');
    if (!panel || !listEl) {
        return;
    }

    const readStoredRooms = (): RoomMeta[] => {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return [];
            }
            const parsed = JSON.parse(raw);
            if (!Array.isArray(parsed)) {
                return [];
            }
            return parsed.map((item) => toRoomMeta(item)).filter((room) => room.slug);
        } catch (error) {
            console.error('Unable to read last visited rooms', error);
            return [];
        }
    };

    const persistRooms = (rooms: RoomMeta[]): void => {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(rooms));
        } catch (error) {
            console.error('Unable to save last visited rooms', error);
        }
    };

    const clearRooms = (): void => {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (error) {
            console.error('Unable to clear last visited rooms', error);
        }
    };

    const renderCard = (room: RoomMeta) => {
        const card = document.createElement('a');
        card.className = 'visited-room-card';
        card.href = buildUrl(`/r/${encodeURIComponent(room.slug)}`);
        card.setAttribute('aria-label', `Open ${room.title || 'meeting'}`);

        const icon = document.createElement('div');
        icon.className = 'visited-room-icon';
        icon.textContent = '#';

        const body = document.createElement('div');
        body.className = 'visited-room-body';

        const title = document.createElement('div');
        title.className = 'visited-room-title';
        title.textContent = room.title || 'Unknown';

        const description = document.createElement('div');
        description.className = 'visited-room-description';
        const descriptionText = room.description || 'Instant Meeting';
        description.textContent = truncate(descriptionText);

        const meta = document.createElement('div');
        meta.className = 'visited-room-meta';
        meta.textContent = `Host: ${room.owner || 'Guest User'}`;

        body.appendChild(title);
        body.appendChild(description);
        body.appendChild(meta);

        const action = document.createElement('span');
        action.className = 'visited-room-action';
        action.innerHTML = '<i data-lucide="arrow-right"></i>';

        card.appendChild(icon);
        card.appendChild(body);
        card.appendChild(action);

        return card;
    };

    const baseMeta = document.querySelector('meta[name="app-base-url"]');
    const appBase = baseMeta?.getAttribute('content') || window.location.origin;
    const normalizeBase = appBase.endsWith('/') ? appBase.slice(0, -1) : appBase;
    const buildUrl = (path: string): string => {
        const cleanPath = path.replace(/^\/+/, '');
        return `${normalizeBase}/${cleanPath}`;
    };

    const checkRoomExists = async (slug: string): Promise<boolean> => {
        try {
            const response = await fetch(buildUrl(`/rooms/${encodeURIComponent(slug)}/exists`));
            if (!response.ok) {
                return false;
            }
            const payload = await response.json().catch(() => null);
            return Boolean(payload && payload.exists);
        } catch (error) {
            console.error('Last visited room check failed', error);
            return false;
        }
    };

    const render = async (): Promise<void> => {
        const stored = readStoredRooms().slice(0, MAX_ROOMS);
        if (!stored.length) {
            panel.hidden = true;
            return;
        }

        panel.hidden = false;
        listEl.innerHTML = '';
        stored.forEach((room) => {
            listEl.appendChild(renderCard(room));
        });
        if (window.refreshLucideIcons) {
            window.refreshLucideIcons(panel);
        }

        const validations = await Promise.all(stored.map(async (room) => {
            if (!room.slug) {
                return null;
            }
            const exists = await checkRoomExists(room.slug);
            return exists ? room : null;
        }));

        const validRooms = validations.filter(Boolean);
        if (!validRooms.length) {
            clearRooms();
            panel.hidden = true;
            listEl.innerHTML = '';
            return;
        }

        persistRooms(validRooms);
        if (validRooms.length !== stored.length) {
            listEl.innerHTML = '';
            validRooms.forEach((room) => {
                listEl.appendChild(renderCard(room));
            });
            if (window.refreshLucideIcons) {
                window.refreshLucideIcons(panel);
            }
        }
    };

    render();
});
