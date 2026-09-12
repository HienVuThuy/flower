/**
 * KHOÁ Ô "SỐ LƯỢNG TỒN" KHI TẮT QUẢN LÝ TỒN KHO
 * ============================================================
 * Ô số tồn chỉ có nghĩa khi sản phẩm được quản lý tồn kho. Để nó gõ được
 * trong lúc đang tắt là mời người dùng điền một con số mà hệ thống không
 * dùng tới — họ gõ "20", lưu, rồi tưởng cửa hàng còn 20 cái.
 *
 * DÙNG readonly, KHÔNG PHẢI disabled: ô disabled KHÔNG được gửi lên máy chủ,
 * nên bật lại quản lý tồn kho là con số cũ biến mất mà không ai bấm gì.
 *
 * TĂNG CƯỜNG, KHÔNG PHẢI ĐIỀU KIỆN: máy chủ đã dựng sẵn trạng thái đúng cho
 * lần tải đầu (xem _form.blade.php). Tệp này chỉ để đổi ngay khi người dùng
 * đổi ô chọn, không phải chờ lưu rồi tải lại.
 */
export function initStockLock() {
    document
        .querySelectorAll('[data-stock-toggle]:not([data-stock-bound])')
        .forEach((select) => {
            select.dataset.stockBound = '1';

            const khoi = select.closest('[data-stock-group]');
            const input = khoi?.querySelector('[data-stock-input]');
            const loiNhac = khoi?.querySelector('[data-stock-note]');

            if (!input) return;

            const capNhat = () => {
                const bat = select.value === '1';

                input.readOnly = !bat;
                input.classList.toggle('is-locked', !bat);
                input.setAttribute('aria-disabled', String(!bat));

                if (loiNhac) loiNhac.hidden = bat;
            };

            capNhat();
            select.addEventListener('change', capNhat);
        });
}
