/*
 * Thanh khuyến mại đóng được, và nhớ là đã đóng.
 * ============================================================
 * VÌ SAO NHỚ Ở TRÌNH DUYỆT, KHÔNG Ở MÁY CHỦ:
 * "Tôi đã xem thanh này rồi" là sở thích hiển thị của một trình duyệt cụ
 * thể, không phải dữ liệu của cửa hàng. Lưu vào session thì khách vãng
 * lai cũng sinh một bản ghi phiên chỉ để nhớ một cái nút — và mất sạch
 * khi phiên hết hạn.
 *
 * THANH MẶC ĐỊNH `hidden` TRONG HTML, JavaScript mới mở ra.
 *
 * Ngược đời nhưng đúng: nếu để hiện sẵn rồi JS ẩn đi, khách đã đóng nó
 * sẽ thấy thanh nhấp nháy một cái ở mỗi lần tải trang (FOUC). Đổi lại,
 * trình duyệt tắt JavaScript sẽ không thấy thanh này — chấp nhận được,
 * vì đây là khối quảng cáo phụ, không phải lối đi tới bất cứ đâu: mọi
 * sản phẩm khuyến mại vẫn tìm thấy qua trang danh sách.
 */

const STORAGE_KEY = 'announcement.dismissed';

/**
 * localStorage có thể ném lỗi: chế độ riêng tư của Safari, hoặc trình
 * duyệt chặn lưu trữ của bên thứ ba. Không bọc thì cả tệp này chết và
 * thanh không bao giờ hiện.
 */
function read() {
    try {
        return localStorage.getItem(STORAGE_KEY);
    } catch {
        return null;
    }
}

function write(value) {
    try {
        localStorage.setItem(STORAGE_KEY, value);
    } catch {
        // Không lưu được thì thôi — lần sau thanh hiện lại. Phiền một
        // chút, nhưng không hỏng gì.
    }
}

export function initAnnouncement() {
    const bar = document.querySelector('[data-announcement]');

    if (!bar) {
        return;
    }

    const key = bar.dataset.announcement;

    // Khoá gồm slug + ngày kết thúc, nên chương trình được gia hạn hoặc
    // đổi nội dung là khoá đổi theo và thanh hiện lại.
    if (read() === key) {
        return;
    }

    bar.hidden = false;

    bar.querySelector('[data-announcement-close]')?.addEventListener('click', () => {
        bar.hidden = true;
        write(key);
    });
}
