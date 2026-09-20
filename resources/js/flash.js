/* Thông báo tự biến mất sau một khoảng thời gian. */

const TIMEOUT = {
    success: 5000,
    warning: 7000,
    error: 9000,
};

/**
 * Tự đóng, không nhờ component Alert của Bootstrap.
 * Dự án chỉ nạp Modal / Collapse / Offcanvas cho nhẹ, nên nút x của thông báo
 * phải tự xử lý — trước đây gọi .click() vào nút và chờ Bootstrap, thành ra
 * thông báo nằm lì trên màn hình.
 */
export function dongAlert(alert) {
    if (!alert || alert.dataset.dangDong) return;

    alert.dataset.dangDong = '1';
    alert.classList.remove('show');

    const go = () => alert.remove();

    alert.addEventListener('transitionend', go, { once: true });
    setTimeout(go, 400);
}

export function dismissAfter(alert) {
    const kind = TIMEOUT[alert.dataset.autoDismiss] ? alert.dataset.autoDismiss : 'success';
    const delay = TIMEOUT[kind];

    let timer = null;

    const bar = document.createElement('span');
    bar.className = 'alert__timer';
    bar.style.animationDuration = `${delay}ms`;
    alert.append(bar);

    const start = () => {
        clearTimeout(timer);
        timer = setTimeout(() => dongAlert(alert), delay);
        bar.style.animationPlayState = 'running';
    };

    const pause = () => {
        clearTimeout(timer);
        bar.style.animationPlayState = 'paused';
    };

    alert.addEventListener('mouseenter', pause);
    alert.addEventListener('focusin', pause);

    alert.addEventListener('mouseleave', start);
    alert.addEventListener('focusout', start);

    start();
}

export function initFlash() {
    document.querySelectorAll('[data-auto-dismiss]:not([data-flash-bound])').forEach((alert) => {
        alert.dataset.flashBound = '1';
        dismissAfter(alert);
    });

    if (document.body.dataset.flashClickBound) return;

    document.body.dataset.flashClickBound = '1';

    document.addEventListener('click', (e) => {
        const nut = e.target.closest('[data-bs-dismiss="alert"]');

        if (nut) {
            dongAlert(nut.closest('.alert'));
        }
    });
}

function toastHost() {
    let host = document.querySelector('[data-toast-host]');

    if (host) {
        return host;
    }

    host = document.createElement('div');
    host.className = 'toast-host';
    host.setAttribute('data-toast-host', '');

    host.setAttribute('role', 'status');
    host.setAttribute('aria-live', 'polite');

    document.body.append(host);

    return host;
}

export function showToast(message, kind = 'success') {
    const alert = document.createElement('div');

    alert.className = `alert alert-${kind === 'error' ? 'danger' : 'success'} alert-dismissible fade show toast-host__item`;
    alert.setAttribute('data-auto-dismiss', kind === 'error' ? 'error' : 'success');
    alert.dataset.flashBound = '1';

    const text = document.createElement('span');
    text.textContent = message;

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close';
    close.setAttribute('data-bs-dismiss', 'alert');
    close.setAttribute('aria-label', 'Đóng');

    alert.append(text, close);
    toastHost().append(alert);

    dismissAfter(alert);

    return alert;
}
