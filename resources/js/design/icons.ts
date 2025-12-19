export function refreshLucideIcons(root: Document | Element = document): void {
  if (!window.lucide) return;

  const target = root instanceof Element
    ? root
    : root.documentElement || document.documentElement;

  if (typeof window.lucide.createIcons === 'function') {
    try {
      window.lucide.createIcons({ icons: window.lucide.icons }, target);
      return;
    } catch (e) {
      /* fallback to manual rendering below */
    }
  }

  const doc = target.ownerDocument || document;
  const icons = (window.lucide.icons || {}) as Record<string, { toSvg: () => string }>;
  const nodes = target.querySelectorAll<HTMLElement>('[data-lucide]');

  nodes.forEach((node) => {
    const name = node.getAttribute('data-lucide');
    if (!name) return;
    const iconDef = icons[name];
    if (!iconDef || typeof iconDef.toSvg !== 'function') return;

    const wrapper = doc.createElement('div');
    wrapper.innerHTML = iconDef.toSvg();
    const svg = wrapper.firstElementChild as SVGElement | null;
    if (!svg) return;

    const attrs = node.getAttributeNames();
    attrs.forEach((attr) => {
      if (attr === 'data-lucide') return;
      const value = node.getAttribute(attr);
      if (value !== null) {
        svg.setAttribute(attr, value);
      }
    });
    svg.classList.add(...node.classList);
    node.replaceWith(svg);
  });
}
