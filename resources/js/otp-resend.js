/*
 * Đồng hồ đếm ngược cho nút "Gửi lại mã".
 * ============================================================
 * VÌ SAO CẦN: máy chủ dựng nút với chữ "Gửi lại mã sau 47 giây". Con số
 * đó ĐÚNG ĐÚNG MỘT LẦN — lúc trang được dựng. Khách ngồi nhìn 47 giây
 * trôi qua mà chữ không đổi, và hết 47 giây rồi nút vẫn khoá: họ phải
 * đoán mà tải lại trang mới bấm được.
 *
 * PHẦN THÊM, KHÔNG PHẢI PHẦN CHÍNH. Không có JavaScript thì nút vẫn khoá
 * đúng như máy chủ quyết định, chỉ là phải tải lại trang. Và MÁY CHỦ VẪN
 * LÀ NƠI QUYẾT ĐỊNH: mở nút sớm ở đây thì bấm cũng chỉ nhận lỗi
 * "Vui lòng đợi N giây nữa" — đây là chuyện hiển thị, không phải chuyện
 * cấp quyền.
 */

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
