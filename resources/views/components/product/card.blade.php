@props(['product', 'ref' => null, 'showCategory' => true])

@php
    $price = $product->price();

    /*
     * ĐƯỜNG DẪN TÍNH MỘT LẦN, dùng cho cả ba liên kết trong thẻ.
     *
     * `ref` là nhãn cho biết khách bấm vào thẻ này TỪ ĐÂU (khối gợi ý ở
     * trang chủ, khối gợi ý ở trang chi tiết...). ProductController::show
     * ghi nhãn đó vào sự kiện xem sản phẩm, nhờ vậy sau này trả lời được
     * câu "gợi ý có ai bấm không" — trước đây không có cách nào biết.
     *
     * Không có ref thì đường dẫn sạch trơn như cũ: mọi nơi khác đang gọi
     * component product.card không phải sửa gì.
     */
    $url = $ref
        ? route('shop.products.show', [$product, 'ref' => $ref])
        : route('shop.products.show', $product);

    $outOfStock = $product->status === 'out_of_stock'
        || ($product->track_inventory && ! $product->inStock());
@endphp

<div class="product-card">

    <div class="product-card__media">

        @if($price->isDiscounted())
            <span class="media-tag media-tag--sale">−{{ $price->discountPercent() }}%</span>
        @elseif($outOfStock)
            <span class="media-tag media-tag--sold-out">Hết hàng</span>
        @endif

        <a href="{{ $url }}" aria-label="{{ $product->name }}">
            @if($product->main_image)
                {{--
                    Dùng x-site.image thay cho <img> trần: nó tự chọn bản
                    WebP đúng cỡ và kèm width/height để trang không nhảy.

                    ĐO ĐƯỢC: ảnh gốc trung bình 882px bề ngang, hiển thị ở
                    117–300px trong thẻ này. Bản 400px nhẹ hơn 75% (5,59 MB
                    → 1,39 MB cho cả 52 ảnh).
                --}}
                <x-site.image
                    :path="$product->main_image"
                    :alt="$product->name"
                    class="product-card__image"
                />
            @else
                <div class="product-card__placeholder">
                    <x-site.leaf-placeholder />
                </div>
            @endif
        </a>

        {{--
            Nút tim đặt trên ảnh. Đặt NGOÀI thẻ <a> bao ảnh: form lồng
            trong link là HTML sai và trình duyệt xử lý mỗi nơi một kiểu.
        --}}
        <div class="product-card__wish">
            <x-product.wishlist-button
                :product="$product"
                :active="$product->isWishlisted()"
                :compact="true" />
        </div>

        <div class="product-card__quick">
            <a href="{{ $url }}" class="product-card__quick-pill">
                Xem sản phẩm
            </a>
        </div>

    </div>

    {{--
        DÒNG DANH MỤC — ẩn đi khi khách đang đứng TRONG chính danh mục đó.

        Trên trang /danh-muc/cay-bonsai, tiêu đề đã là "Cây bonsai" và cả
        12 thẻ bên dưới đều viết "Cây bonsai" thêm một lần nữa. Nó không
        cho biết thêm gì — khách vừa tự bấm vào danh mục ấy — mà chiếm
        đúng chỗ đáng lẽ dành cho tên sản phẩm.

        Ở mọi nơi khác (trang chủ, tìm kiếm, gợi ý) thì dòng này CẦN, vì
        ở đó các thẻ trộn nhiều danh mục.
    --}}
    @if($showCategory)
        <div class="product-card__category">{{ $product->category->name }}</div>
    @endif

    <a href="{{ $url }}" class="product-card__title">
        {{ $product->name }}
    </a>

    {{--
        Chỉ hiện khi sản phẩm đã có đánh giá. Dùng rating_avg/rating_count
        do controller nạp sẵn bằng withAvg/withCount; danh sách nào chưa
        nạp thì ratingCount() tự truy vấn — đúng nhưng chậm, nên controller
        mới là chỗ phải nhớ.
    --}}
    @if($product->ratingCount() > 0)
        <div class="product-card__rating">
            <x-product.rating-stars
                :value="$product->ratingAverage()"
                :count="$product->ratingCount()" />
        </div>
    @endif

    <div class="product-card__price-row">
        <x-product.price :product="$product" />
    </div>

    <div class="product-card__actions">
        <x-product.actions :product="$product" :compact="true" />
    </div>

</div>
