/** Form "Chăm cây hộ": hiện ô theo chế độ gửi và hỏi máy chủ giá tạm tính (không tự tính giá ở trình duyệt). */
export function initBoardingQuote() {
    document.querySelectorAll('[data-cham-ho]:not([data-bound])').forEach((form) => {
        form.dataset.bound = '1';

        const ketQua = form.querySelector('[data-bao-gia-ket-qua]');
        const so = form.querySelector('[data-bao-gia-so]');
        const chiTiet = form.querySelector('[data-bao-gia-chi-tiet]');
        const token = form.querySelector('input[name="_token"]')?.value;
        let hen = null;
        let luot = 0;

        const cheDo = () => form.querySelector('input[name="mode"]:checked')?.value;

        const hienO = () => {
            const m = cheDo();

            form.querySelectorAll('[data-che-do]').forEach((o) => {
                const dung = o.dataset.cheDo === m;
                o.hidden = !dung;
                o.querySelectorAll('input, select').forEach((i) => { i.disabled = !dung; });
            });
        };

        const baoGia = async () => {
            const soLuot = ++luot;
            const du = new FormData();

            ['boarding_rate_id', 'mode', 'drop_off_on', 'months', 'years', 'return_on', 'boarding_window_id'].forEach((k) => {
                const o = form.querySelector(`[name="${k}"]:not([disabled])${k === 'mode' ? ':checked' : ''}`);
                if (o && o.value !== '') du.append(k, o.value);
            });

            try {
                const res = await fetch(form.dataset.baoGiaUrl, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token },
                    body: du,
                });
                const data = await res.json();

                if (soLuot !== luot) return;

                ketQua.hidden = false;

                if (!res.ok) {
                    so.textContent = '—';
                    chiTiet.textContent = Object.values(data.errors || {})[0]?.[0] || 'Chưa tính được giá.';
                    return;
                }

                so.textContent = data.care_amount;
                chiTiet.textContent = data.tam_tinh
                    ? 'Tạm tính 1 tháng — trả cây mới tính theo số tháng thực gửi.'
                    : `${data.months} tháng · nhận cây lại ngày ${data.return_on}`;
            } catch {
                ketQua.hidden = true;
            }
        };

        form.addEventListener('change', (e) => {
            if (e.target.name === 'mode') hienO();
            if (!e.target.matches('[data-bao-gia]')) return;

            clearTimeout(hen);
            hen = setTimeout(baoGia, 250);
        });

        hienO();
        baoGia();
    });
}
