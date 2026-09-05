/*
 * HỘP CHỌN QUY CÁCH NGAY TRÊN THẺ SẢN PHẨM
 * ============================================================
 * VẤN ĐỀ NÓ GIẢI QUYẾT: thẻ sản phẩm ở trang danh sách gửi thẳng biểu
 * mẫu "Thêm vào giỏ" mà không hề có ô chọn quy cách. Với hàng có nhiều
 * quy cách, việc đó tạo ra một dòng giỏ KHÔNG QUY CÁCH tính theo giá
 * thấp nhất — cửa hàng không biết giao chậu nào, và ai bỏ qua bước chọn
 * cũng mua được quy cách đắt với giá quy cách rẻ.
 *
 * CartService nay chặn thẳng chuyện đó ở máy chủ. Tệp này lo phần còn
 * lại: đưa khách qua bước chọn mà KHÔNG bắt rời trang.
 *
 * PHẦN THÊM, KHÔNG PHẢI PHẦN CHÍNH.
 * Không có tệp này thì hai nút vẫn là liên kết thật, dẫn tới trang sản
 * phẩm ngay tại bảng chọn quy cách. Chậm hơn một nhịp, nhưng đúng việc
 * và không mất chức năng nào.
 *
 * DÙNG LẠI ĐƯỜNG CŨ, KHÔNG DỰNG ĐƯỜNG MỚI.
 * Hộp thoại chứa một <form class="product-buy"> y hệt biểu mẫu ở trang
 * chi tiết, gửi tới đúng route đó, với đúng các thuộc tính data mà
 * add-to-cart.js đang lắng nghe. Nhờ vậy phần thêm-không-tải-lại-trang,
 * phần cập nhật số trong giỏ và phần thông báo đều chạy sẵn — không có
 * bản sao thứ hai nào để sau này lệch đi.
 */

/** Chỉ dựng một hộp thoại cho cả trang, dùng lại cho mọi thẻ. */
let hop = null;

function tienVND(n) {
    return new Intl.NumberFormat('vi-VN').format(Math.round(n)) + '₫';
}

/**
 * Lấy CSRF token từ một biểu mẫu bất kỳ đang có trên trang.
 *
 * Trang nào có thẻ sản phẩm cũng có ít nhất một biểu mẫu (tìm kiếm,
 * đăng xuất, thêm vào giỏ). Không có thì trả null và ta bỏ hẳn phần
 * nâng cấp — thà để liên kết dẫn sang trang sản phẩm còn hơn mở một
 * hộp thoại mà bấm xong bị 419.
 */
function layToken() {
    const input = document.querySelector('input[name="_token"]');

    return input ? input.value : null;
}

function dungHop() {
    const el = document.createElement('dialog');

    el.className = 'variant-dialog';
    el.innerHTML = `
        <form method="dialog" class="variant-dialog__close-form">
            <button type="submit" class="variant-dialog__close" aria-label="Đóng">&times;</button>
        </form>
        <h2 class="variant-dialog__title" data-dialog-title></h2>
        <form method="POST" class="product-buy" data-dialog-form>
            <input type="hidden" name="_token" data-dialog-token>
            <input type="hidden" name="product_id" data-dialog-product>
            <span class="text-label d-block mb-2">Chọn quy cách</span>
            <div class="variant-picker mb-3" data-dialog-picker></div>
            <span class="text-label d-block mb-2">Số lượng</span>
            <div class="qty-control mb-4" data-qty>
                <button type="button" class="qty-control__btn" data-qty-minus aria-label="Giảm số lượng">&minus;</button>
                <input type="number" name="quantity" class="qty-control__input"
                       value="1" min="1" max="99" data-qty-input aria-label="Số lượng">
                <button type="button" class="qty-control__btn" data-qty-plus aria-label="Tăng số lượng">+</button>
            </div>
            <!--
                data-add-to-cart / data-buy-now KHÔNG PHẢI trang trí.

                add-to-cart.js nghe sự kiện submit trên document rồi hỏi
                event.submitter có mang data-add-to-cart hay không. Thiếu
                thuộc tính đó thì nút "Thêm vào giỏ" ở đây rơi về gửi
                biểu mẫu kiểu cũ — tải lại cả trang, đúng thứ mà hộp
                thoại này sinh ra để tránh.

                data-dialog-cart / data-dialog-buy chỉ để tệp này tìm lại
                đúng nút mà gắn formaction và đặt tiêu điểm.
            -->
            <div class="variant-dialog__actions">
                <button type="submit" class="btn btn-secondary-brand"
                        data-dialog-cart data-add-to-cart>
                    Thêm vào giỏ
                </button>
                <button type="submit" class="btn btn-primary-brand"
                        data-dialog-buy data-buy-now>
                    Mua ngay
                </button>
            </div>
        </form>
    `;

    document.body.appendChild(el);

    /*
     * Bấm ra vùng nền thì đóng.
     *
     * <dialog> tính cả phần ::backdrop là chính nó, nên so target với
     * chính hộp thoại là đủ để phân biệt "bấm ra ngoài" với "bấm vào
     * nội dung bên trong".
     */
    el.addEventListener('click', (e) => {
        if (e.target === el) el.close();
    });

    return el;
}

