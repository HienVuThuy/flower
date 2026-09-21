/* Nhắn tin với cửa hàng: tab trong khung chat nổi + trang /tin-nhan. */
import { ganKeoGian } from './chat-resize';

const NHIP_MO = 4000;
const NHIP_DONG = 30000;

export function veTin(tin, laToi) {
    const khoi = document.createElement('div');
    khoi.className = `chat-msg ${laToi ? 'chat-msg--me' : 'chat-msg--them'}`;
    khoi.dataset.id = tin.id;

    const chu = document.createElement('p');
    chu.className = 'chat-msg__text';
    chu.textContent = tin.noi_dung;

    const meta = document.createElement('span');
    meta.className = 'chat-msg__meta';
    meta.textContent = `${tin.nguoi_gui} · ${tin.luc}${tin.phieu ? ` · phiếu ${tin.phieu}` : ''}`;

    khoi.append(chu, meta);

    return khoi;
}

/** Đang xem gần cuối khung (lệch dưới 60px) — chỉ khi đó tin mới mới được tự kéo xuống. */
export function sapCuoi(log) {
    return log.scrollHeight - log.scrollTop - log.clientHeight < 60;
}

/**
 * Có tin mới trong lúc người dùng đang kéo lên đọc tin cũ: KHÔNG giật khung xuống,
 * chỉ hiện nút "Có tin mới" — bấm vào mới xuống cuối. Tự ẩn khi đã cuộn tới cuối.
 */
export function baoTinMoi(log) {
    let nut = log.parentElement.querySelector(':scope > [data-chat-tin-moi]');

    if (!nut) {
        nut = document.createElement('button');
        nut.type = 'button';
        nut.className = 'chat-tin-moi';
        nut.dataset.chatTinMoi = '';
        nut.textContent = 'Có tin mới — xem';
        nut.hidden = true;
        nut.addEventListener('click', () => {
            log.scrollTo({ top: log.scrollHeight, behavior: 'smooth' });
            nut.hidden = true;
        });
        log.addEventListener('scroll', () => { if (sapCuoi(log)) nut.hidden = true; });
        log.after(nut);
    }

    nut.hidden = false;
}

export function cuonCuoi(log) {
    log.scrollTop = log.scrollHeight;
    const nut = log.parentElement.querySelector(':scope > [data-chat-tin-moi]');
    if (nut) nut.hidden = true;
}

export function ganPhimEnter(o, form) {
    o.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
            e.preventDefault();
            form.requestSubmit();
        }
    });
}

function datHuyHieu(so) {
    document.querySelectorAll('[data-chat-badge]').forEach((b) => {
        b.hidden = so < 1;
        b.textContent = so > 9 ? '9+' : String(so);
    });
}

function hoiThoai(khung) {
    const log = khung.querySelector('[data-chat-log]');
    const form = khung.querySelector('[data-chat-form]');
    const o = form.querySelector('textarea');
    const nut = form.querySelector('button[type="submit"]');
    const loi = form.querySelector('[data-chat-error]');
    const trong = khung.querySelector('[data-chat-empty]');
    const token = form.querySelector('input[name="_token"]')?.value ?? '';
    let cuoi = Math.max(0, ...[...log.querySelectorAll('[data-id]')].map((d) => Number(d.dataset.id)));
    let dangTai = false;

    /* epCuoi: chính mình vừa gửi → luôn xuống cuối. Còn lại chỉ xuống khi đang ở cuối. */
    const them = (ds, epCuoi = false) => {
        const oCuoi = sapCuoi(log);
        let coMoi = false;

        ds.filter((t) => t.id > cuoi).forEach((t) => {
            log.insertBefore(veTin(t, t.tu_khach), trong);
            cuoi = Math.max(cuoi, t.id);
            coMoi = true;
        });
        if (trong) trong.hidden = log.querySelector('[data-id]') !== null;

        if (epCuoi || (coMoi && oCuoi)) {
            cuonCuoi(log);
        } else if (coMoi) {
            baoTinMoi(log);
        }
    };

    const tai = async () => {
        if (dangTai) return;
        dangTai = true;
        try {
            const res = await fetch(`${khung.dataset.urlTin}?sau=${cuoi}&da_xem=1`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (res.ok) {
                const data = await res.json();
                them(data.tin ?? []);
                datHuyHieu(data.chua_doc ?? 0);
            }
        } catch {
            /* mạng chập chờn: lượt sau thử lại */
        } finally {
            dangTai = false;
        }
    };

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const noiDung = o.value.trim();
        if (noiDung === '' || nut.disabled) return;

        nut.disabled = true;
        loi.hidden = true;

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token },
                credentials: 'same-origin',
                body: new FormData(form),
            });
            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                loi.textContent = data.message ?? 'Chưa gửi được tin nhắn. Vui lòng thử lại.';
                loi.hidden = false;
                return;
            }

            o.value = '';
            them([data.tin], true);
        } catch {
            loi.textContent = 'Không kết nối được. Vui lòng thử lại.';
            loi.hidden = false;
        } finally {
            nut.disabled = false;
            o.focus();
        }
    });

    ganPhimEnter(o, form);
    log.scrollTop = log.scrollHeight;

    return { tai };
}

