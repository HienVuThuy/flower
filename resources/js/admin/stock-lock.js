/** KHOÁ Ô "SỐ LƯỢNG TỒN" KHI TẮT QUẢN LÝ TỒN KHO */
export function initStockLock() {
    document
        .querySelectorAll('[data-stock-toggle]:not([data-stock-bound])')
        .forEach((select) => {
            select.dataset.stockBound = '1';

            const khoi = select.closest('[data-stock-group]');
            const input = khoi?.querySelector('[data-stock-input]');
            const loiNhac = khoi?.querySelector('[data-stock-note]');

            if (!input) return;

            const capNhat = () => {
                const bat = select.value === '1';

                input.readOnly = !bat;
                input.classList.toggle('is-locked', !bat);
                input.setAttribute('aria-disabled', String(!bat));

                if (loiNhac) loiNhac.hidden = bat;
            };

            capNhat();
            select.addEventListener('change', capNhat);
        });
}
