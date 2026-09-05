/*
 * Biểu mẫu tạo/sửa sổ nhật ký — đổi phần bên dưới theo kiểu sổ đang chọn.
 * ============================================================
 * NÂNG CẤP DẦN, KHÔNG PHỤ THUỘC.
 *
 * Máy chủ luôn vẽ ra ĐẦY ĐỦ mọi khối. Script này chỉ ẩn bớt những khối
 * không hợp với kiểu sổ đang chọn.
 *
 * Nghĩa là không có JavaScript thì mọi khối đều hiện — đúng hành vi
 * trước đây, không mất một ô nhập nào. Đó là lý do phép ẩn nằm ở đây chứ
 * không nằm trong Blade: nếu Blade chỉ vẽ khối của kiểu sổ hiện tại thì
 * người tắt script không bao giờ đổi được sang khối khác.
 *
 * Cùng nguyên tắc đã dùng cho bộ lọc sản phẩm và cho ô đổi chỉ số ở
 * trang sổ (QĐ-131): trang phải chạy được khi script hỏng.
 */
export function initJournalForm() {
    const form = document.querySelector('[data-journal-form]');

    if (!form) {
        return;
    }

    const radios = form.querySelectorAll('[data-kind-radio]');
    const blocks = form.querySelectorAll('[data-for-kinds]');

    if (!radios.length || !blocks.length) {
        return;
    }

    const apply = () => {
        const chon = form.querySelector('[data-kind-radio]:checked');

        if (!chon) {
            return;
        }

        blocks.forEach((block) => {
            const hop = (block.dataset.forKinds || '')
                .split(/\s+/)
                .filter(Boolean)
                .includes(chon.value);

            /*
             * `hidden` chứ không phải `style.display`.
             *
             * Thuộc tính `hidden` giấu khối khỏi CẢ trình đọc màn hình,
             * còn `display:none` đặt bằng script thì dễ bị một luật CSS
             * khác ghi đè mà không ai để ý.
             */
            block.hidden = !hop;
        });
    };

    radios.forEach((radio) => radio.addEventListener('change', apply));

    apply();
}
