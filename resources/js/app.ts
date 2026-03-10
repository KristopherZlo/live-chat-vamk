import './bootstrap';
import './error-reporting';

const syncAppHeaderHeight = () => {
    const header = document.querySelector<HTMLElement>('.app-header');
    if (!header) return;
    const root = document.documentElement;
    const update = () => {
        const height = header.getBoundingClientRect().height;
        if (height) {
            root.style.setProperty('--app-header-height', `${height}px`);
        }
    };
    update();
    if (typeof ResizeObserver !== 'undefined') {
        const observer = new ResizeObserver(() => update());
        observer.observe(header);
    } else {
        window.addEventListener('resize', () => requestAnimationFrame(update));
    }
};

document.addEventListener('DOMContentLoaded', syncAppHeaderHeight);
