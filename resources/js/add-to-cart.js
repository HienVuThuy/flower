import { showToast } from './flash';
import { guiForm, coHoTro, capNhatSoGio } from './ajax';

/* Thêm vào giỏ mà KHÔNG tải lại trang. */

const DONE_MS = 1600;

function busy(button) {
    const html = button.innerHTML;

    button.disabled = true;
    button.setAttribute('aria-busy', 'true');

    return () => {
        button.disabled = false;
        button.removeAttribute('aria-busy');
        button.innerHTML = html;
    };
}

function flashDone(button, restore) {
    button.innerHTML = '<span>Đã thêm</span>';
    button.classList.add('is-added');

    setTimeout(() => {
        button.classList.remove('is-added');
        restore();
    }, DONE_MS);
}

async function submit(form, button) {
    const restore = busy(button);

    const { ok, data } = await guiForm(form);

    if (!ok) {
        restore();
        showToast(data.message ?? 'Không thêm được vào giỏ hàng.', 'error');

        return;
    }

    capNhatSoGio(data.cartCount);

    showToast(data.message ?? 'Đã thêm vào giỏ hàng.', data.clamped ? 'error' : 'success');
    flashDone(button, restore);

    document.dispatchEvent(new CustomEvent('cart:added', {
        detail: { cartCount: data.cartCount },
    }));
}

export function initAddToCart() {
    if (!coHoTro()) {
        return;
    }

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form.product-buy');

        if (!form) {
            return;
        }

        const button = event.submitter;

        if (!button || !button.hasAttribute('data-add-to-cart')) {
            return;
        }

        event.preventDefault();

        submit(form, button).catch(() => {
            form.submit();
        });
    });
}
