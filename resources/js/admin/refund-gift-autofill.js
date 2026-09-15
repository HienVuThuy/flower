/**
 * ĐIỀN SẴN SỐ QUÀ TRẢ KÈM — phiếu hoàn tiền / trả hàng ở trang đơn quản trị
 * ============================================================
 * Nhập số món chính trả về thì ô quà ngay dưới tự điền số quà cần trả kèm.
 *
 * KHÔNG TÍNH GÌ Ở ĐÂY: bảng "trả r món chính → n quà" do máy chủ dựng sẵn
 * (GiftReturnCalculator) theo luật của từng món quà, gắn vào ô quà qua
 * data-qua-tra-kem. Tệp này chỉ tra bảng.
 *
 * ĐIỀN SẴN, KHÔNG KHOÁ: người lập phiếu sửa được ô quà. Đã sửa tay thì thôi
 * không ghi đè nữa — trừ khi họ xoá trống ô đó.
 */
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
