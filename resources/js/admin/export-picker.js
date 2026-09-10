/*
 * Hai nút "Chọn tất cả" / "Bỏ chọn" ở trang xuất dữ liệu.
 * ============================================================
 * PHẦN THÊM, KHÔNG PHẢI PHẦN CHÍNH. Không có JavaScript thì CSS ẩn hai
 * nút này đi và người dùng tích tay từng ô — trang vẫn dùng được đủ.
 *
 * GỌI LẠI ĐƯỢC NHIỀU LẦN: điều hướng quản trị thay ruột trang rồi gọi
 * lại toàn bộ phần khởi tạo (xem admin/nav.js). Dấu `data-pickReady`
 * chặn việc gắn sự kiện lần thứ hai lên cùng một nút — nếu không, một
 * cú bấm sẽ chạy hai lượt.
 */
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
