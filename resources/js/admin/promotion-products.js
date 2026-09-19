/** Bảng sản phẩm của chương trình khuyến mại: mỗi dòng giảm giá riêng hoặc tặng quà riêng. */
export function initPromotionProducts() {
    document.querySelectorAll('[data-km-san-pham]:not([data-bound])').forEach((form) => {
        form.dataset.bound = '1';

        const body = form.querySelector('[data-dong-san-pham]');
        const picker = form.querySelector('[data-chon-san-pham]');
        const mau = document.querySelector('[data-mau-dong]');
        const trong = form.querySelector('[data-trong]');
        const kieuChung = form.dataset.kieu;
        const mucChung = parseFloat(form.dataset.muc || '0');
        const quaChung = form.dataset.quaChung;
        const tienTe = JSON.parse(form.dataset.tienTe || '{}');

        let chiSo = body.querySelectorAll('[data-row]').length;

        const fmt = (n) => {
            const so = new Intl.NumberFormat(tienTe.code === 'USD' ? 'en-US' : 'vi-VN', {
                minimumFractionDigits: tienTe.decimals ?? 0,
                maximumFractionDigits: tienTe.decimals ?? 0,
            }).format(n);

            return tienTe.position === 'before' ? tienTe.symbol + so : so + tienTe.symbol;
        };

        const giaSau = (goc, kieu, muc) => {
            if (goc === null || Number.isNaN(goc) || Number.isNaN(muc)) return null;

            let ket;
            if (kieu === 'percent') ket = goc - (goc * muc / 100);
            else if (kieu === 'fixed_amount') ket = goc - muc;
            else if (kieu === 'fixed_price') ket = muc;
            else return null;

            return Math.min(Math.max(ket, 0), goc);
        };

        const lamMoi = (dong) => {
            const kieu = dong.querySelector('[data-type]').value || kieuChung;
            const laQua = kieu === 'tang_qua';

            dong.querySelector('[data-o="giam"]').hidden = laQua;
            dong.querySelector('[data-o="qua"]').hidden = !laQua;

            const ketQua = dong.querySelector('[data-final]');

            if (laQua) {
                const chon = dong.querySelector('[data-qua]');
                const soLuong = dong.querySelector('[data-qty]').value || dong.querySelector('[data-qty]').placeholder;
                const ten = chon.value ? chon.selectedOptions[0].text : quaChung.replace(/^\d+ × /, '');
                ketQua.textContent = ten ? `Tặng ${soLuong} × ${ten}` : 'Chưa chọn quà';
                return;
            }

            const gocRaw = dong.querySelector('[data-base-price]').dataset.basePrice;
            const goc = gocRaw === '' || gocRaw == null ? null : parseFloat(gocRaw);
            const mucO = dong.querySelector('[data-value]').value;
            const muc = mucO !== '' ? parseFloat(mucO) : mucChung;
            const ket = giaSau(goc, kieu, muc);

            ketQua.textContent = ket === null ? '—' : fmt(ket);
        };

        const lamMoiTatCa = () => {
            body.querySelectorAll('[data-row]').forEach(lamMoi);
            trong.classList.toggle('d-none', body.querySelectorAll('[data-row]').length > 0);
        };

        form.querySelector('[data-them-san-pham]').addEventListener('click', () => {
            const opt = picker.selectedOptions[0];
            if (!opt || !opt.value || !mau) return;

            const html = mau.innerHTML
                .replaceAll('__I__', String(chiSo++))
                .replaceAll('__ID__', opt.value)
                .replaceAll('__NAME__', opt.dataset.name)
                .replaceAll('__CATEGORY__', opt.dataset.category)
                .replaceAll('__PRICE__', opt.dataset.price ?? '');

            body.insertAdjacentHTML('beforeend', html);

            const moi = body.lastElementChild;
            const o = moi.querySelector('[data-base-price]');
            o.textContent = opt.dataset.price ? fmt(parseFloat(opt.dataset.price)) : '—';

            opt.remove();
            picker.value = '';
            lamMoiTatCa();
        });

        const khiDoi = (e) => {
            const dong = e.target.closest('[data-row]');
            if (dong) lamMoi(dong);
        };

        body.addEventListener('input', khiDoi);
        body.addEventListener('change', khiDoi);

        body.addEventListener('click', (e) => {
            if (!e.target.matches('[data-remove]')) return;
            e.target.closest('[data-row]').remove();
            lamMoiTatCa();
        });

        lamMoiTatCa();
    });
}
