const STORAGE_KEY = 'gr:lastVisitedRooms';
const MAX_ROOMS = 9;

document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.localStorage === 'undefined') {
        return;
    }

    const roomEl = document.querySelector('[data-last-visited-room]');
    if (!roomEl) {
        return;
    }

    const sanitize = (value) => String(value ?? '').trim();
    const currentRoom = {
        slug: sanitize(roomEl.dataset.roomSlug),
        title: sanitize(roomEl.dataset.roomTitle),
        description: sanitize(roomEl.dataset.roomDescription),
    };

    if (!currentRoom.slug) {
        return;
    }

    const loadRooms = () => {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return [];
            }
            const parsed = JSON.parse(raw);
            if (!Array.isArray(parsed)) {
                return [];
            }
            return parsed.map((item) => ({
                slug: sanitize(item?.slug),
                title: sanitize(item?.title),
                description: sanitize(item?.description),
            }));
        } catch {
            return [];
        }
    };

    const saveRooms = (rooms) => {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(rooms));
        } catch (error) {
            console.error('Unable to persist last visited rooms', error);
        }
    };

    const existing = loadRooms().filter((room) => room.slug && room.slug !== currentRoom.slug);
    const updated = [currentRoom, ...existing].slice(0, MAX_ROOMS);
    saveRooms(updated);
});
