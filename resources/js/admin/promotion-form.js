/** Biểu mẫu khuyến mại: chỉ hiện ô của hình thức đang chọn (giảm giá hoặc tặng quà). */
export function initPromotionForm() {
    document.querySelectorAll('[data-km-hinh-thuc]:not([data-bound])').forEach((khung) => {
        khung.dataset.bound = '1';

        const chon = khung.querySelector('[data-km-type]');
        if (!chon) return;

        const doi = () => {
            const qua = chon.value === 'tang_qua';
            khung.querySelectorAll('[data-km-khi]').forEach((el) => {
                el.hidden = (el.dataset.kmKhi === 'qua') !== qua;
            });
        };

        chon.addEventListener('change', doi);
        doi();
    });
}
