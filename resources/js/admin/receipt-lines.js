/*
 * Nút "Thêm dòng" ở phiếu nhập kho.
 * ============================================================
 * PHẦN THÊM, KHÔNG PHẢI PHẦN CHÍNH. Biểu mẫu đã dựng sẵn 8 dòng trống ở
 * máy chủ, nên không có JavaScript thì vẫn lập được cả phiếu — chỉ là
 * tối đa 8 mặt hàng một lần.
 *
 * GỌI LẠI ĐƯỢC NHIỀU LẦN: điều hướng quản trị thay ruột trang rồi gọi
 * lại toàn bộ phần khởi tạo (xem admin/nav.js).
 */
export function initReceiptLines() {
    document.querySelectorAll('[data-receipt-add]').forEach((nut) => {
        if (nut.dataset.lineReady) {
            return;
        }

        nut.dataset.lineReady = '1';

        nut.addEventListener('click', () => {
            const bang = document.querySelector('[data-receipt-lines] tbody');

            if (!bang) {
                return;
            }

            const dong = bang.querySelectorAll('[data-receipt-line]');
            const cuoi = dong[dong.length - 1];

            if (!cuoi) {
                return;
            }

            const moi = cuoi.cloneNode(true);

            /*
             * ĐÁNH SỐ TIẾP, KHÔNG DÙNG LẠI SỐ CŨ.
             *
             * `items[7][quantity]` mà nhân bản y nguyên thì hai dòng cùng
             * một chỉ số, và PHP chỉ nhận dòng cuối — dòng người dùng vừa
             * gõ ở trên biến mất không dấu vết.
             */
            const soMoi = dong.length;

            moi.querySelectorAll('[name]').forEach((o) => {
                o.name = o.name.replace(/items\[\d+\]/, `items[${soMoi}]`);

                if (o.tagName === 'SELECT') {
                    o.selectedIndex = 0;
                } else {
                    o.value = '';
                }
            });

            bang.appendChild(moi);
        });
    });
}
