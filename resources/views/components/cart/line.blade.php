@props(['item'])

@php
    $product = $item->product;
    $stock = $item->availableStock();
    // Số lượng đang giữ vượt quá tồn kho: xảy ra khi admin giảm tồn kho
    // sau lúc khách bỏ vào giỏ. Phải báo chứ không im lặng.
    $overStock = $stock !== null && $item->quantity > $stock;
@endphp

<div class="cart-line {{ $item->is_selected === false ? 'is-unselected' : '' }}">

    {{--
        Ô CHỌN — thuộc biểu mẫu `cart-select` nằm NGOÀI dòng này.

        Dòng giỏ hàng đã có hai <form> riêng (sửa số lượng, xoá), mà HTML
        không cho lồng form. Thuộc tính form="" gắn ô này vào biểu mẫu ở
        ngoài — đúng cách đã dùng cho ô nhập mã giảm giá ở trang thanh
        toán.

        Không có JavaScript thì khách tích xong bấm nút "Cập nhật lựa
        chọn"; có JavaScript thì tự gửi ngay khi tích.
    --}}
    <div class="cart-line__pick">
        <input
            type="checkbox"
            form="cart-select"
            name="selected[]"
            value="{{ $item->id }}"
            id="pick-{{ $item->id }}"
            class="form-check-input"
            @checked($item->is_selected !== false)
            data-cart-pick
        >
        <label class="visually-hidden" for="pick-{{ $item->id }}">
            Chọn {{ $product->name }} để thanh toán
        </label>
    </div>

    <a href="{{ route('shop.products.show', $product) }}" class="cart-line__media">
        @if($product->main_image)
            <img src="{{ asset('storage/' . $product->main_image) }}" alt="{{ $product->name }}" loading="lazy">
        @else
            <x-site.leaf-placeholder />
        @endif
    </a>

    <div class="cart-line__body">

        <a href="{{ route('shop.products.show', $product) }}" class="cart-line__name">
            {{ $product->name }}
        </a>

        @if($item->variant)
            <div class="cart-line__variant">{{ $item->variant->name }}</div>
        @endif

        <div class="cart-line__unit">
            <x-site.money :amount="(float) $item->unitPrice()" /> / sản phẩm
        </div>

        @if($overStock)
            <p class="cart-line__warning">
                Chỉ còn {{ $stock }} sản phẩm — vui lòng giảm số lượng.
            </p>
        @endif

    </div>

    {{--
        data-cart-form: cart-live.js gửi biểu mẫu này bằng fetch rồi thay
        lại cả khối giỏ bằng HTML máy chủ vẽ. Không có JavaScript thì nó
        vẫn là biểu mẫu POST bình thường và trang tải lại như trước.
    --}}
    <form method="POST" action="{{ route('shop.cart.update', $item) }}" class="cart-line__qty" data-cart-form>
        @csrf
        @method('PATCH')

        <label class="visually-hidden" for="qty-{{ $item->id }}">Số lượng</label>

        <input
            type="number"
            id="qty-{{ $item->id }}"
            name="quantity"
            value="{{ $item->quantity }}"
            min="1"
            max="{{ $stock ?? 99 }}"
            class="form-control form-control-sm"
        >

        <button type="submit" class="btn btn-ghost btn-sm">Cập nhật</button>
    </form>

    <div class="cart-line__total">
        <x-site.money :amount="(float) $item->lineTotal()" />
    </div>

    <form method="POST" action="{{ route('shop.cart.destroy', $item) }}" data-cart-form>
        @csrf
        @method('DELETE')
        <button type="submit" class="cart-line__remove" aria-label="Xoá {{ $product->name }} khỏi giỏ">
            <x-site.icon name="x-circle" />
        </button>
    </form>

</div>
