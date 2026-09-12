/**
 * Đổi ngày ở ô chọn kỳ là xem luôn, không phải bấm thêm nút.
 * ============================================================
 * Nút "Xem" vẫn nằm trong HTML và vẫn chạy khi không có JavaScript; CSS
 * chỉ ẩn nó đi khi trang đã có JS (`html.has-js`). Ẩn một nút mà không
 * thay được việc nó làm là bỏ rơi người không chạy được script.
 *
 * ============================================================
 * ĐÁNH DẤU PHẦN TỬ ĐÃ GẮN.
 *
 * bootUi() chạy lại sau mỗi lần điều hướng trong trang quản trị. Không
 * đánh dấu thì mỗi lần điều hướng lại chồng thêm một listener, và một
 * lần đổi ngày gửi biểu mẫu nhiều lần (QĐ-238).
 */
export function initChonKy() {
    const forms = document.querySelectorAll('[data-chon-ky]:not([data-chon-ky-bound])');

    forms.forEach((form) => {
        form.dataset.chonKyBound = '1';

        form.querySelectorAll('[data-chon-ky-o]').forEach((o) => {
            o.addEventListener('change', () => {
                /*
                 * Chỉ gửi khi ĐỦ CẢ HAI ĐẦU.
                 *
                 * Người dùng chọn ngày bắt đầu trước, rồi mới tới ngày
                 * kết thúc. Gửi ngay sau ô đầu thì trang tải lại giữa
                 * chừng và ô thứ hai chưa kịp chọn — họ phải bắt đầu lại.
                 */
                const daDu = [...form.querySelectorAll('[data-chon-ky-o]')]
                    .every((o) => o.value !== '');

                if (daDu) {
                    form.submit();
                }
            });
        });
    });
}