export function initLiveChat() {
    const noi = document.querySelector('[data-ai-chat]:not([data-chat-init])');
    if (noi) noi.dataset.chatInit = '1';
    const tab = noi?.querySelectorAll('[data-chat-tab]') ?? [];

    tab.forEach((nutTab) => {
        nutTab.addEventListener('click', () => {
            tab.forEach((t) => {
                const chon = t === nutTab;
                t.classList.toggle('is-active', chon);
                t.setAttribute('aria-selected', String(chon));
                noi.querySelector(`[data-chat-pane="${t.dataset.chatTab}"]`).hidden = !chon;
            });
            noi.dispatchEvent(new Event('chat:doi-tab'));
        });
    });

    document.querySelectorAll('[data-live-chat]:not([data-chat-bound])').forEach((khung) => {
        khung.dataset.chatBound = '1';
        const { tai } = hoiThoai(khung);
        const trongNoi = khung.closest('[data-ai-chat]');

        const dangHien = () => !document.hidden
            && (!trongNoi || (trongNoi.open && !khung.closest('[data-chat-pane]').hidden));

        const nhip = () => { if (dangHien()) tai(); };

        if (!trongNoi) {
            tai();
        } else {
            trongNoi.addEventListener('toggle', nhip);
            trongNoi.addEventListener('chat:doi-tab', nhip);
        }

        setInterval(nhip, NHIP_MO);
        document.addEventListener('visibilitychange', nhip);
    });

    if (noi) {
        const panel = noi.querySelector('[data-ai-chat-panel]');
        const logDangHien = () => panel.querySelector('[data-chat-pane]:not([hidden]) .ai-chat__log, [data-chat-pane]:not([hidden]) .chat-thread__log');

        ganKeoGian(panel, panel.querySelector('[data-chat-keo]'), 'khach-chat-kich-thuoc', {
            minW: 300,
            minH: 160,
            macDinh: { chieuCao: () => logDangHien()?.getBoundingClientRect().height || 300 },
            apDung: (w, h) => {
                panel.classList.toggle('da-keo', w !== null);
                panel.style.width = w === null ? '' : `${w}px`;
                panel.style.setProperty('--chat-log-h', h === null ? '' : `${h}px`);
            },
        });
    }

    const urlChuaDoc = noi?.dataset.chatUnreadUrl;

    if (urlChuaDoc) {
        const demChuaDoc = async () => {
            if (document.hidden || noi.open) return;
            try {
                const res = await fetch(urlChuaDoc, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (res.ok) datHuyHieu((await res.json()).chua_doc ?? 0);
            } catch {
                /* bỏ qua */
            }
        };

        demChuaDoc();
        setInterval(demChuaDoc, NHIP_DONG);
    }
}
