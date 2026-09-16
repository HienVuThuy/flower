/* Luân phiên ảnh trong khung hero. */

const REDUCED_MOTION = '(prefers-reduced-motion: reduce)';

function startCarousel(root) {
    const slides = [...root.querySelectorAll('.hero-carousel__slide')];

    if (slides.length < 2) {
        return;
    }

    const interval = Number(root.dataset.interval) || 6000;
    let index = slides.findIndex((s) => s.classList.contains('is-active'));
    let timer = null;

    if (index < 0) {
        index = 0;
        slides[0].classList.add('is-active');
    }

    const step = () => {
        slides[index].classList.remove('is-active');
        index = (index + 1) % slides.length;
        slides[index].classList.add('is-active');
    };

    const stop = () => {
        if (timer !== null) {
            clearInterval(timer);
            timer = null;
        }
    };

    const start = () => {
        if (timer === null && !window.matchMedia(REDUCED_MOTION).matches) {
            timer = setInterval(step, interval);
        }
    };

    document.addEventListener('visibilitychange', () => {
        document.hidden ? stop() : start();
    });

    window.matchMedia(REDUCED_MOTION).addEventListener('change', (e) => {
        e.matches ? stop() : start();
    });

    start();
}

export function initHeroCarousel() {
    document.querySelectorAll('[data-hero-carousel]').forEach(startCarousel);
}