/** Đổ dữ liệu của một thẻ sản phẩm vào hộp thoại rồi mở lên. */
function mo(khoi, cheDo, token) {
    let danhSach;

    try {
        danhSach = JSON.parse(khoi.dataset.variants || '[]');
    } catch (e) {
        return false;
    }

    if (!Array.isArray(danhSach) || danhSach.length === 0) return false;

    if (!hop) hop = dungHop();

    /*
     * Tiêu đề nhắc lại VIỆC ĐANG LÀM, không chỉ tên sản phẩm.
     *
     * Vì giờ chỉ còn một nút, tiêu đề là chỗ duy nhất còn nói được khách
     * đang trên đường nào — "Thêm vào giỏ" hay "Mua ngay". Thiếu nó thì
     * hai hộp thoại trông y hệt nhau, chỉ khác chữ trên nút.
     */
    hop.querySelector('[data-dialog-title]').textContent =
        (cheDo === 'buy' ? 'Mua ngay: ' : 'Thêm vào giỏ: ')
        + (khoi.dataset.productName || 'chọn quy cách');
    hop.querySelector('[data-dialog-token]').value = token;
    hop.querySelector('[data-dialog-product]').value = khoi.dataset.productId;

    const form = hop.querySelector('[data-dialog-form]');

    form.action = khoi.dataset.cartUrl;

    /*
     * Nút "Mua ngay" đổi đích bằng formaction, y như ở trang chi tiết —
     * một biểu mẫu, hai đích. Hai biểu mẫu riêng thì ô số lượng và ô
     * quy cách phải nhân đôi, và sớm muộn hai bản lệch nhau.
     */
    hop.querySelector('[data-dialog-buy]').setAttribute('formaction', khoi.dataset.buyUrl);

    /*
     * CHỌN SẴN QUY CÁCH CÒN HÀNG ĐẦU TIÊN, không phải cái đầu tiên.
     *
     * Chọn cứng cái đầu mà nó đã hết hàng thì ô radio bị vô hiệu hoá,
     * trình duyệt không gửi `variant_id` nào cả, và máy chủ từ chối —
     * khách bấm mua mà chẳng hiểu vì sao không được.
     */
    const chonDau = danhSach.findIndex((v) => !v.het);

    hop.querySelector('[data-dialog-picker]').innerHTML = danhSach.map((v, i) => `
        <label class="variant-option">
            <input type="radio" name="variant_id" value="${v.id}" class="visually-hidden"
                   ${i === chonDau ? 'checked' : ''} ${v.het ? 'disabled' : ''}>
            <span class="variant-option__name"></span>
            ${v.gia !== null && v.gia !== undefined
                ? `<span class="variant-option__price">${tienVND(parseFloat(v.gia))}</span>`
                : ''}
            ${v.het ? '<span class="variant-option__price">Hết hàng</span>' : ''}
        </label>
    `).join('');

    /*
     * Tên quy cách gán bằng textContent, KHÔNG nhét thẳng vào chuỗi HTML
     * ở trên. Tên do cửa hàng tự nhập, và một dấu ngoặc nhọn trong đó là
     * đủ để biến nó thành thẻ HTML thật.
     */
    hop.querySelectorAll('.variant-option').forEach((label, i) => {
        label.querySelector('.variant-option__name').textContent = danhSach[i].ten;
    });

    // Mọi quy cách đều hết hàng: không mở hộp rỗng nghĩa.
    if (chonDau === -1) return false;

    hop.querySelector('[data-qty-input]').value = '1';

    hop.showModal();

    /*
     * Đưa con trỏ vào đúng nút khách vừa bấm.
     *
     * Bấm "Mua ngay" ở thẻ mà hộp thoại mở ra với con trỏ ở nút "Thêm
     * vào giỏ" là đổi ý định của họ giữa chừng.
     */
    /*
     * CHỈ HIỆN ĐÚNG CÁI NÚT KHÁCH VỪA BẤM.
     *
     * Hộp này mở ra vì khách đã bấm "Thêm vào giỏ" HOẶC "Mua ngay" — ý
     * định đã rõ, chỉ còn thiếu quy cách. Bày lại cả hai nút là hỏi lại
     * một câu họ vừa trả lời, và mở đường cho một cú bấm nhầm dẫn thẳng
     * sang trang thanh toán.
     *
     * Nút kia bị VÔ HIỆU HOÁ chứ không chỉ ẩn: nút ẩn vẫn có thể được
     * kích hoạt bằng phím Enter khi tiêu điểm rơi vào nó, và trình đọc
     * màn hình vẫn đọc ra.
     */
    const nutCart = hop.querySelector('[data-dialog-cart]');
    const nutBuy = hop.querySelector('[data-dialog-buy]');
    const muaNgay = cheDo === 'buy';

    nutCart.hidden = muaNgay;
    nutCart.disabled = muaNgay;

    nutBuy.hidden = ! muaNgay;
    nutBuy.disabled = ! muaNgay;

    (muaNgay ? nutBuy : nutCart).focus();

    return true;
}

