/** Thanh mua nhanh dưới đáy (điện thoại): chỉ hiện khi khối mua chính đã cuộn khuất lên trên. */
export function initQuickBuy() {
    const thanh = document.querySelector('[data-mua-nhanh]:not([data-bound])');
    const chinh = document.querySelector('[data-mua-chinh]');

    if (!thanh || !chinh) return;

    thanh.dataset.bound = '1';
    thanh.hidden = false;

    let cho = false;

    const xet = () => {
        cho = false;
        thanh.classList.toggle('is-hien', chinh.getBoundingClientRect().bottom < 0);
    };

    const hen = () => {
        if (cho) return;
        cho = true;
        requestAnimationFrame(xet);
    };

    window.addEventListener('scroll', hen, { passive: true });
    window.addEventListener('resize', hen, { passive: true });
    xet();
}
