/**
 * TRẠNG THÁI NỀN SÁNG / TỐI — nơi duy nhất đọc và ghi `data-scheme`.
 * ============================================================
 * Hai chỗ cần tới nó: nút trên thanh header và bộ chọn ba mức ở trang
 * Hồ sơ. Tách ra một chỗ để hai nơi không có hai cách hiểu khác nhau về
 * "đang ở chế độ nào" — thứ chắc chắn lệch nếu chép đôi.
 *
 * `data-scheme` trên <html> LUÔN là giá trị cụ thể ("sang" hoặc "toi").
 * Trạng thái "theo hệ thống" được nhớ riêng ở `data-scheme-auto`, do
 * đoạn script trong <head> đặt — xem layouts/app.blade.php.
 *
 * Tách hai thứ ra vì CSS cần biết PHẢI VẼ MÀU GÌ (một trong hai), còn
 * giao diện cần biết NGƯỜI DÙNG ĐÃ CHỌN GÌ (một trong ba). Nhét cả ba
 * giá trị vào `data-scheme` thì CSS phải xử lý "auto" bằng một khối
 * @media riêng — tức là chép bảng màu tối thành hai bản.
 */
export const DisplaySchemeStore = {
    /** Chế độ đang HIỂN THỊ: 'sang' hoặc 'toi'. */
    dangHien() {
        return document.documentElement.dataset.scheme === 'toi' ? 'toi' : 'sang';
    },

    /** Người dùng có đang để "theo hệ thống" không. */
    dangTuDong() {
        return document.documentElement.dataset.schemeAuto === '1';
    },

    /** Chế độ mà lần bấm tới sẽ chuyển sang. */
    doiSang() {
        return this.dangHien() === 'toi' ? 'sang' : 'toi';
    },

    /**
     * Vẽ lại trang theo chế độ mới.
     *
     * `giuTuDong` dùng khi chính hệ điều hành vừa đổi: màn hình đổi theo
     * nhưng người dùng VẪN đang ở chế độ "theo hệ thống", không phải vừa
     * tự tay chọn.
     */
    apDung(cheDo, { giuTuDong = false } = {}) {
        const el = document.documentElement;

        el.dataset.scheme = cheDo === 'toi' ? 'toi' : 'sang';

        // Trang quản trị dùng thêm bộ màu tối của Bootstrap cho bảng, ô nhập,
        // thông báo. Không đổi theo thì bấm nút xong nền tối mà bảng vẫn trắng.
        if (el.hasAttribute('data-admin')) {
            el.setAttribute('data-bs-theme', el.dataset.scheme === 'toi' ? 'dark' : 'light');
        }

        if (giuTuDong) {
            el.dataset.schemeAuto = '1';
        } else {
            delete el.dataset.schemeAuto;
        }
    },
};
