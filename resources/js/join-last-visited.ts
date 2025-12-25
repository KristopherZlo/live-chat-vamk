import type { RoomMeta, StoredRoom } from './types/rooms';

const STORAGE_KEY = 'gr:lastVisitedRooms';
const MAX_ROOMS = 9;

const sanitize = (value: unknown): string => String(value ?? '').trim();
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
        const card = document.createElement('article');
        card.className = 'room-card panel visited-room-card';

        const meta = document.createElement('div');
        meta.className = 'room-card-meta';
        const code = document.createElement('span');
        code.className = 'room-code';
        code.textContent = `Code: ${room.slug}`;
        const owner = document.createElement('span');
        owner.className = 'room-owner';
        owner.textContent = `Owner: ${room.owner || 'Unknown'}`;
        const separator = document.createElement('span');
        separator.className = 'dot-separator';
        separator.textContent = '•';
        meta.appendChild(code);
        meta.appendChild(separator);
        meta.appendChild(owner);

        const titleRow = document.createElement('div');
        titleRow.className = 'room-card-title';
        const title = document.createElement('div');
        title.className = 'inline-edit-display room-card-title-text visited-room-title';
        title.textContent = room.title || 'Untitled room';
        titleRow.appendChild(title);

        const description = document.createElement('p');
        description.className = 'inline-edit-display room-card-desc visited-room-description';
        description.textContent = room.description || 'No description yet.';

        const actions = document.createElement('div');
        actions.className = 'room-card-actions visited-room-actions';
        const enterButton = document.createElement('a');
        enterButton.className = 'btn btn-sm btn-primary';
        enterButton.href = buildUrl(`/r/${encodeURIComponent(room.slug)}`);
        enterButton.setAttribute('aria-label', `Enter ${room.title || 'room'}`);
        enterButton.innerHTML = '<i data-lucide="log-in"></i><span>Enter</span>';
        actions.appendChild(enterButton);

        card.appendChild(meta);
        card.appendChild(titleRow);
        card.appendChild(description);
        card.appendChild(actions);

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
