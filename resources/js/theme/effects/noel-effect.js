import { BaseEffect } from './base-effect.js';

/**
 * Tuyết rơi — nhẹ, có depth (kích thước/tốc độ khác nhau), giới
 * hạn số lượng, vẽ bằng canvas (không tạo DOM node cho từng bông).
 */
export class NoelEffect extends BaseEffect {
    constructor() {
        super();
        this.maxParticles = 70;
    }

    spawnParticle(randomY = false) {
        const w = window.innerWidth;
        const h = window.innerHeight;
        const depth = Math.random();

        return {
            x: Math.random() * w,
            y: randomY ? Math.random() * h : -10,
            radius: 1 + depth * 2.4,
            speed: 0.4 + depth * 1.1,
            drift: (Math.random() - 0.5) * 0.6,
            sway: Math.random() * Math.PI * 2,
            opacity: 0.35 + depth * 0.5,
        };
    }

    updateParticle(p) {
        p.y += p.speed;
        p.sway += 0.01;
        p.x += p.drift + Math.sin(p.sway) * 0.3;

        if (p.y > window.innerHeight + 10) {
            p.y = -10;
            p.x = Math.random() * window.innerWidth;
        }
    }

    drawParticle(ctx, p) {
        ctx.beginPath();
        ctx.fillStyle = `rgba(255, 255, 255, ${p.opacity})`;
        ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
        ctx.fill();
    }
}
