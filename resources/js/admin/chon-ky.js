/** Đổi ngày ở ô chọn kỳ là xem luôn, không phải bấm thêm nút. */
export function initChonKy() {
    const forms = document.querySelectorAll('[data-chon-ky]:not([data-chon-ky-bound])');

    forms.forEach((form) => {
        form.dataset.chonKyBound = '1';

        form.querySelectorAll('[data-chon-ky-o]').forEach((o) => {
            o.addEventListener('change', () => {
                const daDu = [...form.querySelectorAll('[data-chon-ky-o]')]
                    .every((o) => o.value !== '');

                if (daDu) {
                    form.submit();
                }
            });
        });
    });
}
