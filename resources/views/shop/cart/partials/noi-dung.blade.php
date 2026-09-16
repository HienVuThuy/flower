{{-- RUỘT CỦA TRANG GIỎ HÀNG — tách riêng để VẼ LẠI ĐƯỢC. --}}
@if($cart->isEmpty())

    <x-site.empty-state
        title="Giỏ hàng đang trống"
        text="Chọn vài bó hoa hoặc chậu cây bạn thích, rồi quay lại đây."
    >
        <x-slot:actions>
            <a href="{{ route('shop.products.index') }}" class="btn btn-primary-brand">
                Xem sản phẩm
            </a>
        </x-slot:actions>
    </x-site.empty-state>

@else

    <div class="row g-4">

        <div class="col-lg-8">

            <div class="cart-select">

                <form
                    id="cart-select"
                    method="POST"
                    action="{{ route('shop.cart.select') }}"
                    class="cart-select__form"
                    data-cart-select
                    data-cart-form
                >
                    @csrf

                    <label class="cart-select__all">
                        <input
                            type="checkbox"
                            class="form-check-input"
                            data-cart-pick-all
                            @checked($selectedCount === $cart->items->count())
                        >
                        <span>Chọn tất cả</span>
                    </label>

                    <span class="cart-select__count">
                        Đang chọn <strong>{{ $selectedCount }}</strong>/{{ $cart->items->count() }} món
                    </span>

                    <button type="submit" class="btn btn-ghost btn-sm" data-cart-select-submit>
                        Cập nhật lựa chọn
                    </button>
                </form>

                <form
                    method="POST"
                    action="{{ route('shop.cart.clear') }}"
                    class="cart-select__clear"
                    onsubmit="return confirm('Xoá tất cả sản phẩm khỏi giỏ hàng? Thao tác này không hoàn tác được.');"
                >
                    @csrf
                    @method('DELETE')

                    <button type="submit" class="btn btn-ghost btn-sm cart-select__clear-btn">
                        <x-site.icon name="trash" />
                        Xoá tất cả
                    </button>
                </form>

            </div>

            <div class="cart-lines">
                @foreach($cart->items as $item)
                    <x-cart.line :item="$item"
                                 :qua="($quaTheoDong ?? [])[\App\Services\Gift\GiftResolver::khoaDong((int) $item->product_id, $item->product_variant_id ? (int) $item->product_variant_id : null)] ?? []" />
                @endforeach
            </div>

            <x-product.cross-sell
                :items="$accessories"
                title="Có thể bạn cần thêm"
                note="Phụ kiện hợp với hàng đang có trong giỏ."
                source="cart" />

        </div>

        <div class="col-lg-4">
            <x-cart.summary :basket="$basket" :show-action="true" />
        </div>

    </div>

@endif
