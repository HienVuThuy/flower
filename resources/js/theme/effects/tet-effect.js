import { BaseEffect } from './base-effect.js';

/** Cánh mai rơi — chậm, thưa, không phải confetti. */
export class TetEffect extends BaseEffect {
    constructor() {
        super();
        this.maxParticles = 26;
    }

    spawnParticle(randomY = false) {
        const w = window.innerWidth;
        const h = window.innerHeight;
        const depth = Math.random();

        return {
            x: Math.random() * w,
            y: randomY ? Math.random() * h : -20,
            size: 5 + depth * 5,
            speed: 0.3 + depth * 0.6,
            drift: 0.3 + Math.random() * 0.4,
            angle: Math.random() * Math.PI * 2,
            spin: (Math.random() - 0.5) * 0.02,
            opacity: 0.5 + depth * 0.4,
        };
    }

    updateParticle(p) {
        p.y += p.speed;
        p.x += Math.sin(p.y * 0.01) * p.drift;
        p.angle += p.spin;

        if (p.y > window.innerHeight + 20) {
            p.y = -20;
            p.x = Math.random() * window.innerWidth;
        }
    }

    drawParticle(ctx, p) {
        ctx.save();
        ctx.translate(p.x, p.y);
        ctx.rotate(p.angle);
        ctx.fillStyle = `rgba(232, 178, 61, ${p.opacity})`;
        ctx.beginPath();
        ctx.ellipse(0, 0, p.size, p.size * 0.55, 0, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
    }
}
