/**
 * KÉO GIÃN KHUNG CHAT: khung neo góc dưới phải nên tay nắm đặt ở góc TRÊN TRÁI —
 * kéo lên / sang trái là to ra. Nhớ kích thước theo từng trình duyệt; bấm đúp tay nắm để về mặc định.
 * Bàn phím: mũi tên trên tay nắm đổi 20px mỗi lần.
 */
export function ganKeoGian(khung, tayNam, khoa, { minW, minH, apDung, macDinh }) {
    if (!khung || !tayNam || tayNam.dataset.keoBound) return;
    tayNam.dataset.keoBound = '1';

    /* Phần co giãn chỉ là vùng tin nhắn; tiêu đề, tab, ô nhập vẫn phải nằm trong màn hình. */
    const gioiHan = (w, h) => {
        const phanKhac = khung.getBoundingClientRect().height - macDinh.chieuCao();

        return [
            Math.round(Math.min(Math.max(w, minW), window.innerWidth - 32)),
            Math.round(Math.max(minH, Math.min(h, window.innerHeight - 90 - phanKhac))),
        ];
    };

    const dat = (w, h, luu = true) => {
        const [ww, hh] = gioiHan(w, h);
        apDung(ww, hh);

        if (luu) {
            try {
                localStorage.setItem(khoa, JSON.stringify([ww, hh]));
            } catch {
                /* trình duyệt chặn lưu trữ: vẫn đổi kích thước, chỉ không nhớ */
            }
        }
    };

    try {
        const luu = JSON.parse(localStorage.getItem(khoa) || 'null');
        if (Array.isArray(luu) && luu.length === 2) dat(luu[0], luu[1], false);
    } catch {
        /* bỏ qua */
    }

    tayNam.addEventListener('pointerdown', (e) => {
        e.preventDefault();
        const hop = khung.getBoundingClientRect();
        const x0 = e.clientX;
        const y0 = e.clientY;
        const w0 = hop.width;
        const h0 = macDinh.chieuCao();

        tayNam.setPointerCapture(e.pointerId);
        document.body.classList.add('dang-keo-chat');

        const di = (ev) => dat(w0 + (x0 - ev.clientX), h0 + (y0 - ev.clientY));
        const tha = () => {
            tayNam.removeEventListener('pointermove', di);
            document.body.classList.remove('dang-keo-chat');
        };

        tayNam.addEventListener('pointermove', di);
        tayNam.addEventListener('pointerup', tha, { once: true });
        tayNam.addEventListener('pointercancel', tha, { once: true });
    });

    tayNam.addEventListener('dblclick', () => {
        apDung(null, null);
        try {
            localStorage.removeItem(khoa);
        } catch {
            /* bỏ qua */
        }
    });

    tayNam.addEventListener('keydown', (e) => {
        const buoc = { ArrowLeft: [20, 0], ArrowRight: [-20, 0], ArrowUp: [0, 20], ArrowDown: [0, -20] }[e.key];
        if (!buoc) return;
        e.preventDefault();
        dat(khung.getBoundingClientRect().width + buoc[0], macDinh.chieuCao() + buoc[1]);
    });
}
