/*
 * Luân phiên ảnh trong khung hero.
 * ============================================================
 * Bộ ảnh do server render sẵn theo theme đang bật (config/theme.php),
 * JS chỉ lo việc đổi ảnh — không biết và không cần biết theme nào.
 *
 * Ba điều bắt buộc:
 *  1. Tôn trọng prefers-reduced-motion: không tự đổi ảnh.
 *  2. Dừng khi tab bị ẩn, để không chạy timer vô ích ở nền.
 *  3. Không làm gì nếu chỉ có một ảnh.
 */

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

    // Người dùng bật/tắt giảm chuyển động ngay khi đang xem trang.
    window.matchMedia(REDUCED_MOTION).addEventListener('change', (e) => {
        e.matches ? stop() : start();
    });

    start();
}

export function initHeroCarousel() {
    document.querySelectorAll('[data-hero-carousel]').forEach(startCarousel);
}
