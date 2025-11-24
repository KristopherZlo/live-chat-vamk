// Храним все фигуры и линии
let shapes = [];
let lines = [];

// Глобальные координаты мыши (изначально далеко от холста)
let mouseX = -1000;
let mouseY = -1000;

// Описываем каждую фигуру (круг, прямоугольник или шестиугольник)
class Shape {
  constructor(x, y, size, type, color) {
    this.x = x;
    this.y = y;
    this.size = size;
    this.type = type;
    this.color = color;
    this.vx = (Math.random() - 0.5) * 0.5;
    this.vy = (Math.random() - 0.5) * 0.5;
    this.maxSpeed = 2;
    this.baseSpeed = 0.3;
    this.element = null;
  }

  update(mX, mY) {
    // Не даём скорости упасть ниже базовой
    const currentSpeed = Math.sqrt(this.vx * this.vx + this.vy * this.vy);
    if (currentSpeed < this.baseSpeed) {
      const scale = this.baseSpeed / currentSpeed;
      this.vx *= scale;
      this.vy *= scale;
    }

    // Отталкиваемся от мыши
    const dxMouse = this.x - mX;
    const dyMouse = this.y - mY;
    const distanceMouse = Math.sqrt(dxMouse * dxMouse + dyMouse * dyMouse);
    const repelRadius = 100;
    if (distanceMouse < repelRadius) {
      const force = (1 - distanceMouse / repelRadius) * 1.5;
      this.vx += (dxMouse / distanceMouse) * force;
      this.vy += (dyMouse / distanceMouse) * force;
    }

    // Отталкивание между фигурами
    shapes.forEach((other) => {
      if (other !== this) {
        const dx = this.x - other.x;
        const dy = this.y - other.y;
        const dist = Math.sqrt(dx * dx + dy * dy);
        const minDist = this.size + other.size + 10;

        if (dist < minDist) {
          const overlap = (1 - dist / minDist) * 0.5;
          const angleX = dx / dist;
          const angleY = dy / dist;
          this.vx += angleX * overlap;
          this.vy += angleY * overlap;
          other.vx -= angleX * overlap;
          other.vy -= angleY * overlap;
        }
      }
    });

    // Трение
    this.vx *= 0.95;
    this.vy *= 0.95;

    // Ограничение скорости
    const speed = Math.sqrt(this.vx * this.vx + this.vy * this.vy);
    if (speed > this.maxSpeed) {
      const scale = this.maxSpeed / speed;
      this.vx *= scale;
      this.vy *= scale;
    }

    // Обновляем координаты
    this.x += this.vx;
    this.y += this.vy;

    // Отражение от краёв
    const margin = this.size;
    if (this.x < margin) {
      this.x = margin;
      this.vx = Math.abs(this.vx) * 0.8;
      this.vy += (Math.random() - 0.5) * 0.5;
    }
    if (this.x > 400 - margin) {
      this.x = 400 - margin;
      this.vx = -Math.abs(this.vx) * 0.8;
      this.vy += (Math.random() - 0.5) * 0.5;
    }
    if (this.y < margin) {
      this.y = margin;
      this.vy = Math.abs(this.vy) * 0.8;
      this.vx += (Math.random() - 0.5) * 0.5;
    }
    if (this.y > 500 - margin) {
      this.y = 500 - margin;
      this.vy = -Math.abs(this.vy) * 0.8;
      this.vx += (Math.random() - 0.5) * 0.5;
    }

    // Синхронизируем с элементом SVG
    if (this.type === 'circle') {
      this.element.setAttribute('cx', this.x);
      this.element.setAttribute('cy', this.y);
    } else if (this.type === 'rect') {
      this.element.setAttribute('x', this.x - this.size);
      this.element.setAttribute('y', this.y - this.size);
    } else if (this.type === 'polygon') {
      const points = [];
      for (let i = 0; i < 6; i++) {
        const angle = (i * Math.PI) / 3;
        const px = this.x + this.size * Math.cos(angle);
        const py = this.y + this.size * Math.sin(angle);
        points.push(`${px},${py}`);
      }
      this.element.setAttribute('points', points.join(' '));
    }
  }
}