export function initVariantDialog() {
    // Trình duyệt không có <dialog>: để liên kết chạy như thường.
    if (typeof HTMLDialogElement === 'undefined') return;

    const token = layToken();

    if (!token) return;

    /*
     * MỘT trình lắng nghe trên document — cùng lý do với add-to-cart.js:
     * trang danh sách có hàng chục thẻ, và thẻ nạp thêm sau khi đổi bộ
     * lọc vẫn chạy mà không phải gắn lại gì.
     */
    document.addEventListener('click', (e) => {
        const nut = e.target.closest('[data-variant-trigger]');

        if (!nut) return;

        const khoi = nut.closest('[data-variant-choice]');

        if (!khoi) return;

        /*
         * CHỈ chặn cú bấm khi đã mở được hộp thoại.
         *
         * Dữ liệu hỏng, danh sách rỗng, mọi quy cách hết hàng — mo() trả
         * về false và ta KHÔNG gọi preventDefault, để liên kết đưa khách
         * sang trang sản phẩm như bản không có JavaScript. Chặn trước
         * rồi mới phát hiện hỏng là để khách bấm vào một cái nút chết.
         */
        if (mo(khoi, nut.dataset.variantTrigger, token)) {
            e.preventDefault();
        }
    });

    /*
     * Đóng hộp sau khi thêm vào giỏ thành công.
     *
     * add-to-cart.js chặn submit rồi gửi bằng fetch, nên hộp thoại
     * không tự đóng. Nghe chính sự kiện nó phát ra thay vì tự đoán thời
     * gian chờ — đoán thì hoặc đóng sớm khi mạng chậm, hoặc để hộp nằm
     * lại chắn màn hình sau khi đã xong.
     */
    document.addEventListener('cart:added', () => {
        if (hop && hop.open) hop.close();
    });
}
