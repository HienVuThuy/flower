/*
 * Nút con mắt: hiện/ẩn nội dung ô mật khẩu.
 * ============================================================
 * Nút được đánh dấu `hidden` sẵn trong Blade và CHỈ được bỏ ẩn ở đây.
 * Nhờ vậy trình duyệt tắt JavaScript sẽ không thấy một cái nút bấm vào
 * không có gì xảy ra — thà không có còn hơn có mà hỏng.
 *
 * KHÔNG lưu lại trạng thái "đang hiện" giữa các trang: mật khẩu hiện rõ
 * là thứ chỉ nên kéo dài đúng lúc người ta cần nhìn.
 */

function setupField(field) {
    const input = field.querySelector('input');
    const button = field.querySelector('[data-password-toggle]');

    if (!input || !button) {
        return;
    }

    const iconShow = button.querySelector('[data-icon-show]');
    const iconHide = button.querySelector('[data-icon-hide]');

    button.hidden = false;

    button.addEventListener('click', () => {
        const showing = input.type === 'text';

        input.type = showing ? 'password' : 'text';

        button.setAttribute('aria-pressed', String(!showing));

        const label = showing ? 'Hiện mật khẩu' : 'Ẩn mật khẩu';
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);

        if (iconShow) iconShow.hidden = !showing;
        if (iconHide) iconHide.hidden = showing;

        /*
         * Trả con trỏ về ô nhập, đặt ở CUỐI chuỗi.
         *
         * Đổi thuộc tính type làm trình duyệt đặt lại con trỏ về đầu ô;
         * không xử lý thì người đang gõ dở bấm con mắt xong gõ tiếp sẽ
         * chèn ký tự vào đầu mật khẩu.
         */
        const end = input.value.length;
        input.focus();

        try {
            input.setSelectionRange(end, end);
        } catch {
            // Một số trình duyệt không cho gọi trên input type=password.
        }
    });
}

export function initPasswordToggles(root = document) {
    root.querySelectorAll('[data-password-field]').forEach(setupField);
}
