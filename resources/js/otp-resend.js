/* Đồng hồ đếm ngược cho nút "Gửi lại mã". */

export function initOtpResend() {
    const button = document.querySelector('[data-resend-countdown]');

    if (!button) {
        return;
    }

    let left = Number(button.dataset.resendCountdown);

    if (!Number.isFinite(left) || left < 1) {
        return;
    }

    const nhan = button.dataset.resendLabel || 'Gửi lại mã';

    const ve = () => {
        button.textContent = left > 0 ? `Gửi lại mã sau ${left} giây` : nhan;
        button.disabled = left > 0;
    };

    ve();

    const dongHo = setInterval(() => {
        left -= 1;
        ve();

        if (left <= 0) {
            clearInterval(dongHo);
        }
    }, 1000);
}
