/**
 * Hợp đồng lifecycle cho mọi seasonal effect (Layer 3).
 *
 * init()    — tạo canvas, gắn listener. Gọi một lần.
 * enable()  — bắt đầu vẽ (bỏ qua nếu prefers-reduced-motion).
 * disable() — dừng vòng lặp vẽ, xoá canvas, KHÔNG gỡ listener.
 * destroy() — disable() + gỡ toàn bộ listener + canvas khỏi DOM.
 *
 * ThemeManager đảm bảo effect cũ luôn destroy() xong trước khi
 * effect mới init(), nên không có 2 effect chạy song song và
 * không rò rỉ animation frame khi đổi theme liên tục.
 */
export class BaseEffect {
    constructor() {
        this.canvas = null;
        this.ctx = null;
        this.particles = [];
        this.rafId = null;
        this.running = false;
        this.maxParticles = 60;

        this._onResize = this._onResize.bind(this);
        this._onVisibility = this._onVisibility.bind(this);
        this._tick = this._tick.bind(this);
    }

    prefersReducedMotion() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    isMobile() {
        return window.innerWidth < 768;
    }

    init() {
        this.canvas = document.createElement('canvas');
        this.canvas.className = 'seasonal-canvas';
        this.canvas.setAttribute('aria-hidden', 'true');
        document.body.appendChild(this.canvas);
        this.ctx = this.canvas.getContext('2d');
        this._resize();

        window.addEventListener('resize', this._onResize);
        document.addEventListener('visibilitychange', this._onVisibility);
    }

    enable() {
        if (!this.canvas) this.init();
        if (this.prefersReducedMotion()) return;

        this.particles = this._spawnAll();
        this.running = true;
        this._loop();
    }

    disable() {
        this.running = false;
        if (this.rafId) cancelAnimationFrame(this.rafId);
        this.rafId = null;
        this.particles = [];
        if (this.ctx && this.canvas) {
            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        }
    }

    destroy() {
        this.disable();
        window.removeEventListener('resize', this._onResize);
        document.removeEventListener('visibilitychange', this._onVisibility);
        if (this.canvas && this.canvas.parentNode) {
            this.canvas.parentNode.removeChild(this.canvas);
        }
        this.canvas = null;
        this.ctx = null;
    }

    _resize() {
        if (!this.canvas) return;
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        this.canvas.width = window.innerWidth * dpr;
        this.canvas.height = window.innerHeight * dpr;
        this.canvas.style.width = window.innerWidth + 'px';
        this.canvas.style.height = window.innerHeight + 'px';
        this.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }

    _onResize() {
        this._resize();
    }

    _onVisibility() {
        if (document.hidden) {
            if (this.rafId) cancelAnimationFrame(this.rafId);
            this.rafId = null;
        } else if (this.running) {
            this._loop();
        }
    }

    _spawnAll() {
        const count = this.isMobile()
            ? Math.round(this.maxParticles * 0.5)
            : this.maxParticles;

        return Array.from({ length: count }, () => this.spawnParticle(true));
    }

    _loop() {
        if (!this.running || document.hidden) return;

        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

        this.particles.forEach((particle) => {
            this.updateParticle(particle);
            this.drawParticle(this.ctx, particle);
        });

        this.rafId = requestAnimationFrame(this._tick);
    }

    _tick() {
        this._loop();
    }

    // Ghi đè ở effect con.
    spawnParticle() {
        return {};
    }

    updateParticle() {}

    drawParticle() {}
}
