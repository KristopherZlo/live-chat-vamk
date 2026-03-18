type RoomRealtimeOptions = {
    roomSlug: string;
    isOwnerUser: boolean;
    isDevUser: boolean;
    hasQuestionsPanel: boolean;
    hasBansPanel: boolean;
    startMyQuestionsPolling: () => void;
    startQuestionsPolling: () => void;
    onMessageSent: (payload: any) => void;
    onReactionUpdated: (payload: any) => void;
    onPollUpdated: (payload: any) => void;
    onMessageDeleted: (payload: any) => void;
    onParticipantBanned: (payload: any) => void;
    onParticipantUnbanned: (payload: any) => void;
    onQuestionCreated: (payload: any) => void;
    onQuestionUpdated: (payload: any) => void;
};

export function initRoomRealtime(options: RoomRealtimeOptions): void {
    const connect = () => {
        options.startMyQuestionsPolling();

        if (!window.Echo) {
            options.startQuestionsPolling();

            return;
        }

        window.Echo.channel(`room.${options.roomSlug}`)
            .listen('MessageSent', (payload) => {
                options.onMessageSent(payload);
            })
            .listen('ReactionUpdated', (payload) => {
                options.onReactionUpdated(payload);
            })
            .listen('PollUpdated', (payload) => {
                options.onPollUpdated(payload);
            })
            .listen('MessageDeleted', (payload) => {
                options.onMessageDeleted(payload);
            })
            .error(() => {
                options.startQuestionsPolling();
            });

        const canUseHostRealtime = Boolean((options.hasQuestionsPanel || options.hasBansPanel) && (options.isOwnerUser || options.isDevUser));
        if (!canUseHostRealtime) {
            if (options.hasQuestionsPanel) {
                options.startQuestionsPolling();
            }

            return;
        }

        window.Echo.private(`room.host.${options.roomSlug}`)
            .listen('ParticipantBanned', (payload) => {
                options.onParticipantBanned(payload);
            })
            .listen('ParticipantUnbanned', (payload) => {
                options.onParticipantUnbanned(payload);
            })
            .listen('QuestionCreated', (payload) => {
                options.onQuestionCreated(payload);
            })
            .listen('QuestionUpdated', (payload) => {
                options.onQuestionUpdated(payload);
            })
            .error(() => {
                options.startQuestionsPolling();
            });
    };

    const echoReady = window.__echoReady;
    if (echoReady && typeof echoReady.then === 'function') {
        echoReady.then(connect).catch(connect);
    } else {
        connect();
    }
}
