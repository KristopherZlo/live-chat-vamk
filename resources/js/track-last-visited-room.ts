import type { RoomMeta, StoredRoom } from './types/rooms';

const STORAGE_KEY = 'gr:lastVisitedRooms';
const MAX_ROOMS = 9;

const sanitize = (value: unknown): string => String(value ?? '').trim();

const normalizeRoom = (input: StoredRoom): RoomMeta => ({
    slug: sanitize(input?.slug),
    title: sanitize(input?.title),
    description: sanitize(input?.description),
    owner: sanitize(input?.owner),
});

document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.localStorage === 'undefined') {
        return;
    }

    const roomEl = document.querySelector('[data-last-visited-room]');
    if (!roomEl) {
        return;
    }

    const currentRoom: RoomMeta = normalizeRoom({
        slug: roomEl.dataset.roomSlug,
        title: roomEl.dataset.roomTitle,
        description: roomEl.dataset.roomDescription,
        owner: roomEl.dataset.roomOwner,
    });

    if (!currentRoom.slug) {
        return;
    }

    const loadRooms = (): RoomMeta[] => {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return [];
            }
            const parsed = JSON.parse(raw);
            if (!Array.isArray(parsed)) {
                return [];
            }
            return parsed.map((item) => normalizeRoom(item)).filter((room) => room.slug);
        } catch {
            return [];
        }
    };

    const saveRooms = (rooms: RoomMeta[]): void => {
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
