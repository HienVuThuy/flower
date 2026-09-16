/* Nút "Thêm dòng" ở phiếu nhập kho. */
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
