import { guiForm, coHoTro } from './ajax';
import { showToast } from './flash';

/* Bấm tim yêu thích mà KHÔNG tải lại trang. */

function veLaiNut(button, active) {
    const nhan = active ? 'Bỏ khỏi yêu thích' : 'Thêm vào yêu thích';

    button.classList.toggle('is-active', active);
    button.setAttribute('aria-pressed', active ? 'true' : 'false');
    button.title = nhan;

    button.querySelector('use')?.setAttribute('href', active ? '#i-heart-fill' : '#i-heart');

    const chuAn = button.querySelector('.visually-hidden');

    if (chuAn) {
        chuAn.textContent = nhan;
    }
}

export function initWishlist() {
    if (!coHoTro()) {
        return;
    }

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form[data-wishlist]');

        if (!form) {
            return;
        }

        if (form.closest('[data-wishlist-list]')) {
            return;
        }

        const button = form.querySelector('[data-wishlist-button]');

        if (!button) {
            return;
        }

        event.preventDefault();

        button.disabled = true;

        guiForm(form)
            .then(({ ok, data }) => {
                if (!ok) {
                    showToast(data.message ?? 'Không lưu được yêu thích.', 'error');

                    return;
                }

                veLaiNut(button, data.active);
                showToast(data.message);
            })
            .catch(() => form.submit())
            .finally(() => {
                button.disabled = false;
            });
    });
}
