@props(['product', 'ref' => null, 'showCategory' => true])

@php
    $price = $product->price();

    $url = $ref
        ? route('shop.products.show', [$product, 'ref' => $ref])
        : route('shop.products.show', $product);

    $outOfStock = $product->status === 'out_of_stock'
        || ($product->track_inventory && ! $product->inStock());

    $coQua = isset(\App\Services\Gift\GiftResolver::sanPhamCoQua()[$product->id]);
@endphp

<div class="product-card">

    <div class="product-card__media">

        @if($price->isDiscounted() || $product->is_featured || $coQua || $outOfStock)
            <div class="media-tags">
                @if($outOfStock)
                    <span class="media-tag media-tag--sold-out">Hết hàng</span>
                @endif
                @if($price->isDiscounted())
                    <span class="media-tag media-tag--sale">Giảm {{ $price->discountPercent() }}%</span>
                @endif
                @if($product->is_featured)
                    <span class="media-tag media-tag--noi-bat"><x-site.icon name="star-fill" />Nổi bật</span>
                @endif
                @if($coQua)
                    <span class="media-tag media-tag--qua"><x-site.icon name="gift" />Có quà</span>
                @endif
            </div>
        @endif

        <a href="{{ $url }}" aria-label="{{ $product->name }}">
            @if($product->main_image)
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

    @if($showCategory)
        <div class="product-card__category">{{ $product->category->name }}</div>
    @endif

    <a href="{{ $url }}" class="product-card__title">
        {{ $product->name }}
    </a>

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
