import { DisplaySchemeStore } from './display-scheme';

/** NÚT ĐỔI NỀN SÁNG / TỐI */
let daTheoDoiHeDieuHanh = false;

export function initSchemeToggle() {
    const forms = document.querySelectorAll('[data-scheme-toggle]:not([data-scheme-bound])');

    forms.forEach((form) => {
        form.dataset.schemeBound = '1';

        const input = form.querySelector('[data-scheme-value]');

        const dongBo = () => {
            if (input) input.value = DisplaySchemeStore.doiSang();
        };

        dongBo();

        form.addEventListener('submit', (e) => {
            e.preventDefault();

            const moi = DisplaySchemeStore.doiSang();

            DisplaySchemeStore.apDung(moi);

            if (input) input.value = moi;

            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(new FormData(form)),
                credentials: 'same-origin',
            }).catch(() => {
            });

            dongBo();
        });
    });

    if (window.matchMedia && !daTheoDoiHeDieuHanh) {
        daTheoDoiHeDieuHanh = true;

        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (!DisplaySchemeStore.dangTuDong()) return;

            DisplaySchemeStore.apDung(e.matches ? 'toi' : 'sang', { giuTuDong: true });

            document.querySelectorAll('[data-scheme-value]').forEach((input) => {
                input.value = DisplaySchemeStore.doiSang();
            });
        });
    }
}
