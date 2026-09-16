/* THAO TÁC HÀNG LOẠT Ở KHU QUẢN TRỊ */

export function initBulkActions() {
    document.querySelectorAll('[data-bulk-form]').forEach((form) => {
        if (form.dataset.bulkReady) return;

        form.dataset.bulkReady = '1';

        const bar = form.querySelector('[data-bulk-bar]');

        if (!bar) return;

        const counter = form.querySelector('[data-bulk-count]');
        const nutBoChon = form.querySelector('[data-bulk-clear]');
        const chonViec = form.querySelector('select[name="viec"]');

        const chonTatCa = Array.from(form.elements)
            .find((el) => el.matches?.('[data-bulk-all]'));

        const oTich = () => Array.from(form.elements)
            .filter((el) => el.matches?.('[data-bulk-item]'));
        const dangChon = () => oTich().filter((o) => o.checked);

        function capNhat() {
            const so = dangChon().length;

            if (counter) {
                counter.textContent = so === 0
                    ? 'Tích chọn các dòng rồi chọn thao tác'
                    : `Đã chọn ${so} dòng`;
            }

            if (chonTatCa) {
                chonTatCa.checked = so > 0 && so === oTich().length;
                chonTatCa.indeterminate = so > 0 && so < oTich().length;
            }
        }

        document.addEventListener('change', (e) => {
            const o = e.target;

            if (!o.matches?.('[data-bulk-item], [data-bulk-all]')) return;
            if (o.form !== form) return;

            if (o.matches('[data-bulk-all]')) {
                oTich().forEach((x) => { x.checked = o.checked; });
            }

            capNhat();
        });

        if (nutBoChon) {
            nutBoChon.addEventListener('click', () => {
                oTich().forEach((o) => { o.checked = false; });
                capNhat();
            });
        }

        form.addEventListener('submit', (e) => {
            if (dangChon().length === 0) {
                e.preventDefault();

                return;
            }

            const canhBao = chonViec?.selectedOptions[0]?.dataset.canhBao;

            if (canhBao && !window.confirm(
                canhBao.replace('{so}', String(dangChon().length))
            )) {
                e.preventDefault();
            }
        });

        capNhat();
    });
}
