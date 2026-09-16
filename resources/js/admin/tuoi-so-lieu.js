/** Số liệu này cũ bao lâu rồi, và tự làm mới khi được bật. */
const CHU_KY = 60_000;
const KHOA = 'admin.tuoi-so-lieu.tu-dong';

let dongHo = null;

function doc() {
    try {
        return localStorage.getItem(KHOA) === '1';
    } catch {
        return false;
    }
}

function ghi(bat) {
    try {
        localStorage.setItem(KHOA, bat ? '1' : '0');
    } catch {
    }
}

export function initTuoiSoLieu() {
    const khoi = document.querySelector('[data-tuoi-so-lieu]:not([data-tuoi-bound])');

    if (dongHo) {
        clearInterval(dongHo);
        dongHo = null;
    }

    if (!khoi) {
        return;
    }

    khoi.dataset.tuoiBound = '1';

    const moc = khoi.querySelector('time');
    const truoc = khoi.querySelector('[data-tuoi-truoc]');
    const congTac = khoi.querySelector('[data-tuoi-tu-dong]');

    const luc = moc ? new Date(moc.getAttribute('datetime')).getTime() : Date.now();

    const veLai = () => {
        const phut = Math.floor((Date.now() - luc) / 60_000);

        if (phut >= 1 && truoc) {
            truoc.hidden = false;
            truoc.textContent = `· cách đây ${phut} phút`;
        }
    };

    veLai();

    if (congTac) {
        congTac.checked = doc();

        congTac.addEventListener('change', () => {
            ghi(congTac.checked);
        });
    }

    dongHo = setInterval(() => {
        veLai();

        if (congTac?.checked && !document.hidden) {
            window.location.reload();
        }
    }, CHU_KY);

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && congTac?.checked && Date.now() - luc > CHU_KY) {
            window.location.reload();
        }
    });
}
