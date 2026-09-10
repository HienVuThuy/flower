@props([
    'product',
    'compact' => false,
    // Trang chi tiết truyền true để hiện ô chọn số lượng và quy cách
    // NGAY TRONG form mua hàng. Thẻ sản phẩm ở danh sách thì không.
    'withQuantity' => false,
    'variants' => null,
])

@php
    $cartEnabled = (bool) config('features.cart');
    $price = $product->price();

    // Sản phẩm chưa đặt giá (hoa sự kiện, cây cỡ lớn...) thì không
    // bán trực tiếp — chỉ nhận yêu cầu báo giá.
    $quoteOnly = $price->isContactForPrice();

    /*
     * HẾT HÀNG PHẢI XÉT CẢ QUY CÁCH.
     *
     * `inStock()` chỉ nhìn cột tồn kho của chính sản phẩm — với hàng có
     * quy cách thì đó không phải thứ khách mua. Mọi quy cách bán hết mà
     * cột kia còn số dương thì trang hiện "Còn hàng", nút vẫn sáng, còn
     * bảng chọn quy cách thì mọi ô đều bị vô hiệu hoá; khách bấm mãi
     * không được và không có gì giải thích. Xem Product::isPurchasable().
     */
    $outOfStock = $product->status === 'out_of_stock'
        || ! $product->isPurchasable();

    $inquiryUrl = route('shop.bulk-inquiry.create', ['product' => $product->slug]);

    $sizeClass = $compact ? 'btn-sm' : 'btn-lg';

    /*
     * NẠP QUY CÁCH KHI CHƯA CÓ — KHÔNG ĐƯỢC COI NHƯ "KHÔNG CÓ".
     *
     * LỖI ĐÃ SỬA: bản trước trả về collect() rỗng khi quan hệ chưa nạp,
     * nên component tưởng sản phẩm không có quy cách và dựng nút gửi
     * thẳng biểu mẫu. Biểu mẫu đó thiếu `variant_id`, và CartService từ
     * chối — mọi cú bấm đều báo "vui lòng chọn quy cách trước khi mua".
     *
     * Nghĩa là BẤT KỲ trang nào quên `->with('variants')` đều dựng ra
     * một nút chắc chắn hỏng, âm thầm. Đo được ở khối "Gợi ý cho bạn":
     * RecommendationService nạp category, promotions, traits — và quên
     * đúng cái này.
     *
     * KHÔNG thêm truy vấn nào: `isPurchasable()` ngay bên trên đã tự
     * truy vấn quan hệ này khi nó chưa nạp, rồi vứt kết quả đi. Nạp một
     * lần ở đây là ÍT truy vấn hơn bản cũ, không phải nhiều hơn.
     *
     * Nạp sẵn ở controller/service vẫn nên làm — nhưng từ nay là để
     * tránh N+1, không còn là điều kiện để nút chạy đúng.
     */
    if ($variants === null && ! $product->relationLoaded('variants')) {
        $product->load('variants');
    }

    $activeVariants = $variants
        ?? $product->variants->where('is_active', true)->values();

    /*
     * Có quy cách thì KHÔNG mua được nếu chưa chọn — CartService chặn
     * thẳng. Nên thẻ sản phẩm phải đưa khách qua bước chọn, chứ không
     * gửi thẳng một biểu mẫu chắc chắn bị từ chối.
     */
    $mustPickVariant = $compact && $activeVariants->isNotEmpty();

    /*
     * Dữ liệu cho hộp chọn quy cách dựng bằng JavaScript.
     *
     * Nhúng thẳng vào thuộc tính data thay vì gọi thêm một endpoint:
     * dữ liệu đã có sẵn trong tay khi dựng trang, và một endpoint nữa
     * là thêm một nơi phải kiểm quyền, thêm một nơi có thể lệch.
     */
    $variantPayload = $mustPickVariant
        ? $activeVariants->map(fn ($v) => [
            'id' => $v->id,
            'ten' => $v->name,
            'gia' => $v->price,
            'het' => $v->track_inventory && ! $v->inStock(),
        ])->values()
        : null;
