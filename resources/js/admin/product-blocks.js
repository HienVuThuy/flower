/**
 * KHỐI MÔ TẢ CHI TIẾT: thêm, xoá, đổi thứ tự.
 * ============================================================
 * ĐÁNH LẠI SỐ SAU MỖI THAO TÁC. Tên trường mang chỉ số (`blocks[2][body]`), và
 * thứ tự gửi lên chính là thứ tự hiện ra ở trang khách. Xoá khối giữa mà không
 * đánh lại số thì mảng gửi lên thủng một lỗ (0,1,3) — PHP vẫn nhận, nhưng lần
 * lưu sau số thứ tự không còn khớp với thứ tự trên màn hình.
 *
 * TĂNG CƯỜNG, KHÔNG PHẢI ĐIỀU KIỆN: không có JavaScript thì các khối đã có vẫn
 * sửa và lưu được như thường; chỉ mất phần thêm khối mới và đổi thứ tự.
 */
export function initProductBlocks() {
    document
        .querySelectorAll('[data-blocks]:not([data-blocks-bound])')
        .forEach((khung) => {
            khung.dataset.blocksBound = '1';

            const danhSach = khung.querySelector('[data-blocks-list]');

            if (!danhSach) return;

            const danhLaiSo = () => {
                danhSach.querySelectorAll('[data-block-row]').forEach((dong, i) => {
                    dong.querySelectorAll('[name^="blocks["]').forEach((o) => {
                        o.name = o.name.replace(/^blocks\[[^\]]*\]/, `blocks[${i}]`);
                    });
                });
            };

            khung.querySelectorAll('[data-block-add]').forEach((nut) => {
                nut.addEventListener('click', () => {
                    const mau = khung.querySelector(
                        `[data-block-template="${nut.dataset.blockAdd}"]`,
                    );

                    if (!mau) return;

                    danhSach.append(mau.content.cloneNode(true));
                    danhLaiSo();

                    // Đưa con trỏ vào ô vừa thêm: thêm xong mà phải tự đi tìm
                    // chỗ gõ là một bước thừa cho mỗi khối.
                    danhSach.lastElementChild
                        ?.querySelector('textarea, input[type="file"]')
                        ?.focus();
                });
            });

            danhSach.addEventListener('click', (e) => {
                const dong = e.target.closest('[data-block-row]');

                if (!dong) return;

                if (e.target.closest('[data-block-remove]')) {
                    dong.remove();
                    danhLaiSo();

                    return;
                }

                if (e.target.closest('[data-block-up]')) {
                    dong.previousElementSibling?.before(dong);
                    danhLaiSo();

                    return;
                }

                if (e.target.closest('[data-block-down]')) {
                    dong.nextElementSibling?.after(dong);
                    danhLaiSo();
                }
            });
        });
}
