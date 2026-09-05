/*
 * Tiện nghi thêm cho menu tài khoản.
 * ============================================================
 * Menu là một <details> — TỰ NÓ đã mở/đóng được, có bàn phím, và trình
 * đọc màn hình hiểu trạng thái. Tệp này KHÔNG làm menu chạy được; nó
 * chỉ thêm hai thói quen mà người dùng mong đợi ở một menu thả xuống:
 *
 *   1. bấm ra ngoài thì đóng;
 *   2. bấm Esc thì đóng, và trả tiêu điểm về nút.
 *
 * Tắt JavaScript thì mất đúng hai tiện nghi đó, mọi liên kết bên trong
 * vẫn bấm được — khác hẳn dropdown của Bootstrap, thứ không mở nổi khi
 * không có JS.
 */

function setup(menu) {
    const summary = menu.querySelector('summary');

    if (!summary) {
        return;
    }

    /*
     * Dùng 'click' ở giai đoạn nổi bọt trên document.
     *
     * menu.contains(target) phân biệt "bấm bên trong" với "bấm ra
     * ngoài" — kể cả khi bấm vào chữ nằm sâu trong một liên kết.
     */
    document.addEventListener('click', (event) => {
        if (menu.open && !menu.contains(event.target)) {
            menu.open = false;
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && menu.open) {
            menu.open = false;

            // Không trả tiêu điểm thì nó rơi về <body>, người dùng bàn
            // phím mất dấu đang đứng ở đâu.
            summary.focus();
        }
    });
}

export function initAccountMenu() {
    document.querySelectorAll('[data-account-menu]').forEach(setup);
}
