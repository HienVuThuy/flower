import { guiForm, coHoTro, capNhatSoGio } from './ajax';
import { showToast } from './flash';

/* Sửa giỏ hàng mà KHÔNG tải lại trang. */

function donNutThua(khung) {
    khung.querySelector('[data-cart-select-submit]')?.remove();
}

function traTieuDiem(id) {
    if (!id) {
        return;
    }

    const moi = document.getElementById(id);

    if (moi) {
        moi.focus();
    }
}

export function initCartLive() {
    const khung = document.querySelector('[data-cart-live]');

    if (!khung || !coHoTro()) {
        return;
    }

    donNutThua(khung);

    let dangGui = false;

    const gui = async (form, imLang = false) => {
        if (dangGui) {
            return;
        }

        dangGui = true;

        khung.classList.add('is-sending');

        const tieuDiem = document.activeElement?.id;

        try {
            const { ok, data } = await guiForm(form);

            if (!ok) {
                showToast(data.message ?? 'Không thực hiện được thao tác này.', 'error');

                return;
            }

            khung.innerHTML = data.html;
            donNutThua(khung);
            capNhatSoGio(data.cartCount);
            traTieuDiem(tieuDiem);

            if (!imLang) {
                showToast(data.message ?? 'Đã cập nhật giỏ hàng.');
            }
        } finally {
            dangGui = false;
            khung.classList.remove('is-sending');
        }
    };

    khung.addEventListener('submit', (event) => {
        const form = event.target.closest('form[data-cart-form]');

        if (!form) {
            return;
        }

        event.preventDefault();

        gui(form, form.hasAttribute('data-cart-select')).catch(() => {
            dangGui = false;
            form.submit();
        });
    });

    document.addEventListener('cart:added', async () => {
        const url = khung.dataset.cartLive;

        if (!url || dangGui) {
            return;
        }

        dangGui = true;

        try {
            const res = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (!res.ok) {
                return;
            }

            const data = await res.json();

            khung.innerHTML = data.html;
            donNutThua(khung);
        } catch {
        } finally {
            dangGui = false;
        }
    });

    khung.addEventListener('change', (event) => {
        const form = khung.querySelector('[data-cart-select]');

        if (!form) {
            return;
        }

        if (event.target.matches('[data-cart-pick-all]')) {
            khung.querySelectorAll('[data-cart-pick]').forEach((o) => {
                o.checked = event.target.checked;
            });
        } else if (!event.target.matches('[data-cart-pick]')) {
            return;
        }

        gui(form, true).catch(() => {
            dangGui = false;
            form.submit();
        });
    });
}
