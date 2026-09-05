import { guiForm, coHoTro } from './ajax';
import { showToast } from './flash';

/*
 * Bấm tim yêu thích mà KHÔNG tải lại trang.
 * ============================================================
 * VÌ SAO CẦN: nút này nằm trên MỌI thẻ sản phẩm — trang chủ, danh sách,
 * kết quả tìm kiếm, gợi ý mua kèm. Khách lướt tới sản phẩm thứ mười tám,
 * bấm tim, và trang tải lại ném họ về đầu. Muốn thích thêm cái nữa thì
 * cuộn lại từ đầu. Đây là một thao tác chỉ đổi MỘT cái icon, nó không
 * đáng để vẽ lại cả trang.
 *
 * PHẦN THÊM, KHÔNG PHẢI PHẦN CHÍNH. Biểu mẫu bên dưới là POST thật tới
 * đúng route thật, có CSRF, và vẫn chạy khi JavaScript hỏng.
 *
 * BA ĐIỀU PHẢI ĐÚNG:
 *
 *   1. TRẠNG THÁI LẤY TỪ MÁY CHỦ, không tự lật ngược cái đang hiện.
 *      Khách mở hai tab cùng một sản phẩm rồi bấm ở cả hai: lật theo
 *      giao diện thì tab thứ hai hiện ngược hẳn với dữ liệu thật.
 *
 *   2. KHÔNG CHẶN Ở CHÍNH TRANG YÊU THÍCH. Ở đó bấm tim làm sản phẩm
 *      rời khỏi danh sách, kéo theo phân trang và trạng thái "trống" —
 *      đổi mỗi cái icon rồi để thẻ nằm lại là hiển thị một danh sách
 *      không còn đúng. Trang đó tải lại, và đó là hành vi trung thực.
 *
 *   3. HỎNG THÌ QUAY VỀ CÁCH CŨ — gửi biểu mẫu như thường.
 */

/**
 * Vẽ lại một nút tim theo trạng thái máy chủ vừa trả về.
 *
 * Đổi ĐỦ BỐN THỨ, không chỉ cái nhìn thấy:
 *   - class `is-active` (màu),
 *   - `<use href>` của icon (tim rỗng / tim đặc),
 *   - `aria-pressed` (trình đọc màn hình nghe "đã bật"/"đã tắt"),
 *   - `title` và chữ ẩn (người dùng chuột và trình đọc màn hình đọc
 *     việc sẽ xảy ra nếu bấm tiếp).
 *
 * Bỏ quên hai cái sau là để lại một nút nhìn thì đúng mà nghe thì sai.
 */
function veLaiNut(button, active) {
    const nhan = active ? 'Bỏ khỏi yêu thích' : 'Thêm vào yêu thích';

    button.classList.toggle('is-active', active);
    button.setAttribute('aria-pressed', active ? 'true' : 'false');
    button.title = nhan;

    // Icon là <svg><use href="#i-heart"> lấy từ sprite chung, nên đổi
    // hình chỉ là đổi một thuộc tính — không phải dựng lại thẻ svg.
    button.querySelector('use')?.setAttribute('href', active ? '#i-heart-fill' : '#i-heart');

    const chuAn = button.querySelector('.visually-hidden');

    if (chuAn) {
        chuAn.textContent = nhan;
    }
}

export function initWishlist() {
    if (!coHoTro()) {
        return;
    }

    /*
     * Nghe ở document, không gắn vào từng nút: thẻ sản phẩm sinh ra sau
     * khi trang đã tải (đổi bộ lọc, gợi ý mua kèm được vẽ lại cùng khối
     * giỏ hàng) vẫn chạy mà không phải gắn lại gì.
     */
    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form[data-wishlist]');

        if (!form) {
            return;
        }

        // Chính trang "Yêu thích": để trang tải lại — xem điều 2 ở trên.
        if (form.closest('[data-wishlist-list]')) {
            return;
        }

        const button = form.querySelector('[data-wishlist-button]');

        if (!button) {
            return;
        }

        event.preventDefault();

        // Khoá trong lúc chờ: bấm hai lần thật nhanh sẽ thành thích rồi
        // bỏ thích, và câu trả lời về không theo thứ tự thì icon đứng
        // lại ở trạng thái ngược với dữ liệu.
        button.disabled = true;

        guiForm(form)
            .then(({ ok, data }) => {
                if (!ok) {
                    showToast(data.message ?? 'Không lưu được yêu thích.', 'error');

                    return;
                }

                veLaiNut(button, data.active);
                showToast(data.message);
            })
            .catch(() => form.submit())
            .finally(() => {
                button.disabled = false;
            });
    });
}
