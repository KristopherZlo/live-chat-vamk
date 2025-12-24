export function setupConfettiTrigger(root: Document | Element = document): void {
  const triggers = root.querySelectorAll<HTMLElement>('[data-confetti-trigger]');
  if (!triggers.length) return;

  const colors = ['#ff6b6b', '#ffd93d', '#6bcb77', '#4d96ff', '#ff8fab', '#c77dff'];
  const count = 26;

  const burst = (originX: number, originY: number): void => {
    const container = document.createElement('div');
    container.className = 'confetti-burst';
    container.style.left = `${originX}px`;
    container.style.top = `${originY}px`;
    const pieces: number[] = [];

    for (let i = 0; i < count; i += 1) {
      const piece = document.createElement('span');
      piece.className = 'confetti-piece';
      const dx = (Math.random() * 2 - 1) * 140;
      const dy = -(80 + Math.random() * 140);
      const rotate = Math.floor(Math.random() * 720) - 360;
      const delay = Math.random() * 120;
      const duration = 700 + Math.random() * 400;
      const color = colors[i % colors.length];

      piece.style.setProperty('--confetti-x', `${dx}px`);
      piece.style.setProperty('--confetti-y', `${dy}px`);
      piece.style.setProperty('--confetti-rot', `${rotate}deg`);
      piece.style.setProperty('--confetti-delay', `${delay}ms`);
      piece.style.setProperty('--confetti-duration', `${duration}ms`);
      piece.style.setProperty('--confetti-color', color);
      container.appendChild(piece);
      pieces.push(duration + delay);
    }

    document.body.appendChild(container);
    const total = Math.max(...pieces, 900);
    window.setTimeout(() => container.remove(), total + 200);
  };

  triggers.forEach((trigger) => {
    if (trigger.dataset.confettiBound === '1') return;
    trigger.dataset.confettiBound = '1';
    trigger.addEventListener('click', (event) => {
      const rect = trigger.getBoundingClientRect();
      const mouseEvent = event as MouseEvent;
      const clientX = mouseEvent.clientX || rect.left + rect.width / 2;
      const clientY = mouseEvent.clientY || rect.top + rect.height / 2;
      burst(clientX, clientY);
    });
  });
}
