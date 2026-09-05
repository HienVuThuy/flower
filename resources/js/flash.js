/*
 * Thông báo tự biến mất sau một khoảng thời gian.
 * ============================================================
 * VÌ SAO CẦN: câu "Đã cập nhật thông tin" nằm mãi trên đầu trang cho tới
 * lần tải trang sau. Khách bấm sang trang khác rồi quay lại vẫn thấy nó,
 * và bắt đầu tự hỏi vừa cập nhật cái gì. Thông báo là thứ báo MỘT LẦN,
 * xong việc thì phải đi.
 *
 * PHẦN THÊM, KHÔNG PHẢI PHẦN CHÍNH. Không có JavaScript thì thông báo
 * vẫn hiện và vẫn đóng được bằng nút × của Bootstrap — chỉ là không tự
 * biến mất.
 *
 * BA ĐIỀU PHẢI ĐÚNG:
 *
 *   1. LỖI SỐNG LÂU HƠN THÀNH CÔNG. "Đã lưu" thì đọc lướt là đủ; "Mật
 *      khẩu hiện tại không đúng" thì người ta cần thời gian đọc và hiểu
 *      phải làm gì. Xoá một thông báo lỗi quá sớm là xoá mất thông tin
 *      mà người dùng cần.
 *
 *   2. RÊ CHUỘT VÀO THÌ DỪNG ĐẾM. Người đang đọc dở mà chữ biến mất
 *      giữa chừng là kiểu khó chịu rất khó chỉ tên. Rời chuột ra thì
 *      đếm lại từ đầu, không đếm tiếp — họ vừa đọc lại từ đầu.
 *
 *   3. THANH THỜI GIAN cho biết nó sắp biến mất. Không có nó thì thông
 *      báo đột ngột mất đi trông như trang bị lỗi.
 */

/**
 * Bao lâu thì tự đóng, theo loại thông báo.
 *
 * BA MỨC, không phải hai. Thời gian sống tỉ lệ với việc người đọc phải
 * làm gì với nó:
 *
 *   success — "Đã lưu". Đọc lướt là đủ, không phải quyết định gì.
 *   warning — "Mã giảm giá đã bị gỡ vì đơn chưa đủ 1.000.000₫". Không
 *             phải lỗi, nhưng có hệ quả về tiền và người đọc cần hiểu
 *             vì sao con số vừa đổi.
 *   error   — "Mật khẩu hiện tại không đúng". Cần đọc, hiểu, rồi làm
 *             lại — mất nhiều thời gian nhất.
 */
const TIMEOUT = {
    success: 5000,
    warning: 7000,
    error: 9000,
};

/**
 * Gắn hẹn giờ tự đóng vào một thông báo đã có sẵn trong trang.
 *
 * XUẤT RA NGOÀI để thông báo do JavaScript tạo (thêm vào giỏ không tải
 * lại trang) dùng đúng cơ chế này — cùng thời gian chờ, cùng cách tạm
 * dừng khi rê chuột, cùng thanh thời gian. Viết lại một bản thứ hai là
 * hai kiểu hành xử khác nhau cho cùng một thứ trên màn hình.
 */
export function dismissAfter(alert) {
    /*
     * Giá trị lạ thì về "success", KHÔNG để undefined.
     *
     * TIMEOUT[undefined] là undefined, và setTimeout(fn, undefined) chạy
     * NGAY LẬP TỨC — thông báo chớp lên rồi biến mất trước khi kịp đọc.
     * Một lỗi gõ sai trong tên loại không được biến thành thông báo
     * không ai thấy.
     */
    const kind = TIMEOUT[alert.dataset.autoDismiss] ? alert.dataset.autoDismiss : 'success';
    const delay = TIMEOUT[kind];

    let timer = null;

    /*
     * Thanh thời gian chạy bằng CSS animation, KHÔNG bằng setInterval.
     *
     * Trình duyệt chạy animation trên luồng vẽ riêng nên nó mượt kể cả
     * lúc JavaScript đang bận. Quan trọng hơn: tạm dừng chỉ là đổi một
     * thuộc tính CSS, không phải tự quản lý thời gian còn lại.
     */
    const bar = document.createElement('span');
    bar.className = 'alert__timer';
    bar.style.animationDuration = `${delay}ms`;
    alert.append(bar);

    const close = () => {
        // Dùng đúng cơ chế của Bootstrap thay vì tự xoá phần tử: nó lo
        // phần chuyển màu mờ dần và bắn sự kiện `closed.bs.alert` cho
        // bất cứ đoạn mã nào đang lắng nghe.
        alert.querySelector('[data-bs-dismiss="alert"]')?.click();
    };

    const start = () => {
        clearTimeout(timer);
        timer = setTimeout(close, delay);
        bar.style.animationPlayState = 'running';
    };

    const pause = () => {
        clearTimeout(timer);
        bar.style.animationPlayState = 'paused';
    };

    /*
     * Dừng khi rê chuột VÀ khi tiêu điểm bàn phím rơi vào trong.
     *
     * focusin/focusout chứ không phải focus/blur: hai sự kiện sau không
     * nổi bọt, nên bấm Tab tới nút × bên trong sẽ không kích hoạt.
     */
    alert.addEventListener('mouseenter', pause);
    alert.addEventListener('focusin', pause);

    // Rời ra thì đếm LẠI TỪ ĐẦU, không đếm tiếp: người đọc vừa quay lại,
    // cho họ trọn thời gian như lần đầu.
    alert.addEventListener('mouseleave', start);
    alert.addEventListener('focusout', start);

    start();
}

