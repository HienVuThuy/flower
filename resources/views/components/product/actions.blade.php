@props([
    'product',
    'compact' => false,
    'withQuantity' => false,
    'variants' => null,
    'phoBien' => null,
])

@php
    $cartEnabled = (bool) config('features.cart');
    $price = $product->price();

    $quoteOnly = $price->isContactForPrice();

    $outOfStock = $product->status === 'out_of_stock'
        || ! $product->isPurchasable();

    $inquiryUrl = route('shop.bulk-inquiry.create', ['product' => $product->slug]);

    $sizeClass = $compact ? 'btn-sm' : 'btn-lg';

    if ($variants === null && ! $product->relationLoaded('variants')) {
        $product->load('variants');
    }

    $activeVariants = $variants
        ?? $product->variants->where('is_active', true)->values();

    $mustPickVariant = $compact && $activeVariants->isNotEmpty();

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

    <form method="POST" action="{{ route('shop.cart.store') }}" class="product-buy">
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->id }}">

        @if($withQuantity && $activeVariants->isNotEmpty())
            <div class="mb-4" id="chon-quy-cach">
                <span class="text-label d-block mb-2">Chọn quy cách</span>

                @php
                    $conMua = fn ($v) => ! ($v->track_inventory && ! $v->inStock());
                    $macDinh = $activeVariants->first(fn ($v) => $phoBien !== null && $v->id === (int) $phoBien && $conMua($v))
                        ?? $activeVariants->first($conMua);
                    $idMacDinh = $macDinh?->id;
                @endphp

                <div class="variant-picker" data-variant-picker>
                    @foreach($activeVariants as $i => $variant)
                        @php($variantOut = $variant->track_inventory && ! $variant->inStock())

                        <label class="variant-option {{ $variant->id === $idMacDinh ? 'is-selected' : '' }}">
                            <input
                                type="radio"
                                name="variant_id"
                                value="{{ $variant->id }}"
                                class="visually-hidden"
                                data-variant
                                data-price="{{ $variant->price }}"
                                @checked($variant->id === $idMacDinh)
                                @disabled($variantOut)
                            >
                            <span class="variant-option__name">{{ $variant->name }}</span>

                            @if($phoBien !== null && $variant->id === (int) $phoBien)
                                <span class="variant-option__tag">Phổ biến nhất</span>
                            @endif

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
