import { createQrModules } from './design/qr';
import { refreshLucideIcons } from './design/icons';
import { initTheme, setupThemeToggle } from './design/theme';
import {
  setupQueueFilter,
  setupQueueNewHandlers,
  markQueueItemSeen,
  markQueueHasNew,
} from './design/queue';
import {
  loadQueueSoundSetting,
  setupQueueSoundToggle,
  setupSoundPriming,
  playQueueSound,
  initQueueSoundPlayer,
  isQueueSoundEnabled,
} from './design/queue-sound';
import {
  setupFlashMessages,
  showFlashNotification,
  setupNetworkStatusNotification,
} from './design/flash';
import {
  setupCopyButtons,
  setupChatEnterSubmit,
  setupInlineEditors,
  syncLogoutForms,
} from './design/forms';
import { setupMobileMenu, setupUserMenus, setupMobileTabs } from './design/menu';
import { setupRoomDescriptions, setupRoomDeleteModals } from './design/room';
import { setupWhatsNewModal } from './design/whats-new';
import { updateDashboardGreeting, scheduleGreetingRefresh } from './design/greeting';

if (typeof window !== 'undefined') {
  window.createQrModules = createQrModules;
  window.refreshLucideIcons = refreshLucideIcons;
  window.showFlashNotification = showFlashNotification;
  window.setupFlashMessages = setupFlashMessages;
  window.setupNetworkStatusNotification = setupNetworkStatusNotification;
  window.markQueueHasNew = markQueueHasNew;
  window.playQueueSound = playQueueSound;
  window.initQueueSoundPlayer = initQueueSoundPlayer;
  window.isQueueSoundEnabled = isQueueSoundEnabled;
  window.setupQueueNewHandlers = setupQueueNewHandlers;
  window.setupQueueFilter = setupQueueFilter;
  window.markQueueItemSeen = markQueueItemSeen;

  setupNetworkStatusNotification();
}

document.addEventListener('DOMContentLoaded', () => {
  loadQueueSoundSetting();
  initTheme();
  setupThemeToggle();
  setupCopyButtons();
  setupMobileMenu();
  setupUserMenus();
  setupQueueSoundToggle();
  setupSoundPriming(window.queueSoundUrl);
  setupMobileTabs();
  setupChatEnterSubmit();
  setupQueueFilter();
  setupQueueNewHandlers();
  setupFlashMessages();
  syncLogoutForms();
  setupInlineEditors();
  setupRoomDescriptions();
  setupRoomDeleteModals();
  setupWhatsNewModal();
  updateDashboardGreeting();
  scheduleGreetingRefresh();
  refreshLucideIcons();
});

window.rebindQueuePanels = (root: Document | Element = document): void => {
  const doc = root instanceof Document ? root : root.ownerDocument || document;
  const isExternalDoc = !!doc.defaultView && doc.defaultView !== window;

  if (!isExternalDoc) {
    setupFlashMessages(root);
    setupRoomDescriptions(root);
    syncLogoutForms();
  }

  setupQueueFilter(root);
  setupQueueNewHandlers(root);
  refreshLucideIcons(root);
};