// Создаём фигуру (circle, rect, polygon)
function createShape(svg, x, y, size, color) {
  const types = ['circle', 'rect', 'polygon'];
  const type = types[Math.floor(Math.random() * types.length)];
  const shape = new Shape(x, y, size, type, color);

  const el = document.createElementNS('http://www.w3.org/2000/svg', type === 'polygon' ? 'polygon' : type);

  if (type === 'circle') {
    el.setAttribute('cx', x);
    el.setAttribute('cy', y);
    el.setAttribute('r', size);
  } else if (type === 'rect') {
    el.setAttribute('x', x - size);
    el.setAttribute('y', y - size);
    el.setAttribute('width', size * 2);
    el.setAttribute('height', size * 2);
    el.setAttribute('rx', size * 0.3);
    el.setAttribute('ry', size * 0.3);
  } else {
    const points = [];
    for (let i = 0; i < 6; i++) {
      const angle = (i * Math.PI) / 3;
      const px = x + size * Math.cos(angle);
      const py = y + size * Math.sin(angle);
      points.push(`${px},${py}`);
    }
    el.setAttribute('points', points.join(' '));
  }

  el.setAttribute('fill', color);
  el.setAttribute('opacity', '0.8');
  el.setAttribute('filter', 'url(#glow)');

  shape.element = el;
  shapes.push(shape);
  return el;
}

// Создаём набор линий между всеми фигурами
function createLines(svg) {
  const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
  lines = [];

  if (shapes.length < 2) return group;

  for (let i = 0; i < shapes.length - 1; i++) {
    for (let j = i + 1; j < shapes.length; j++) {
      const lineElement = document.createElementNS('http://www.w3.org/2000/svg', 'line');
      lineElement.setAttribute('stroke', 'url(#characterGrad)');
      lineElement.setAttribute('opacity', '0');

      lines.push({
        element: lineElement,
        fromShape: shapes[i],
        toShape: shapes[j]
      });

      group.appendChild(lineElement);
    }
  }

  return group;
}

// Обновляем положение линий
function updateLines() {
  lines.forEach((ln) => {
    const dx = ln.toShape.x - ln.fromShape.x;
    const dy = ln.toShape.y - ln.fromShape.y;
    const dist = Math.sqrt(dx * dx + dy * dy);
    const maxDist = 220;

    if (dist < maxDist) {
      ln.element.setAttribute('x1', ln.fromShape.x);
      ln.element.setAttribute('y1', ln.fromShape.y);
      ln.element.setAttribute('x2', ln.toShape.x);
      ln.element.setAttribute('y2', ln.toShape.y);

      const baseWidth = 2.5;
      const minWidth = 1;
      const strokeWidth = baseWidth - (dist / maxDist) * (baseWidth - minWidth);
      ln.element.setAttribute('stroke-width', strokeWidth);

      const opacity = (1 - dist / maxDist) * 0.5;
      ln.element.setAttribute('opacity', opacity);
    } else {
      ln.element.setAttribute('opacity', '0');
    }
  });
}

// Обновляем все фигуры с учётом мыши, потом линии
function updateAllShapes(x, y) {
  shapes.forEach((shape) => shape.update(x, y));
  updateLines();
}