export function initFlash() {
    /*
     * Tôn trọng "giảm chuyển động" ở mức HỢP LÝ.
     *
     * Người bật tuỳ chọn này không muốn thấy thanh chạy, nhưng họ VẪN
     * muốn thông báo tự dọn đi. Vì thế chỉ ẩn thanh thời gian (do CSS lo)
     * chứ không tắt hẳn tính năng.
     */
    document.querySelectorAll('[data-auto-dismiss]').forEach(dismissAfter);
}


/*
 * ============================================================
 * THÔNG BÁO NỔI (toast)
 * ============================================================
 * VÌ SAO KHÔNG DÙNG LẠI CHỖ CŨ: thông báo do máy chủ trả về nằm ở ĐẦU
 * trang. Với việc thêm vào giỏ mà không tải lại trang thì khách đang ở
 * giữa hoặc cuối danh sách — một dòng chữ hiện ở đầu trang là một dòng
 * chữ họ không bao giờ thấy.
 *
 * Toast neo theo KHUNG NHÌN nên luôn nằm trong tầm mắt, ở đâu cũng vậy.
 */

/** Khung chứa toast, tạo một lần rồi dùng lại. */
function toastHost() {
    let host = document.querySelector('[data-toast-host]');

    if (host) {
        return host;
    }

    host = document.createElement('div');
    host.className = 'toast-host';
    host.setAttribute('data-toast-host', '');

    /*
     * role="status" + aria-live="polite" — trình đọc màn hình đọc nội
     * dung mới mà KHÔNG cắt ngang thứ người dùng đang nghe. Không có
     * hai thuộc tính này thì với người dùng trình đọc màn hình, bấm
     * "Thêm vào giỏ" là không có gì xảy ra cả.
     */
    host.setAttribute('role', 'status');
    host.setAttribute('aria-live', 'polite');

    document.body.append(host);

    return host;
}

/**
 * Hiện một thông báo nổi ở góc màn hình.
 *
 * @param {string} message  Nội dung
 * @param {'success'|'error'} kind  Quyết định màu và thời gian sống
 */
export function showToast(message, kind = 'success') {
    const alert = document.createElement('div');

    // Dùng đúng lớp alert của Bootstrap: nút × và hiệu ứng mờ dần đã có
    // sẵn, và dismissAfter() bên trên bấm chính nút đó để đóng.
    alert.className = `alert alert-${kind === 'error' ? 'danger' : 'success'} alert-dismissible fade show toast-host__item`;
    alert.setAttribute('data-auto-dismiss', kind === 'error' ? 'error' : 'success');

    // textContent chứ không phải innerHTML: nội dung tới từ máy chủ, và
    // một ngày nào đó nó có thể chứa tên sản phẩm do người khác nhập.
    const text = document.createElement('span');
    text.textContent = message;

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close';
    close.setAttribute('data-bs-dismiss', 'alert');
    close.setAttribute('aria-label', 'Đóng');

    alert.append(text, close);
    toastHost().append(alert);

    // Dọn phần tử khi Bootstrap đóng xong, để khung chứa không phình ra
    // sau vài chục lần thêm hàng.
    alert.addEventListener('closed.bs.alert', () => alert.remove());

    dismissAfter(alert);

    return alert;
}
