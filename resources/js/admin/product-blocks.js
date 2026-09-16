/** KHỐI MÔ TẢ CHI TIẾT: thêm, xoá, đổi thứ tự. */
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
