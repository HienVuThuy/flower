/* Nút gửi biểu mẫu chỉ ăn MỘT lần: khoá nút và đổi chữ trong lúc máy chủ xử lý.
   Bước đặt hàng mất vài giây (trừ kho, ghi đơn, mở lượt thanh toán) — không khoá thì
   khách tưởng nút hỏng và bấm tiếp, đơn đã tạo rồi lại bị đưa về trang khác. */

const CHU_CHO = 'Đang xử lý…';

export function initGuiMotLan() {
    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form');
        const nut = form?.querySelector('[data-gui-mot-lan]');

        if (!nut || nut.disabled) return;

        const chu = nut.dataset.guiMotLan || CHU_CHO;

        /* Khoá SAU khi trình duyệt đã gửi biểu mẫu — khoá ngay thì dữ liệu nút không được gửi. */
        setTimeout(() => {
            nut.disabled = true;
            nut.setAttribute('aria-busy', 'true');
            nut.textContent = chu;
        }, 0);
    });

    /* Quay lại bằng nút Back: trình duyệt trả trang từ bộ nhớ, phải mở khoá nút. */
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-gui-mot-lan][disabled]').forEach((nut) => {
            nut.disabled = false;
            nut.removeAttribute('aria-busy');

            if (nut.dataset.guiMotLanChu) nut.textContent = nut.dataset.guiMotLanChu;
        });
    });
}
