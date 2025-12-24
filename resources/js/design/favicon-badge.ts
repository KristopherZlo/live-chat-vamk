type FaviconEntry = {
  element: HTMLLinkElement;
  href: string;
};

const BADGE_COLOR = '#f97316';
const BADGE_BORDER = '#ffffff';
const MIN_ICON_SIZE = 64;

let originalIcons: FaviconEntry[] | null = null;
let activeCount = 0;
let renderToken = 0;
let cachedBadgeDataUrl: string | null = null;

function collectFaviconLinks(): HTMLLinkElement[] {
  if (typeof document === 'undefined') return [];
  const icons = Array.from(document.querySelectorAll<HTMLLinkElement>('link[rel~="icon"]'));
  const shortcuts = Array.from(document.querySelectorAll<HTMLLinkElement>('link[rel="shortcut icon"]'));
  return [...new Set([...icons, ...shortcuts])];
}

function ensureOriginalIcons(): FaviconEntry[] {
  if (originalIcons) return originalIcons;
  const links = collectFaviconLinks();
  originalIcons = links.map((element) => ({ element, href: element.href }));
  return originalIcons;
}

function applyFaviconHref(href: string): void {
  const links = ensureOriginalIcons();
  links.forEach(({ element }) => {
    element.href = href;
  });
}

function restoreFavicon(): void {
  const links = ensureOriginalIcons();
  links.forEach(({ element, href }) => {
    element.href = href;
  });
}

function buildBadgeDataUrl(baseHref: string): Promise<string | null> {
  return new Promise((resolve) => {
    if (!baseHref) {
      resolve(null);
      return;
    }
    const image = new Image();
    image.decoding = 'async';
    image.crossOrigin = 'anonymous';
    image.onload = () => {
      const size = Math.max(image.naturalWidth || 0, image.naturalHeight || 0, MIN_ICON_SIZE);
      const canvas = document.createElement('canvas');
      canvas.width = size;
      canvas.height = size;
      const ctx = canvas.getContext('2d');
      if (!ctx) {
        resolve(null);
        return;
      }
      ctx.clearRect(0, 0, size, size);
      ctx.drawImage(image, 0, 0, size, size);
      const radius = Math.max(6, Math.round(size * 0.18));
      const offset = Math.max(2, Math.round(size * 0.05));
      const centerX = size - radius - offset;
      const centerY = size - radius - offset;
      ctx.beginPath();
      ctx.arc(centerX, centerY, radius, 0, Math.PI * 2);
      ctx.fillStyle = BADGE_COLOR;
      ctx.fill();
      ctx.lineWidth = Math.max(2, Math.round(size * 0.07));
      ctx.strokeStyle = BADGE_BORDER;
      ctx.stroke();
      resolve(canvas.toDataURL('image/png'));
    };
    image.onerror = () => resolve(null);
    image.src = baseHref;
  });
}

export function updateFaviconBadge(count: number): void {
  if (typeof document === 'undefined') return;
  const normalized = Number.isFinite(count) ? Math.max(0, Math.floor(count)) : 0;
  const links = ensureOriginalIcons();
  if (!links.length) return;

  if (normalized <= 0) {
    activeCount = 0;
    restoreFavicon();
    return;
  }

  if (activeCount === normalized && cachedBadgeDataUrl) {
    applyFaviconHref(cachedBadgeDataUrl);
    return;
  }

  activeCount = normalized;
  const token = ++renderToken;
  const baseHref = links[0]?.href || '';

  buildBadgeDataUrl(baseHref).then((dataUrl) => {
    if (!dataUrl || token !== renderToken || activeCount <= 0) return;
    cachedBadgeDataUrl = dataUrl;
    applyFaviconHref(dataUrl);
  });
}