// Создаём иллюстрацию внутри SVG
function buildIllustration() {
  const svg = document.querySelector('.illustration');
  shapes = [];
  lines = [];

  while (svg.lastChild) {
    svg.removeChild(svg.lastChild);
  }

  // defs с градиентами и фильтром
  const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
  defs.innerHTML = `
    <linearGradient id="bgGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#9333EA;stop-opacity:0.1"/>
      <stop offset="100%" style="stop-color:#7C3AED;stop-opacity:0.05"/>
    </linearGradient>
    <linearGradient id="characterGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#9333EA"/>
      <stop offset="100%" style="stop-color:#7C3AED"/>
    </linearGradient>
    <filter id="glow">
      <feGaussianBlur stdDeviation="2" result="coloredBlur"/>
      <feMerge>
        <feMergeNode in="coloredBlur"/>
        <feMergeNode in="SourceGraphic"/>
      </feMerge>
    </filter>
  `;
  svg.appendChild(defs);

  // Фон
  const bg = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
  bg.setAttribute('x', '0');
  bg.setAttribute('y', '0');
  bg.setAttribute('width', '400');
  bg.setAttribute('height', '500');
  bg.setAttribute('fill', 'url(#bgGrad)');
  svg.appendChild(bg);

  // Генерируем несколько фигур
  const shapeCount = 7 + Math.floor(Math.random() * 3);
  const centerX = 200;
  const centerY = 250;
  const radius = 120;

  for (let i = 0; i < shapeCount; i++) {
    const angle = (i / shapeCount) * Math.PI * 2 + Math.random() * 0.5;
    const dist = radius * (0.5 + Math.random() * 0.5);
    const x = centerX + Math.cos(angle) * dist;
    const y = centerY + Math.sin(angle) * dist;
    const size = 12 + Math.random() * 16;
    const newShape = createShape(svg, x, y, size, 'url(#characterGrad)');
    svg.appendChild(newShape);
  }

  // Линии между фигурами
  const linesGroup = createLines(svg);
  svg.insertBefore(linesGroup, svg.firstChild);

  // Партиклы (точки)
  for (let i = 0; i < 12; i++) {
    const dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
    const initX = 50 + Math.random() * 300;
    const initY = 50 + Math.random() * 400;

    dot.setAttribute('cx', initX);
    dot.setAttribute('cy', initY);
    dot.setAttribute('r', 1 + Math.random() * 2);
    dot.setAttribute('fill', 'white');
    dot.setAttribute('opacity', '0.4');

    // Анимация движения точки
    const animateMotion = document.createElementNS('http://www.w3.org/2000/svg', 'animateMotion');
    const pathRadius = 20 + Math.random() * 30;
    animateMotion.setAttribute('dur', (15 + Math.random() * 20) + 's');
    animateMotion.setAttribute('repeatCount', 'indefinite');
    const path = `
      M 0 0
      q ${-pathRadius / 2} ${-pathRadius} 0 ${-pathRadius}
      q ${pathRadius / 2} 0 ${pathRadius} 0
      q ${pathRadius / 2} ${pathRadius} 0 ${pathRadius}
      q ${-pathRadius / 2} 0 ${-pathRadius} 0
    `;
    animateMotion.setAttribute('path', path);
    dot.appendChild(animateMotion);

    // Анимация прозрачности
    const fadeAnimate = document.createElementNS('http://www.w3.org/2000/svg', 'animate');
    fadeAnimate.setAttribute('attributeName', 'opacity');
    fadeAnimate.setAttribute('values', '0.4;0.1;0.4');
    fadeAnimate.setAttribute('dur', (8 + Math.random() * 5) + 's');
    fadeAnimate.setAttribute('repeatCount', 'indefinite');
    dot.appendChild(fadeAnimate);

    svg.appendChild(dot);
  }

  // Обновляем только координаты мыши в этих слушателях
  svg.addEventListener('mousemove', (e) => {
    const rect = svg.getBoundingClientRect();
    mouseX = (e.clientX - rect.left) * 400 / rect.width;
    mouseY = (e.clientY - rect.top) * 500 / rect.height;
  });

  svg.addEventListener('mouseleave', () => {
    mouseX = -1000;
    mouseY = -1000;
  });
}

// Запуск анимационного цикла
function animate() {
  updateAllShapes(mouseX, mouseY);
  requestAnimationFrame(animate);
}

// Генерация иллюстрации и запуск анимации
buildIllustration();
animate();

// Пример обработчиков для кнопки и чекбокса
document.querySelector('.riot-button').addEventListener('click', () => {
  console.log('Signing in with Riot ID...');
});

document.querySelector('#remember').addEventListener('change', (e) => {
  console.log('Remember me:', e.target.checked);
});
