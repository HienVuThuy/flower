/* HỘP CHỌN QUY CÁCH NGAY TRÊN THẺ SẢN PHẨM */

let hop = null;

function tienVND(n) {
    return new Intl.NumberFormat('vi-VN').format(Math.round(n)) + '₫';
}

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

    el.addEventListener('click', (e) => {
        if (e.target === el) el.close();
    });

    return el;
}

function mo(khoi, cheDo, token) {
    let danhSach;

    try {
        danhSach = JSON.parse(khoi.dataset.variants || '[]');
    } catch (e) {
        return false;
    }

    if (!Array.isArray(danhSach) || danhSach.length === 0) return false;

    if (!hop) hop = dungHop();

    hop.querySelector('[data-dialog-title]').textContent =
        (cheDo === 'buy' ? 'Mua ngay: ' : 'Thêm vào giỏ: ')
        + (khoi.dataset.productName || 'chọn quy cách');
    hop.querySelector('[data-dialog-token]').value = token;
    hop.querySelector('[data-dialog-product]').value = khoi.dataset.productId;

    const form = hop.querySelector('[data-dialog-form]');

    form.action = khoi.dataset.cartUrl;

    hop.querySelector('[data-dialog-buy]').setAttribute('formaction', khoi.dataset.buyUrl);

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

    hop.querySelectorAll('.variant-option').forEach((label, i) => {
        label.querySelector('.variant-option__name').textContent = danhSach[i].ten;
    });

    if (chonDau === -1) return false;

    hop.querySelector('[data-qty-input]').value = '1';

    hop.showModal();

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
    if (typeof HTMLDialogElement === 'undefined') return;

    const token = layToken();

    if (!token) return;

    document.addEventListener('click', (e) => {
        const nut = e.target.closest('[data-variant-trigger]');

        if (!nut) return;

        const khoi = nut.closest('[data-variant-choice]');

        if (!khoi) return;

        if (mo(khoi, nut.dataset.variantTrigger, token)) {
            e.preventDefault();
        }
    });

    document.addEventListener('cart:added', () => {
        if (hop && hop.open) hop.close();
    });
}