@endphp

@if($quoteOnly)

    <div class="product-actions {{ $compact ? 'product-actions--compact' : '' }}">
        {{-- Chỉ một CTA duy nhất: rõ ràng là hàng cần tư vấn/báo giá --}}
        <a href="{{ $inquiryUrl }}" class="btn btn-primary-brand {{ $sizeClass }}">
            Yêu cầu báo giá
        </a>
    </div>

@elseif($outOfStock)

    <div class="product-actions {{ $compact ? 'product-actions--compact' : '' }}">
        <button type="button" class="btn btn-secondary-brand {{ $sizeClass }}" disabled>
            Tạm hết hàng
        </button>

        @unless($compact)
            <a href="{{ $inquiryUrl }}" class="btn btn-ghost">
                Liên hệ đặt trước
            </a>
        @endunless
    </div>

@elseif($cartEnabled)

    {{--
        MỘT form, HAI nút submit.

        "Mua ngay" đổi đích bằng thuộc tính formaction thay vì dùng form
        thứ hai. Nhờ vậy ô số lượng và quy cách nằm chung một form và áp
        cho cả hai nút — trước đây hai form riêng khiến ô số lượng ở
        ngoài, khách chọn 3 nhưng vẫn chỉ thêm được 1.

        Dùng form thật chứ không gọi JS: nút vẫn y nguyên kiểu dáng
        nhưng hoạt động cả khi JavaScript lỗi. Các thuộc tính data-*
        giữ lại để sau này nâng cấp thành thêm vào giỏ không tải lại trang.
    --}}
    <form method="POST" action="{{ route('shop.cart.store') }}" class="product-buy">
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->id }}">

        @if($withQuantity && $activeVariants->isNotEmpty())
            {{-- id để thẻ sản phẩm ở trang danh sách nhảy thẳng tới đây --}}
            <div class="mb-4" id="chon-quy-cach">
                <span class="text-label d-block mb-2">Chọn quy cách</span>

                <div class="variant-picker" data-variant-picker>
                    @foreach($activeVariants as $i => $variant)
                        @php($variantOut = $variant->track_inventory && ! $variant->inStock())

                        <label class="variant-option {{ $i === 0 ? 'is-selected' : '' }}">
                            <input
                                type="radio"
                                name="variant_id"
                                value="{{ $variant->id }}"
                                class="visually-hidden"
                                data-variant
                                data-price="{{ $variant->price }}"
                                @checked($i === 0)
                                @disabled($variantOut)
                            >
                            <span class="variant-option__name">{{ $variant->name }}</span>

                            @if($variant->price !== null)
                                <span class="variant-option__price">
                                    <x-site.money :amount="$variant->price" />
                                </span>
                            @endif

                            @if($variantOut)
                                <span class="variant-option__price">Hết hàng</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        @if($withQuantity)
            <div class="mb-4">
                <span class="text-label d-block mb-2">Số lượng</span>

                <div class="qty-control" data-qty>
                    <button type="button" class="qty-control__btn" data-qty-minus aria-label="Giảm số lượng">&minus;</button>
                    <input
                        type="number"
                        name="quantity"
                        class="qty-control__input"
                        value="1"
                        min="1"
                        max="99"
                        data-qty-input
                        aria-label="Số lượng"
                    >
                    <button type="button" class="qty-control__btn" data-qty-plus aria-label="Tăng số lượng">+</button>
                </div>
            </div>
        @else
            <input type="hidden" name="quantity" value="1">
        @endif

        <div class="product-actions {{ $compact ? 'product-actions--compact' : '' }}"
            @if($mustPickVariant)
                {{--
                    THẺ SẢN PHẨM CỦA HÀNG CÓ QUY CÁCH.

                    Hai nút dưới đây là LIÊN KẾT chứ không phải nút gửi
                    biểu mẫu, và đó là chủ ý:

                      - Không có JavaScript: bấm là sang trang sản phẩm,
                        ngay tại bảng chọn quy cách. Đúng nơi cần tới,
                        vì không chọn thì không mua được.
                      - Có JavaScript: variant-dialog.js chặn cú bấm và
                        mở hộp chọn ngay tại chỗ, không rời trang.

                    Nếu để nguyên là nút gửi biểu mẫu thì mỗi cú bấm là
                    một vòng đi–về máy chủ chỉ để nhận lời từ chối "vui
                    lòng chọn quy cách" — đúng về mặt dữ liệu nhưng vô
                    ích với người bấm.

                    CHỮ TRÊN NÚT GIỮ NGUYÊN "Thêm vào giỏ" và "Mua ngay".
                    Đổi thành "Xem chi tiết" là nói sai việc: khách vẫn
                    đang mua hàng, chỉ là còn một lựa chọn phải nêu.
                --}}
                data-variant-choice
                data-product-id="{{ $product->id }}"
                data-product-name="{{ $product->name }}"
                data-variants="{{ json_encode($variantPayload, JSON_UNESCAPED_UNICODE) }}"
                data-cart-url="{{ route('shop.cart.store') }}"
                data-buy-url="{{ route('shop.cart.buy-now') }}"
            @endif
        >

            @if($mustPickVariant)

                <a href="{{ route('shop.products.show', $product) }}#chon-quy-cach"
                   class="btn btn-secondary-brand {{ $sizeClass }}"
                   data-variant-trigger="cart">
                    <x-site.icon name="bag" />
                    Thêm vào giỏ
                </a>

                <a href="{{ route('shop.products.show', $product) }}#chon-quy-cach"
                   class="btn btn-primary-brand {{ $sizeClass }}"
                   data-variant-trigger="buy">
                    Mua ngay
                </a>

            @else

                <button
                    type="submit"
                    class="btn btn-secondary-brand {{ $sizeClass }}"
                    data-add-to-cart="{{ $product->id }}"
                >
                    <x-site.icon name="bag" />
                    Thêm vào giỏ
                </button>

                <button
                    type="submit"
                    formaction="{{ route('shop.cart.buy-now') }}"
                    class="btn btn-primary-brand {{ $sizeClass }}"
                    data-buy-now="{{ $product->id }}"
                >
                    Mua ngay
                </button>

            @endif

            @unless($compact)
                <a href="{{ $inquiryUrl }}" class="btn btn-ghost">
                    Mua số lượng lớn?
                </a>
            @endunless

        </div>
    </form>

