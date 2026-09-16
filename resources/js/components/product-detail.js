/** Tương tác trang chi tiết sản phẩm: gallery, chọn quy cách, số lượng. */

function initGallery() {
    const gallery = document.querySelector('[data-gallery]');
    if (!gallery) return;

    const main = gallery.querySelector('[data-gallery-main]');
    const thumbs = gallery.querySelectorAll('[data-gallery-thumb]');
    if (!main || !thumbs.length) return;

    thumbs.forEach((thumb) => {
        thumb.addEventListener('click', () => {
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

function initVariantPrice() {
    const picker = document.querySelector('[data-variant-picker]');
    if (!picker) return;

    const options = picker.querySelectorAll('[data-variant]');
    const priceBox = document.querySelector('[data-price-display]');
    if (!priceBox) return;

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
