const STORAGE_KEY = 'gr:lastVisitedRooms';
const MAX_ROOMS = 9;

document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.localStorage === 'undefined') {
        return;
    }

    const panel = document.querySelector('[data-last-visited-panel]');
    const listEl = panel?.querySelector('[data-last-visited-list]');
    if (!panel || !listEl) {
        return;
    }

    const readStoredRooms = () => {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return [];
            }
            const parsed = JSON.parse(raw);
            if (!Array.isArray(parsed)) {
                return [];
            }
            return parsed
                .map((item) => ({
                    slug: String(item?.slug || '').trim(),
                    title: String(item?.title || '').trim(),
                    description: String(item?.description || '').trim(),
                }))
                .filter((room) => room.slug);
        } catch (error) {
            console.error('Unable to read last visited rooms', error);
            return [];
        }
    };

    const persistRooms = (rooms) => {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(rooms));
        } catch (error) {
            console.error('Unable to save last visited rooms', error);
        }
    };

    const clearRooms = () => {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (error) {
            console.error('Unable to clear last visited rooms', error);
        }
    };

    const renderCard = (room) => {
        const card = document.createElement('article');
        card.className = 'room-card panel visited-room-card';

        const meta = document.createElement('div');
        meta.className = 'room-card-meta';
        const code = document.createElement('span');
        code.className = 'room-code';
        code.textContent = `Code: ${room.slug}`;
        meta.appendChild(code);

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
    const buildUrl = (path) => {
        const cleanPath = path.replace(/^\/+/, '');
        return `${normalizeBase}/${cleanPath}`;
    };

    const checkRoomExists = async (slug) => {
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

    const render = async () => {
        const stored = readStoredRooms().slice(0, MAX_ROOMS);
        if (!stored.length) {
            panel.hidden = true;
            return;
        }

        const validRooms = [];
        for (const room of stored) {
            if (!room.slug) {
                continue;
            }
            const exists = await checkRoomExists(room.slug);
            if (exists) {
                validRooms.push(room);
            }
        }

        if (!validRooms.length) {
            clearRooms();
            panel.hidden = true;
            return;
        }

        persistRooms(validRooms);
        listEl.innerHTML = '';
        validRooms.forEach((room) => {
            listEl.appendChild(renderCard(room));
        });
        panel.hidden = false;
        if (window.refreshLucideIcons) {
            window.refreshLucideIcons(panel);
        }
    };

    render();
});
