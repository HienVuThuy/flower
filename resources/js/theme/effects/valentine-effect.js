import { BaseEffect } from './base-effect.js';

/**
 * Cánh hoa hồng rơi — rất thưa và chậm, chỉ gợi không khí,
 * không biến giao diện thành "confetti trái tim".
 */
export class ValentineEffect extends BaseEffect {
    constructor() {
        super();
        this.maxParticles = 16;
    }

    spawnParticle(randomY = false) {
        const w = window.innerWidth;
        const h = window.innerHeight;
        const depth = Math.random();

        return {
            x: Math.random() * w,
            y: randomY ? Math.random() * h : -20,
            size: 6 + depth * 6,
            speed: 0.25 + depth * 0.45,
            drift: 0.2 + Math.random() * 0.3,
            angle: Math.random() * Math.PI * 2,
            spin: (Math.random() - 0.5) * 0.015,
            opacity: 0.4 + depth * 0.35,
        };
    }

    updateParticle(p) {
        p.y += p.speed;
        p.x += Math.sin(p.y * 0.008) * p.drift;
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
        ctx.fillStyle = `rgba(181, 73, 95, ${p.opacity})`;
        ctx.beginPath();
        ctx.moveTo(0, -p.size);
        ctx.quadraticCurveTo(p.size * 0.8, 0, 0, p.size);
        ctx.quadraticCurveTo(-p.size * 0.8, 0, 0, -p.size);
        ctx.fill();
        ctx.restore();
    }
}