@else

    {{--
        Cờ features.cart đang tắt: vẫn hiện đủ nút nhưng vô hiệu hoá.
        Cố ý KHÔNG thay bằng "Xem chi tiết" — xem giải thích trong
        config/features.php.
    --}}
    <div class="product-actions {{ $compact ? 'product-actions--compact' : '' }}">

        <button type="button" class="btn btn-secondary-brand {{ $sizeClass }}" disabled
                title="Giỏ hàng đang được hoàn thiện">
            <x-site.icon name="bag" />
            Thêm vào giỏ
        </button>

        <button type="button" class="btn btn-primary-brand {{ $sizeClass }}" disabled
                title="Thanh toán đang được hoàn thiện">
            Mua ngay
        </button>

        @unless($compact)
            <a href="{{ $inquiryUrl }}" class="btn btn-ghost">
                Mua số lượng lớn?
            </a>
        @endunless

    </div>

    @unless($compact)
        <p class="product-actions__note">
            <x-site.icon name="megaphone" />
            Tính năng giỏ hàng và thanh toán đang được hoàn thiện.
            Trong lúc chờ, bạn có thể
            <a href="{{ $inquiryUrl }}">gửi yêu cầu tư vấn</a>
            để được hỗ trợ đặt hàng.
        </p>
    @endunless

@endif
