/* Khung chat hỗ trợ khách trong trang quản trị. */
import { veTin, ganPhimEnter } from '../live-chat';

const NHIP_TIN = 3000;
const NHIP_DS = 6000;
const NHIP_DONG = 20000;

export function initAdminLiveChat() {
    const goc = document.querySelector('[data-admin-chat]:not([data-bound])');
    if (!goc) return;
    goc.dataset.bound = '1';

    const panel = goc.querySelector('[data-admin-chat-panel]');
    const nutMo = goc.querySelector('[data-admin-chat-toggle]');
    const dsKhach = goc.querySelector('[data-admin-chat-users]');
    const oTim = goc.querySelector('[data-admin-chat-tim]');
    const ai = goc.querySelector('[data-admin-chat-who]');
    const log = goc.querySelector('[data-admin-chat-log]');
    const form = goc.querySelector('[data-admin-chat-form]');
    const o = form.querySelector('textarea');
    const loi = form.querySelector('[data-chat-error]');
    const token = form.querySelector('input[name="_token"]')?.value ?? '';

    let khachId = null;
    let cuoi = 0;

    const huyHieu = (so) => {
        const b = goc.querySelector('[data-admin-chat-badge]');
        b.hidden = so < 1;
        b.textContent = so > 99 ? '99+' : String(so);
    };

    const layJson = async (url) => {
        const res = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!res.ok) throw new Error(String(res.status));

        return res.json();
    };

    const veDanhSach = (ds) => {
        dsKhach.replaceChildren();
        if (ds.length === 0) {
            const p = document.createElement('p');
            p.className = 'admin-chat__hint';
            p.textContent = 'Chưa có hội thoại nào.';
            dsKhach.append(p);
            return;
        }

        ds.forEach((h) => {
            const nut = document.createElement('button');
            nut.type = 'button';
            nut.className = `admin-chat__user${h.id === khachId ? ' is-active' : ''}`;
            nut.dataset.khach = h.id;

            const ten = document.createElement('strong');
            ten.textContent = h.ten;
            const trich = document.createElement('span');
            trich.textContent = `${h.tin_cuoi_tu_khach ? '' : 'Bạn: '}${h.tin_cuoi}`;
            const luc = document.createElement('small');
            luc.textContent = h.luc;
            nut.append(ten, trich, luc);

            if (h.chua_doc > 0) {
                const so = document.createElement('span');
                so.className = 'chat-badge';
                so.textContent = String(h.chua_doc);
                nut.append(so);
            }

            nut.addEventListener('click', () => chon(h.id));
            dsKhach.append(nut);
        });
    };

    const taiDanhSach = async () => {
        try {
            const q = oTim.value.trim();
            const data = await layJson(`${goc.dataset.urlHoiThoai}${q ? `?q=${encodeURIComponent(q)}` : ''}`);
            veDanhSach(data.hoi_thoai ?? []);
            huyHieu(data.chua_doc ?? 0);
        } catch {
            if (!dsKhach.querySelector('.admin-chat__user')) {
                dsKhach.replaceChildren(Object.assign(document.createElement('p'), {
                    className: 'admin-chat__hint',
                    textContent: 'Chưa tải được danh sách. Đang thử lại…',
                }));
            }
        }
    };

    const taiTin = async () => {
        if (!khachId) return;
        try {
            const data = await layJson(`${goc.dataset.urlTin}/${khachId}?sau=${cuoi}`);
            if (cuoi === 0) {
                log.replaceChildren();
                ai.replaceChildren();
                const ten = document.createElement('a');
                ten.href = `${goc.dataset.urlKhach}/${data.khach.id}`;
                ten.textContent = data.khach.ten;
                const email = document.createElement('small');
                email.textContent = ` · ${data.khach.email}`;
                ai.append(ten, email);
            }
            (data.tin ?? []).filter((t) => t.id > cuoi).forEach((t) => {
                log.append(veTin(t, !t.tu_khach));
                cuoi = t.id;
            });
            log.scrollTop = log.scrollHeight;
        } catch {
            /* thử lại lượt sau */
        }
    };

    const chon = (id) => {
        khachId = Number(id);
        cuoi = 0;
        form.hidden = false;
        loi.hidden = true;
        dsKhach.querySelectorAll('.admin-chat__user').forEach((n) => {
            n.classList.toggle('is-active', Number(n.dataset.khach) === khachId);
        });
        taiTin().then(taiDanhSach);
        o.focus();
    };

    const mo = (id = null) => {
        panel.hidden = false;
        nutMo.setAttribute('aria-expanded', 'true');
        taiDanhSach();
        if (id) chon(id);
    };

    nutMo.addEventListener('click', () => (panel.hidden ? mo() : (panel.hidden = true)));
    goc.querySelector('[data-admin-chat-close]').addEventListener('click', () => {
        panel.hidden = true;
        nutMo.setAttribute('aria-expanded', 'false');
    });

    document.addEventListener('click', (e) => {
        const nut = e.target.closest('[data-chat-mo]');
        if (nut) {
            e.preventDefault();
            mo(nut.dataset.chatMo);
        }
    });

    let hen;
    oTim.addEventListener('input', () => {
        clearTimeout(hen);
        hen = setTimeout(taiDanhSach, 300);
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const noiDung = o.value.trim();
        const nut = form.querySelector('button[type="submit"]');
        if (!khachId || noiDung === '' || nut.disabled) return;

        nut.disabled = true;
        loi.hidden = true;
        try {
            const res = await fetch(`${goc.dataset.urlTin}/${khachId}`, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token },
                credentials: 'same-origin',
                body: new FormData(form),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                loi.textContent = data.message ?? 'Chưa gửi được tin nhắn.';
                loi.hidden = false;
                return;
            }
            o.value = '';
            await taiTin();
            taiDanhSach();
        } finally {
            nut.disabled = false;
            o.focus();
        }
    });

    ganPhimEnter(o, form);

    setInterval(() => { if (!panel.hidden && !document.hidden) taiTin(); }, NHIP_TIN);
    setInterval(() => { if (!panel.hidden && !document.hidden) taiDanhSach(); }, NHIP_DS);

    const demChuaDoc = () => { if (panel.hidden && !document.hidden) taiDanhSach(); };
    demChuaDoc();
    setInterval(demChuaDoc, NHIP_DONG);
}
