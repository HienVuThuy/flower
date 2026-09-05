/**
 * Tương tác trang chi tiết sản phẩm: gallery, chọn quy cách, số lượng.
 *
 * Tất cả đều là nâng cấp trải nghiệm (progressive enhancement) —
 * nếu JS lỗi, trang vẫn đọc được đầy đủ: ảnh đầu tiên vẫn hiện,
 * giá vẫn đúng, quy cách vẫn liệt kê được.
 */

function initGallery() {
    const gallery = document.querySelector('[data-gallery]');
    if (!gallery) return;

    const main = gallery.querySelector('[data-gallery-main]');
    const thumbs = gallery.querySelectorAll('[data-gallery-thumb]');
    if (!main || !thumbs.length) return;

    thumbs.forEach((thumb) => {
        thumb.addEventListener('click', () => {
            /*
             * ĐỔI CẢ <source>, KHÔNG CHỈ ĐỔI <img src>.
             *
             * Ảnh chính nay nằm trong <picture> để dùng được WebP. Trình
             * duyệt ưu tiên <source> hơn <img src>, nên chỉ đổi `src` thì
             * ảnh KHÔNG đổi — khách bấm ảnh nhỏ mà ảnh lớn đứng yên, và
             * không có lỗi nào để lần ra.
             */
            const source = gallery.querySelector('[data-gallery-source]');

            if (source && thumb.dataset.srcset) {
                source.srcset = thumb.dataset.srcset;
            }

            main.src = thumb.dataset.src;

            thumbs.forEach((t) => {
                t.classList.toggle('is-active', t === thumb);
                t.setAttribute('aria-current', t === thumb ? 'true' : 'false');
            });
        });
    });
}

/**
 * TÔ SÁNG QUY CÁCH ĐANG CHỌN — cho MỌI bảng chọn trên trang.
 *
 * Nghe theo uỷ quyền trên document thay vì gắn vào từng bảng, vì có hai
 * nơi dùng chung kiểu bảng này:
 *
 *   - bảng cố định ở trang chi tiết sản phẩm;
 *   - bảng dựng động trong hộp chọn quy cách mở từ thẻ sản phẩm
 *     (variant-dialog.js).
 *
 * Bảng thứ hai chưa tồn tại lúc trang tải xong, nên cách gắn-từng-bảng
 * sẽ bỏ sót nó — và bỏ sót ở đây nghĩa là khách bấm sang quy cách khác
 * mà khung tô sáng vẫn nằm nguyên chỗ cũ.
 *
 * CSS `.variant-option:has(input:checked)` cũng làm đúng việc này mà
 * không cần JavaScript. Giữ cả hai là có chủ ý: `:has()` lo phần thường
 * ngày, còn đoạn dưới đây giữ cho trình duyệt cũ chưa hỗ trợ `:has()`
 * vẫn thấy được mình đang chọn gì — ô radio thật thì đã bị ẩn đi.
 */
function initVariantHighlight() {
    document.addEventListener('change', (event) => {
        const radio = event.target.closest('.variant-option input[type="radio"]');

        if (!radio) return;

        const picker = radio.closest('.variant-picker');

        if (!picker) return;

        picker.querySelectorAll('.variant-option').forEach((label) => {
            label.classList.toggle('is-selected', label.contains(radio));
        });
    });
}

/**
 * Giá lớn ở đầu trang chi tiết đổi theo quy cách đang chọn.
 *
 * CHỈ ở trang chi tiết: hộp thoại tự hiện giá ngay trên từng ô nên
 * không có ô giá lớn nào để cập nhật.
 */
function initVariantPrice() {
    const picker = document.querySelector('[data-variant-picker]');
    if (!picker) return;

    const options = picker.querySelectorAll('[data-variant]');
    const priceBox = document.querySelector('[data-price-display]');
    if (!priceBox) return;

    // Giữ lại phần giá gốc do server render, để khi chọn variant
    // không có giá riêng thì trả về đúng như ban đầu.
    const defaultHtml = priceBox.innerHTML;
    const fmt = (n) => new Intl.NumberFormat('vi-VN').format(Math.round(n)) + '₫';

    options.forEach((option) => {
        option.addEventListener('change', () => {
            if (option.disabled) return;

            const raw = option.dataset.price;

            if (raw === '' || raw == null) {
                priceBox.innerHTML = defaultHtml;
                return;
            }

            priceBox.innerHTML =
                '<span class="product-price product-price--lg">' +
                '<span class="text-price">' + fmt(parseFloat(raw)) + '</span>' +
                '</span>';
        });
    });
}

function initQuantity() {
    document.querySelectorAll('[data-qty]').forEach((widget) => {
        const input = widget.querySelector('[data-qty-input]');
        const minus = widget.querySelector('[data-qty-minus]');
        const plus = widget.querySelector('[data-qty-plus]');
        if (!input) return;

        const min = parseInt(input.min, 10) || 1;
        const max = parseInt(input.max, 10) || 99;

        const clamp = () => {
            let v = parseInt(input.value, 10);
            if (isNaN(v)) v = min;
            v = Math.min(max, Math.max(min, v));
            input.value = v;

            if (minus) minus.disabled = v <= min;
            if (plus) plus.disabled = v >= max;
        };

        minus?.addEventListener('click', () => {
            input.value = (parseInt(input.value, 10) || min) - 1;
            clamp();
        });

        plus?.addEventListener('click', () => {
            input.value = (parseInt(input.value, 10) || min) + 1;
            clamp();
        });

        input.addEventListener('change', clamp);
        clamp();
    });
}

export function initProductDetail() {
    initGallery();
    initVariantHighlight();
    initVariantPrice();
    initQuantity();
}
