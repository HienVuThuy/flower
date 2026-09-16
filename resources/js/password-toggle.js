/* Nút con mắt: hiện/ẩn nội dung ô mật khẩu. */

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

        const end = input.value.length;
        input.focus();

        try {
            input.setSelectionRange(end, end);
        } catch {
        }
    });
}

export function initPasswordToggles(root = document) {
    root.querySelectorAll('[data-password-field]').forEach(setupField);
}
