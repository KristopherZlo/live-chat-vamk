export type RoomPageConfig = {
    roomSlug: string;
    isOwnerUser: boolean;
    currentUserId: number | null;
    currentParticipantId: number | null;
    currentUserName: string | null;
    currentParticipantName: string | null;
    publicLink: string;
    queueSoundUrl: string | null;
    cacodemonImageUrl: string | null;
    messagesHistoryUrl: string | null;
    messagesHasMoreInitial: boolean;
    messagesOldestId: number | string | null;
    messagesPageSize?: number | null;
    queueItemUrlTemplate?: string | null;
    queueItemsBatchUrl?: string | null;
    queueChunkUrl?: string | null;
    queuePageSize?: number | string | null;
    questionsPanelUrl?: string | null;
    myQuestionsPanelUrl?: string | null;
    banStoreUrl?: string | null;
    banDestroyUrlTemplate?: string | null;
    pollVoteUrlTemplate?: string | null;
    roomIsClosed: boolean;
    reactionUrlTemplate?: string | null;
    deleteUrlTemplate?: string | null;
    popularReactions?: string[] | null;
    isDevUser?: boolean | null;
};

export function getRoomPageConfig(doc: Document = document): RoomPageConfig | null {
    const configEl = doc.getElementById('roomPageConfig');
    const rawConfig = configEl?.getAttribute('data-room-page-config');

    if (!rawConfig) {
        return null;
    }

    try {
        return JSON.parse(rawConfig) as RoomPageConfig;
    } catch (error) {
        console.error('Failed to parse room page config', error);

        return null;
    }
}

export function onRoomPageReady(callback: (config: RoomPageConfig) => void): void {
    document.addEventListener('DOMContentLoaded', () => {
        const roomPageConfig = getRoomPageConfig();
        if (!roomPageConfig) {
            return;
        }

        if (window.__chatPageBound) {
            return;
        }

        window.__chatPageBound = true;
        callback(roomPageConfig);
    });
}
