/*
 * Điều hướng quản trị KHÔNG TẢI LẠI CẢ TRANG.
 * ============================================================
 * Bấm một mục ở thanh bên trước đây tải lại toàn bộ: thanh bên, thanh
 * trên, CSS, JavaScript — tất cả dựng lại từ đầu chỉ để đổi phần ruột.
 * Kết quả là một khoảng trắng chớp qua mỗi lần bấm, và người trực bấm
 * qua lại giữa Đơn hàng và Sản phẩm hàng chục lần mỗi ca.
 *
 * Nay chỉ lấy về đúng phần nội dung và thay chỗ cũ.
 *
 * ============================================================
 * LUÔN CÓ ĐƯỜNG LÙI VỀ CÁCH CŨ.
 *
 * Mọi lỗi — mạng hỏng, máy chủ trả 500, HTML không có khối cần tìm —
 * đều rơi về `location.href = url`, tức là tải lại trang y như khi
 * không có JavaScript. Một tính năng làm-cho-mượt KHÔNG được phép biến
 * thành một trang quản trị bấm không đi đâu cả.
 *
 * Cũng vì thế nó chỉ chặn những cú bấm CHẮC CHẮN xử lý được: chuột
 * trái, không giữ phím nào, cùng tên miền. Ctrl+bấm để mở tab mới vẫn
 * phải chạy như thường.
 */

/** Nơi thay ruột. Không có thì tính năng này tự tắt. */
const KHUNG = '[data-admin-content]';

/**
 * Chạy lại phần khởi tạo JavaScript cho nội dung vừa thay.
 *
 * Truyền từ ngoài vào thay vì import thẳng: tệp này không nên biết dự
 * án có những mô-đun nào — app.js mới là nơi giữ danh sách đó.
 */
let khoiTaoLai = () => {};

export function initAdminNav(bootLaiUi) {
    const khung = document.querySelector(KHUNG);

    if (!khung) {
        return;
    }

    if (typeof bootLaiUi === 'function') {
        khoiTaoLai = bootLaiUi;
    }

    document.addEventListener('click', (e) => {
        const link = e.target.closest('[data-admin-link]');

        if (!link || !nhanDuoc(e, link)) {
            return;
        }

        e.preventDefault();
        diToi(link.href, true);
    });

    /*
     * NÚT LÙI CỦA TRÌNH DUYỆT PHẢI CHẠY.
     *
     * Đổi URL bằng pushState mà không nghe popstate thì bấm Back sẽ đổi
     * thanh địa chỉ nhưng nội dung đứng im — trạng thái tệ hơn hẳn việc
     * tải lại cả trang, vì màn hình đang nói dối về nơi mình đang đứng.
     */
    window.addEventListener('popstate', () => {
        diToi(window.location.href, false);
    });
}

/** Cú bấm này có thuộc loại mình xử lý được không. */
function nhanDuoc(e, link) {
    if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
        return false;
    }

    if (link.target && link.target !== '_self') {
        return false;
    }

    if (link.hasAttribute('download') || link.classList.contains('is-disabled')) {
        return false;
    }

    // Khác tên miền thì để trình duyệt lo.
    return new URL(link.href, window.location.origin).origin === window.location.origin;
}

async function diToi(url, ghiLichSu) {
    const khung = document.querySelector(KHUNG);

    if (!khung) {
        window.location.href = url;

        return;
    }

    khung.setAttribute('aria-busy', 'true');
    khung.classList.add('is-loading');

    try {
        const res = await fetch(url, {
            headers: { 'X-Requested-With': 'fetch' },
            credentials: 'same-origin',
        });

        /*
         * CHUYỂN HƯỚNG THÌ TẢI THẲNG.
         *
         * Đăng nhập hết hạn là trường hợp thật: máy chủ đá về trang
         * login, và nếu ta cứ moi ruột câu trả lời đó ra thì người quản
         * trị nhìn thấy biểu mẫu đăng nhập nằm gọn trong khung nội dung
         * của trang quản trị.
         */
        if (!res.ok || res.redirected) {
            window.location.href = res.redirected ? res.url : url;

            return;
        }

        const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
        const moi = doc.querySelector(KHUNG);

        if (!moi) {
            window.location.href = url;

            return;
        }

        khung.innerHTML = moi.innerHTML;
        document.title = doc.title;

        danhDauTheoMayChu(doc, url);

        if (ghiLichSu) {
            window.history.pushState({}, '', url);
        }

        /*
         * VỀ ĐẦU TRANG. Không cuộn thì bấm sang trang khác từ giữa một
         * bảng dài sẽ mở trang mới ở giữa chừng, và người dùng tưởng
         * trang bị lỗi vì không thấy tiêu đề đâu.
         */
        window.scrollTo({ top: 0, behavior: 'instant' });

        khoiTaoLai();
    } catch (err) {
        window.location.href = url;
    } finally {
        khung.removeAttribute('aria-busy');
        khung.classList.remove('is-loading');
    }
}

/**
 * Tô sáng mục thanh bên THEO ĐÚNG TRANG MÁY CHỦ VỪA TRẢ VỀ.
 *
 * Lỗi đã sửa: trước đây JS tự đoán mục sáng bằng cách so đường dẫn. Sau
 * khi gộp Tồn đầu kỳ và Trả hàng nhà cung cấp thành tab của trang Nhập kho,
 * `/admin/ton-dau-ky` không nằm dưới `/admin/nhap-kho` — không mục nào sáng,
 * trong khi máy chủ (routeIs trong layout) đã đánh dấu đúng "Nhập kho".
 * Hai nơi giữ một luật thì lệch nhau; nay chỉ còn máy chủ quyết định.
 *
 * Trang trả về không có thanh bên (hiếm) thì mới lùi về cách so đường dẫn.
 */
function danhDauTheoMayChu(doc, url) {
    const sangTrenMayChu = doc.querySelectorAll('.admin-sidebar [data-admin-link].is-active');

    if (sangTrenMayChu.length === 0 && !doc.querySelector('.admin-sidebar')) {
        danhDauDangXem(url);

        return;
    }

    const duongSang = new Set(
        [...sangTrenMayChu].map((a) => new URL(a.href, window.location.origin).pathname),
    );

    document.querySelectorAll('.admin-sidebar [data-admin-link]').forEach((link) => {
        const dangXem = duongSang.has(new URL(link.href, window.location.origin).pathname);

        link.classList.toggle('is-active', dangXem);
        link.toggleAttribute('aria-current', dangXem);
    });
}

/**
 * Tô sáng mục đang xem — cách DỰ PHÒNG, chỉ khi trang trả về không có thanh bên.
 *
 * So theo ĐƯỜNG DẪN, không so cả URL: `/admin/orders?trang=2` vẫn là
 * mục "Đơn hàng". So nguyên chuỗi thì mọi trang có tham số đều mất
 * đánh dấu, và người dùng không biết mình đang ở đâu.
 */
function danhDauDangXem(url) {
    const duong = new URL(url, window.location.origin).pathname;

    document.querySelectorAll('[data-admin-link]').forEach((link) => {
        const cua = new URL(link.href, window.location.origin).pathname;

        // Khớp chính xác, hoặc là tiền tố của trang con (/admin/orders/FP-1).
        const dangXem = cua === duong
            || (cua !== '/admin' && duong.startsWith(cua + '/'));

        link.classList.toggle('is-active', dangXem);
        link.toggleAttribute('aria-current', dangXem);
    });
}
