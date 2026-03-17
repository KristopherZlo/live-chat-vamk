type ChatTabsOptions = {
    buttons: NodeListOf<HTMLElement> | HTMLElement[];
    panes: NodeListOf<HTMLElement> | HTMLElement[];
    initialTab?: string;
    onChange?: (tabName: string) => void;
};

type ChatTabsController = {
    activeTab: string;
    setActiveTab: (tabName?: string) => void;
};

export function setupChatTabs(options: ChatTabsOptions): ChatTabsController {
    const buttons = Array.from(options.buttons);
    const panes = Array.from(options.panes);
    const onChange = options.onChange;

    const setActiveTab = (tabName = 'chat') => {
        buttons.forEach((button) => {
            const isActive = button.dataset.chatTab === tabName;
            button.classList.toggle('active', isActive);
        });

        panes.forEach((pane) => {
            const isMatch = pane.dataset.chatPanel === tabName;
            pane.hidden = !isMatch;
        });

        onChange?.(tabName);
    };

    if (!buttons.length || !panes.length) {
        return {
            activeTab: options.initialTab || 'chat',
            setActiveTab,
        };
    }

    const current = buttons.find((button) => button.classList.contains('active'));
    const activeTab = current?.dataset.chatTab || options.initialTab || 'chat';

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            setActiveTab(button.dataset.chatTab || 'chat');
        });
    });

    setActiveTab(activeTab);

    return {
        activeTab,
        setActiveTab,
    };
}
