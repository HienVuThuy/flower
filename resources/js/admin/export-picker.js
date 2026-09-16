/* Hai nút "Chọn tất cả" / "Bỏ chọn" ở trang xuất dữ liệu. */
export function initExportPicker() {
    document.querySelectorAll('[data-pick-all], [data-pick-none]').forEach((nut) => {
        if (nut.dataset.pickReady) {
            return;
        }

        nut.dataset.pickReady = '1';

        nut.addEventListener('click', () => {
            const form = nut.closest('form');

            if (!form) {
                return;
            }

            const bat = nut.hasAttribute('data-pick-all');

            form.querySelectorAll('[data-pick]').forEach((o) => {
                o.checked = bat;
            });
        });
    });
}
