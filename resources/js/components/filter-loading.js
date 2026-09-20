/** Bấm lọc thì làm mờ lưới cũ ngay, để khách biết trang đang đổi chứ không tưởng web đơ. */
export function initFilterLoading() {
    const luoi = document.querySelector('[data-luoi-san-pham]');

    if (!luoi) return;

    document.querySelectorAll('[data-form-loc]:not([data-bound])').forEach((form) => {
        form.dataset.bound = '1';

        form.addEventListener('submit', () => luoi.classList.add('dang-tai'));
        form.addEventListener('change', (e) => {
            if (e.target.matches('select, input[type="checkbox"], input[type="radio"]')) {
                luoi.classList.add('dang-tai');
            }
        });
    });

    /* Quay lại bằng nút Back: trang lấy từ bộ nhớ đệm, phải bỏ trạng thái mờ. */
    window.addEventListener('pageshow', () => luoi.classList.remove('dang-tai'));
}
