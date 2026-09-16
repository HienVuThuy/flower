/* Gửi biểu mẫu bằng fetch — phần dùng chung. */

export async function guiForm(form) {
    const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    return { ok: response.ok, data: await response.json() };
}

export function coHoTro() {
    return Boolean(window.fetch && window.FormData);
}

export function capNhatSoGio(count) {
    if (typeof count !== 'number') {
        return;
    }

    const badge = document.querySelector('[data-cart-badge]');
    const link = document.querySelector('[data-cart-link]');

    if (badge) {
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.hidden = count < 1;
    }

    if (link) {
        link.setAttribute(
            'aria-label',
            count > 0 ? `Giỏ hàng (${count} sản phẩm)` : 'Giỏ hàng (trống)',
        );
    }
}
