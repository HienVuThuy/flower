/** ĐIỀN SẴN SỐ QUÀ TRẢ KÈM — phiếu hoàn tiền / trả hàng ở trang đơn quản trị */
export function initRefundGiftAutofill() {
    document
        .querySelectorAll('[data-qua-tra-kem]:not([data-qua-tra-bound])')
        .forEach((oQua) => {
            oQua.dataset.quaTraBound = '1';

            let cauHinh;
            try {
                cauHinh = JSON.parse(oQua.dataset.quaTraKem);
            } catch {
                return;
            }

            const form = oQua.closest('form');
            const oCha = form?.querySelector(`[data-dong-tra="${cauHinh.cha}"]`);

            if (!oCha) return;

            oQua.addEventListener('input', () => {
                oQua.dataset.suaTay = oQua.value === '' ? '' : '1';
            });

            oCha.addEventListener('input', () => {
                if (oQua.dataset.suaTay === '1') return;

                const r = Math.max(0, parseInt(oCha.value || '0', 10) || 0);
                const n = cauHinh.bang[String(r)];

                oQua.value = n === undefined ? '' : String(n);
            });
        });
}
