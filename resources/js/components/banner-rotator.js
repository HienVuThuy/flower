/* Khung banner luân phiên trên trang chủ. */

const REDUCED_MOTION = '(prefers-reduced-motion: reduce)';

function setup(root) {
    const slides = [...root.querySelectorAll('[data-banner-slide]')];

    if (slides.length < 2) {
        return;
    }

    const dotsBox = root.querySelector('[data-banner-dots]');
    const dots = dotsBox ? [...dotsBox.querySelectorAll('[data-banner-dot]')] : [];
    const interval = Number(root.dataset.interval) || 7000;

    let index = Math.max(0, slides.findIndex((s) => s.classList.contains('is-active')));
    let timer = null;
    let paused = false;

    if (dotsBox) {
        dotsBox.hidden = false;
    }

    const show = (next) => {
        slides[index].classList.remove('is-active');
        dots[index]?.classList.remove('is-active');
        dots[index]?.setAttribute('aria-selected', 'false');

        index = (next + slides.length) % slides.length;

        slides[index].classList.add('is-active');
        dots[index]?.classList.add('is-active');
        dots[index]?.setAttribute('aria-selected', 'true');
    };

    const stop = () => {
        if (timer !== null) {
            clearInterval(timer);
            timer = null;
        }
    };

    const start = () => {
        if (timer === null && !paused && !document.hidden
            && !window.matchMedia(REDUCED_MOTION).matches) {
            timer = setInterval(() => show(index + 1), interval);
        }
    };

    const restart = () => {
        stop();
        start();
    };

    dots.forEach((dot) => {
        dot.addEventListener('click', () => {
            show(Number(dot.dataset.bannerDot));

            restart();
        });
    });

    const pause = () => {
        paused = true;
        stop();
    };

    const resume = () => {
        paused = false;
        start();
    };

    root.addEventListener('mouseenter', pause);
    root.addEventListener('mouseleave', resume);
    root.addEventListener('focusin', pause);
    root.addEventListener('focusout', resume);

    document.addEventListener('visibilitychange', () => {
        document.hidden ? stop() : start();
    });

    window.matchMedia(REDUCED_MOTION).addEventListener('change', (e) => {
        e.matches ? stop() : start();
    });

    start();
}

export function initBannerRotator() {
    document.querySelectorAll('[data-banner-rotator]').forEach(setup);
}
