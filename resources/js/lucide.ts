import { createIcons, icons } from 'lucide';

// Expose lucide globally so existing code that calls window.lucide continues to work.
window.lucide = { createIcons, icons };

export default window.lucide;
