import { showToast } from './flash';
import { guiForm, coHoTro, capNhatSoGio } from './ajax';

/*
 * Thêm vào giỏ mà KHÔNG tải lại trang.
 * ============================================================
 * VÌ SAO CẦN: khách lướt tới cuối trang chủ, thấy một chậu sen đá, bấm
 * "Thêm vào giỏ" — trang tải lại và ném họ về đầu trang. Muốn xem tiếp
 * thì phải cuộn lại từ đầu. Thêm ba món là ba lần cuộn lại.
 *
 * PHẦN THÊM, KHÔNG PHẢI PHẦN CHÍNH.
 * Biểu mẫu bên dưới vẫn là biểu mẫu thật, gửi tới đúng route đó, và vẫn
 * hoạt động khi JavaScript lỗi hoặc chưa tải xong. Tệp này chỉ CHẶN sự
 * kiện submit lại và làm cùng việc đó bằng fetch. Mọi phép kiểm tra vẫn
 * chạy ở máy chủ — đây là chuyện tiện tay, không phải chuyện tin tưởng.
 *
 * BỐN ĐIỀU PHẢI ĐÚNG:
 *
 *   1. CHỈ CHẶN NÚT "THÊM VÀO GIỎ". Nút "Mua ngay" nằm chung một biểu
 *      mẫu nhưng nó CỐ Ý chuyển sang trang thanh toán — chặn nó là làm
 *      hỏng đúng thứ khách vừa yêu cầu.
 *
 *   2. HỎNG THÌ QUAY VỀ CÁCH CŨ. Mất mạng giữa chừng, máy chủ trả 500,
 *      trình duyệt không có fetch — gửi biểu mẫu như thường. Khách vẫn
 *      thêm được hàng, chỉ là trang có tải lại.
 *
 *   3. SỐ TRONG GIỎ LẤY TỪ MÁY CHỦ. Tự cộng thêm ở trình duyệt là sai
 *      ngay khi khách mở hai tab, hoặc khi giỏ gộp dòng trùng.
 *
 *   4. THÔNG BÁO PHẢI NẰM TRONG TẦM MẮT. Thông báo ở đầu trang thì
 *      người đang ở cuối trang không thấy — mà cả tính năng này sinh ra
 *      chính vì họ đang ở cuối trang.
 */

/** Nút hiện chữ "Đã thêm" bao lâu trước khi trở lại bình thường. */
const DONE_MS = 1600;

/**
 * Đổi nút sang trạng thái "đang gửi" rồi trả lại như cũ.
 *
 * Khoá nút trong lúc chờ để hai cú bấm nhanh không thành hai lần thêm.
 * Máy chủ vẫn cộng dồn đúng nếu điều đó xảy ra, nhưng khách thấy số
 * lượng nhảy 2 sau một cú bấm thì tưởng hệ thống lỗi.
 */
function busy(button) {
    const html = button.innerHTML;

    button.disabled = true;
    button.setAttribute('aria-busy', 'true');

    return () => {
        button.disabled = false;
        button.removeAttribute('aria-busy');
        button.innerHTML = html;
    };
}

/** Nháy chữ "Đã thêm" trên chính nút vừa bấm — phản hồi ngay tại chỗ mắt đang nhìn. */
function flashDone(button, restore) {
    button.innerHTML = '<span>Đã thêm</span>';
    button.classList.add('is-added');

    setTimeout(() => {
        button.classList.remove('is-added');
        restore();
    }, DONE_MS);
}

async function submit(form, button) {
    const restore = busy(button);

    // guiForm + capNhatSoGio nằm ở ./ajax.js: ba chỗ trong hệ thống (thêm
    // vào giỏ, sửa giỏ, yêu thích) cùng gửi biểu mẫu kiểu này và cùng
    // phải cập nhật huy hiệu giỏ. Một bản dùng chung thì sửa một lần.
    const { ok, data } = await guiForm(form);

    if (!ok) {
        restore();
        showToast(data.message ?? 'Không thêm được vào giỏ hàng.', 'error');

        return;
    }

    capNhatSoGio(data.cartCount);

    /*
     * Số lượng bị cắt theo tồn kho thì thông báo phải KHÁC MÀU.
     *
     * Hàng vẫn vào giỏ nên không phải lỗi, nhưng khách không nhận được
     * thứ họ bấm — dùng đúng màu xanh "xong rồi" là để họ lướt qua mà
     * không đọc.
     */
    showToast(data.message ?? 'Đã thêm vào giỏ hàng.', data.clamped ? 'error' : 'success');
    flashDone(button, restore);

    /*
     * BÁO RA NGOÀI LÀ ĐÃ THÊM XONG.
     *
     * Hộp chọn quy cách (variant-dialog.js) cần biết lúc nào thì đóng
     * lại. Cho nó tự đoán bằng một khoảng chờ thì hoặc đóng sớm khi
     * mạng chậm, hoặc để hộp nằm chắn màn hình sau khi đã xong.
     *
     * Phát sự kiện thay vì gọi thẳng hàm của tệp kia: tệp này không cần
     * biết ai đang nghe, và nơi nghe thứ hai sau này (số món trên thanh
     * điều hướng, gợi ý mua kèm...) chỉ việc nghe thêm.
     */
    document.dispatchEvent(new CustomEvent('cart:added', {
        detail: { cartCount: data.cartCount },
    }));
}

export function initAddToCart() {
    // Trình duyệt quá cũ: không làm gì cả, biểu mẫu tự chạy như thường.
    if (!coHoTro()) {
        return;
    }

    /*
     * MỘT trình lắng nghe trên document, không phải mỗi biểu mẫu một cái.
     *
     * Trang chủ có vài chục thẻ sản phẩm. Quan trọng hơn: thẻ được thêm
     * sau khi trang đã tải (cuộn để xem thêm, đổi bộ lọc) vẫn chạy được
     * mà không phải gắn lại gì.
     */
    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form.product-buy');

        if (!form) {
            return;
        }

        /*
         * event.submitter — nút NÀO vừa được bấm.
         *
         * Đây là điều kiện quan trọng nhất trong tệp này. Một biểu mẫu
         * chứa cả "Thêm vào giỏ" lẫn "Mua ngay"; chỉ nút đầu được chặn.
         * Trình duyệt không hỗ trợ submitter thì bỏ qua, để biểu mẫu
         * chạy như cũ — thà tải lại trang còn hơn gửi nhầm đích.
         */
        const button = event.submitter;

        if (!button || !button.hasAttribute('data-add-to-cart')) {
            return;
        }

        event.preventDefault();

        submit(form, button).catch(() => {
            /*
             * Mất mạng, máy chủ trả HTML thay vì JSON, phiên hết hạn...
             * Gửi biểu mẫu theo cách cũ: khách vẫn thêm được hàng, chỉ
             * là trang tải lại. Im lặng nuốt lỗi ở đây mới là hỏng.
             *
             * form.submit() KHÔNG bắn lại sự kiện submit nên không có
             * vòng lặp vô tận.
             */
            form.submit();
        });
    });
}
