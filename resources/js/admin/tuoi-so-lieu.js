/**
 * Số liệu này cũ bao lâu rồi, và tự làm mới khi được bật.
 * ============================================================
 * MỖI PHÚT CẬP NHẬT CHỮ "cách đây N phút" — không tải lại trang.
 *
 * Người xem cần biết con số mình đang nhìn cũ tới đâu, và biết điều đó
 * không đòi hỏi phải hỏi lại máy chủ. Tách hai việc ra thì trang không
 * nhấp nháy chỉ để nói "đã 3 phút".
 *
 * ============================================================
 * TỰ LÀM MỚI: 60 GIÂY, VÀ CHỈ KHI TAB ĐANG ĐƯỢC NHÌN.
 *
 * Tab nằm dưới nền mà vẫn tải lại mỗi phút là gọi máy chủ hàng trăm lần
 * cho một trang không ai xem. `document.hidden` chặn việc đó; quay lại
 * tab thì làm mới ngay một lần cho đúng.
 */
const CHU_KY = 60_000;
const KHOA = 'admin.tuoi-so-lieu.tu-dong';

let dongHo = null;

/** localStorage có thể ném lỗi (cửa sổ riêng tư, chặn dữ liệu trang). */
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
        /* Không nhớ được thì thôi — công tắc vẫn dùng được trong phiên này. */
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
        // Quay lại tab sau một lúc: làm mới ngay thay vì chờ hết chu kỳ.
        if (!document.hidden && congTac?.checked && Date.now() - luc > CHU_KY) {
            window.location.reload();
        }
    });
}
