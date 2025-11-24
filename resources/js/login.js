(() => {
    if (!document.body.classList.contains('login-page')) {
        return;
    }

    const svg = document.querySelector('.login-illustration');
    if (!svg) {
        return;
    }

    const shapes = [];
    let sceneWidth = 400;
    let sceneHeight = 500;
    let links = [];
    let mouseX = -1000;
    let mouseY = -1000;

    function measureScene() {
        const rect = svg.getBoundingClientRect();
        const width = Math.max(rect.width, 1);
        const height = Math.max(rect.height, 1);
        sceneWidth = width || sceneWidth;
        sceneHeight = height || sceneHeight;
        svg.setAttribute('viewBox', `0 0 ${sceneWidth} ${sceneHeight}`);
    }

    class FloatingShape {
        constructor(x, y, size, type, fill) {
            this.x = x;
            this.y = y;
            this.size = size;
            this.type = type;
            this.fill = fill;
            this.vx = (Math.random() - 0.5) * 0.5;
            this.vy = (Math.random() - 0.5) * 0.5;
            this.baseSpeed = 0.3;
            this.maxSpeed = 2;
            this.el = null;
        }

        setElement(el) {
            this.el = el;
        }

        update(mousePosX, mousePosY) {
            const currentSpeed = Math.sqrt(this.vx * this.vx + this.vy * this.vy) || 0.001;
            if (currentSpeed < this.baseSpeed) {
                const scale = this.baseSpeed / currentSpeed;
                this.vx *= scale;
                this.vy *= scale;
            }

            const dxMouse = this.x - mousePosX;
            const dyMouse = this.y - mousePosY;
            const distanceMouse = Math.sqrt(dxMouse * dxMouse + dyMouse * dyMouse) || 1;
            const repelRadius = 100;
            if (distanceMouse < repelRadius) {
                const force = (1 - distanceMouse / repelRadius) * 1.5;
                this.vx += (dxMouse / distanceMouse) * force;
                this.vy += (dyMouse / distanceMouse) * force;
            }

            shapes.forEach((other) => {
                if (other === this) return;

                const dx = this.x - other.x;
                const dy = this.y - other.y;
                const dist = Math.sqrt(dx * dx + dy * dy) || 1;
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
            });

            this.vx *= 0.95;
            this.vy *= 0.95;

            const speed = Math.sqrt(this.vx * this.vx + this.vy * this.vy) || 0.001;
            if (speed > this.maxSpeed) {
                const scale = this.maxSpeed / speed;
                this.vx *= scale;
                this.vy *= scale;
            }

            this.x += this.vx;
            this.y += this.vy;

            const margin = this.size + 4;
            if (this.x < margin) {
                this.x = margin;
                this.vx = Math.abs(this.vx) * 0.8;
            }
            if (this.x > sceneWidth - margin) {
                this.x = sceneWidth - margin;
                this.vx = -Math.abs(this.vx) * 0.8;
            }
            if (this.y < margin) {
                this.y = margin;
                this.vy = Math.abs(this.vy) * 0.8;
            }
            if (this.y > sceneHeight - margin) {
                this.y = sceneHeight - margin;
                this.vy = -Math.abs(this.vy) * 0.8;
            }

            if (!this.el) return;

            if (this.type === 'circle') {
                this.el.setAttribute('cx', this.x);
                this.el.setAttribute('cy', this.y);
            } else if (this.type === 'rect') {
                this.el.setAttribute('x', this.x - this.size);
                this.el.setAttribute('y', this.y - this.size);
            } else {
                const points = [];
                for (let i = 0; i < 6; i++) {
                    const angle = (i * Math.PI) / 3;
                    const px = this.x + this.size * Math.cos(angle);
                    const py = this.y + this.size * Math.sin(angle);
                    points.push(`${px},${py}`);
                }
                this.el.setAttribute('points', points.join(' '));
            }
        }
    }

    function createShape(x, y, size, fill) {
        const types = ['circle', 'rect', 'polygon'];
        const type = types[Math.floor(Math.random() * types.length)];
        const shape = new FloatingShape(x, y, size, type, fill);

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

        el.setAttribute('fill', fill);
        el.setAttribute('opacity', '0.82');
        el.setAttribute('filter', 'url(#glow)');

        shape.setElement(el);
        shapes.push(shape);
        return el;
    }

    function createLinks() {
        const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
        links = [];

        if (shapes.length < 2) return group;

        for (let i = 0; i < shapes.length - 1; i++) {
            for (let j = i + 1; j < shapes.length; j++) {
                const lineElement = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                lineElement.setAttribute('stroke', 'url(#characterGrad)');
                lineElement.setAttribute('opacity', '0');

                links.push({
                    el: lineElement,
                    from: shapes[i],
                    to: shapes[j],
                });

                group.appendChild(lineElement);
            }
        }

        return group;
    }

    function updateLinks() {
        links.forEach((link) => {
            const dx = link.to.x - link.from.x;
            const dy = link.to.y - link.from.y;
            const dist = Math.sqrt(dx * dx + dy * dy);
            const maxDist = Math.min(sceneWidth, sceneHeight) * 0.5;

            if (dist < maxDist) {
                link.el.setAttribute('x1', link.from.x);
                link.el.setAttribute('y1', link.from.y);
                link.el.setAttribute('x2', link.to.x);
                link.el.setAttribute('y2', link.to.y);

                const baseWidth = 2.3;
                const minWidth = 1;
                const strokeWidth = baseWidth - (dist / maxDist) * (baseWidth - minWidth);
                link.el.setAttribute('stroke-width', strokeWidth);

                const opacity = (1 - dist / maxDist) * 0.55;
                link.el.setAttribute('opacity', opacity);
            } else {
                link.el.setAttribute('opacity', '0');
            }
        });
    }

    function buildIllustration() {
        measureScene();
        shapes.length = 0;
        links = [];

        while (svg.lastChild) {
            svg.removeChild(svg.lastChild);
        }

        const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
        defs.innerHTML = `
    <linearGradient id="bgGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#2563EB;stop-opacity:0.12"/>
      <stop offset="100%" style="stop-color:#1D4ED8;stop-opacity:0.06"/>
    </linearGradient>
    <linearGradient id="characterGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#2563EB"/>
      <stop offset="100%" style="stop-color:#1D4ED8"/>
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

        const bg = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        bg.setAttribute('x', '0');
        bg.setAttribute('y', '0');
        bg.setAttribute('width', sceneWidth);
        bg.setAttribute('height', sceneHeight);
        bg.setAttribute('fill', 'url(#bgGrad)');
        svg.appendChild(bg);

        const shapeCount = 8 + Math.floor(Math.random() * 2);
        const centerX = sceneWidth / 2;
        const centerY = sceneHeight / 2;
        const radius = Math.min(sceneWidth, sceneHeight) * 0.3;

        for (let i = 0; i < shapeCount; i++) {
            const angle = (i / shapeCount) * Math.PI * 2 + Math.random() * 0.4;
            const dist = radius * (0.5 + Math.random() * 0.5);
            const x = centerX + Math.cos(angle) * dist;
            const y = centerY + Math.sin(angle) * dist;
            const size = 12 + Math.random() * 16;
            const newShape = createShape(x, y, size, 'url(#characterGrad)');
            svg.appendChild(newShape);
        }

        const linesGroup = createLinks();
        svg.appendChild(linesGroup);

        for (let i = 0; i < 12; i++) {
            const dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            const initX = 20 + Math.random() * Math.max(sceneWidth - 40, 40);
            const initY = 20 + Math.random() * Math.max(sceneHeight - 40, 40);

            dot.setAttribute('cx', initX);
            dot.setAttribute('cy', initY);
            dot.setAttribute('r', 1 + Math.random() * 2);
            dot.setAttribute('fill', 'white');
            dot.setAttribute('opacity', '0.4');

            const animateMotion = document.createElementNS('http://www.w3.org/2000/svg', 'animateMotion');
            const pathRadius = 20 + Math.random() * 30;
            animateMotion.setAttribute('dur', `${15 + Math.random() * 20}s`);
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

            const fadeAnimate = document.createElementNS('http://www.w3.org/2000/svg', 'animate');
            fadeAnimate.setAttribute('attributeName', 'opacity');
            fadeAnimate.setAttribute('values', '0.4;0.1;0.4');
            fadeAnimate.setAttribute('dur', `${8 + Math.random() * 5}s`);
            fadeAnimate.setAttribute('repeatCount', 'indefinite');
            dot.appendChild(fadeAnimate);

            svg.appendChild(dot);
        }

    }

    function tick() {
        shapes.forEach((shape) => shape.update(mouseX, mouseY));
        updateLinks();
        requestAnimationFrame(tick);
    }

    const handleMouseMove = (event) => {
        const rect = svg.getBoundingClientRect();
        if (!rect.width || !rect.height) return;
        mouseX = ((event.clientX - rect.left) * sceneWidth) / rect.width;
        mouseY = ((event.clientY - rect.top) * sceneHeight) / rect.height;
    };

    const handleMouseLeave = () => {
        mouseX = -1000;
        mouseY = -1000;
    };

    svg.addEventListener('mousemove', handleMouseMove);
    svg.addEventListener('mouseleave', handleMouseLeave);

    let resizeTimer = null;
    window.addEventListener('resize', () => {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(buildIllustration, 150);
    });

    buildIllustration();
    requestAnimationFrame(tick);

    const discordButton = document.getElementById('discordLogin');
    if (discordButton) {
        discordButton.addEventListener('click', () => {
            console.log('Discord sign-in placeholder clicked.');
        });
    }
})();
