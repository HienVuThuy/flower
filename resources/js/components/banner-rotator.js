/*
 * Khung banner luân phiên trên trang chủ.
 * ============================================================
 * Khác hero-carousel ở chỗ có CHẤM ĐIỀU HƯỚNG bấm được — khách quay lại
 * xem banner vừa trôi qua mà không phải chờ hết một vòng.
 *
 * Bốn điều bắt buộc:
 *  1. Tôn trọng prefers-reduced-motion — không tự đổi.
 *  2. Dừng khi tab bị ẩn, không chạy timer vô ích ở nền.
 *  3. Dừng khi con trỏ hoặc bàn phím đang ở trong khung — banner tự
 *     nhảy lúc người ta đang định bấm nút là cách nhanh nhất để họ bấm
 *     nhầm sang thứ khác.
 *  4. Không làm gì nếu chỉ có một slide.
 */

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

            // Đặt lại đồng hồ: vừa chọn tay xong mà 1 giây sau nó tự nhảy
            // tiếp thì coi như cú bấm không có tác dụng.
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
