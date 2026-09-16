/* Tiện nghi thêm cho menu tài khoản. */

function setup(menu) {
    const summary = menu.querySelector('summary');

    if (!summary) {
        return;
    }

    document.addEventListener('click', (event) => {
        if (menu.open && !menu.contains(event.target)) {
            menu.open = false;
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menu.open) {
            menu.open = false;

            summary.focus();
        }
    });
}

export function initAccountMenu() {
    document.querySelectorAll('[data-account-menu]').forEach(setup);
}
