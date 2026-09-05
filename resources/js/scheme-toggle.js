import { DisplaySchemeStore } from './display-scheme';

/**
 * NÚT ĐỔI NỀN SÁNG / TỐI
 * ============================================================
 * TĂNG CƯỜNG, KHÔNG PHẢI ĐIỀU KIỆN. Không có tệp này thì nút vẫn là một
 * biểu mẫu POST bình thường: bấm → trang tải lại với nền mới. Ở đây chỉ
 * bỏ đi cú tải lại đó.
 *
 * VÌ SAO PHẢI CÓ JAVASCRIPT MỚI BIẾT NÊN CHUYỂN SANG ĐÂU:
 *
 * Khi người dùng để "theo hệ thống", máy chủ chỉ biết là "auto" — nó
 * KHÔNG biết máy của họ đang để sáng hay tối, vì thông tin đó chỉ có ở
 * trình duyệt. Cùng một trang HTML, người để máy sáng đang xem nền
 * sáng, người để máy tối đang xem nền tối.
 *
 * Nên giá trị gửi đi phải tính từ chế độ ĐANG THẤY, không phải từ cookie.
 */
export function initSchemeToggle() {
    const forms = document.querySelectorAll('[data-scheme-toggle]');

    if (forms.length === 0) return;

    forms.forEach((form) => {
        const input = form.querySelector('[data-scheme-value]');

        /*
         * Cập nhật giá trị NGAY khi tải trang, không đợi tới lúc bấm.
         *
         * Giá trị do máy chủ dựng chỉ đúng khi người dùng đã tự chọn
         * sáng hoặc tối. Với "auto" nó có thể ngược hẳn — và nếu chỉ sửa
         * lúc bấm thì đường không-JavaScript (biểu mẫu gửi thật) sẽ mang
         * theo giá trị sai.
         */
        const dongBo = () => {
            if (input) input.value = DisplaySchemeStore.doiSang();
        };

        dongBo();

        form.addEventListener('submit', (e) => {
            e.preventDefault();

            const moi = DisplaySchemeStore.doiSang();

            // Đổi ngay trên màn hình, TRƯỚC khi gửi: người dùng thấy kết
            // quả tức thì, còn việc ghi cookie chạy ngầm phía sau.
            DisplaySchemeStore.apDung(moi);

            if (input) input.value = moi;

            /*
             * Gửi ngầm để máy chủ ghi cookie.
             *
             * KHÔNG tự đặt document.cookie ở đây: đường không-JavaScript
             * đã ghi cookie qua controller, và ghi ở hai nơi là hai bộ
             * luật về tên cookie, hạn dùng và cờ bảo mật phải giữ đồng
             * bộ — bộ thứ hai sẽ là bộ bị quên.
             */
            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(new FormData(form)),
                credentials: 'same-origin',
            }).catch(() => {
                /*
                 * Mạng hỏng thì màn hình đã đổi rồi nhưng cookie chưa
                 * ghi được — tải lại trang sẽ về chế độ cũ. Chấp nhận
                 * được: đây là tuỳ chọn hiển thị, không phải dữ liệu.
                 * Báo lỗi đỏ cho một việc như thế là phản ứng quá tay.
                 */
            });

            dongBo();
        });
    });

    /*
     * THEO DÕI HỆ ĐIỀU HÀNH ĐỔI SÁNG/TỐI GIỮA CHỪNG.
     *
     * Chỉ có ý nghĩa với người đang để "theo hệ thống": máy tự chuyển
     * sang chế độ tối lúc chiều muộn thì trang phải đổi theo, không bắt
     * họ tải lại. Người đã tự chọn thì KHÔNG đụng vào — lựa chọn tay
     * luôn thắng cài đặt máy.
     */
    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (!DisplaySchemeStore.dangTuDong()) return;

            DisplaySchemeStore.apDung(e.matches ? 'toi' : 'sang', { giuTuDong: true });

            forms.forEach((form) => {
                const input = form.querySelector('[data-scheme-value]');

                if (input) input.value = DisplaySchemeStore.doiSang();
            });
        });
    }
}
