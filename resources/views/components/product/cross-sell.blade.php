@props([
    'items',
    'title' => 'Mua kèm',
    'note' => null,
    'source' => 'cross-sell',
])

{{--
    GỢI Ý MUA KÈM (phụ kiện).

    KHÁC HẲN "Gợi ý cho bạn": khối kia đoán thứ khách CÓ THỂ thích thay
    cho món đang xem. Khối này gợi thứ dùng CÙNG món đó — mua cây chậu
    thì cần đĩa hứng nước, mua bó hoa thì cần gói dưỡng hoa. Là bổ sung
    chứ không phải thay thế, nên hình thức cũng phải khác: hàng ngang
    gọn, ảnh nhỏ, không tranh chỗ với hàng chính.

    Không có gì để gợi thì KHÔNG render — thà thiếu một khối còn hơn có
    một khối trống mang tiêu đề "Mua kèm".
--}}
@if($items->isNotEmpty())

    <div class="cross-sell">

        <div class="cross-sell__head">
            <h2 class="text-h4 mb-0">{{ $title }}</h2>
            @if($note)
                <p class="cross-sell__note">{{ $note }}</p>
            @endif
        </div>

        <div class="cross-sell__list">
            @foreach($items as $item)
                @php($price = $item->price())

                <div class="cross-sell__item">

                    <a href="{{ route('shop.products.show', [$item, 'ref' => 'mua-kem:'.$source]) }}"
                       class="cross-sell__media" aria-label="{{ $item->name }}">
                        @if($item->main_image)
                            <img src="{{ asset('storage/'.$item->main_image) }}"
                                 alt="" loading="lazy">
                        @else
                            {{-- Phụ kiện thường chưa có ảnh chụp. Dùng đúng
                                 hình giữ chỗ mà thẻ sản phẩm đang dùng, để
                                 không sinh ra một kiểu "ảnh trống" thứ hai. --}}
                            <x-site.leaf-placeholder />
                        @endif
                    </a>

                    <div class="cross-sell__body">
                        <a href="{{ route('shop.products.show', [$item, 'ref' => 'mua-kem:'.$source]) }}"
                           class="cross-sell__name">{{ $item->name }}</a>

                        <p class="cross-sell__desc">{{ $item->short_description }}</p>

                        <span class="cross-sell__price">
                            @if($price->finalPrice === null)
                                Liên hệ
                            @else
                                <x-site.money :amount="(float) $price->finalPrice" />
                            @endif
                        </span>
                    </div>

                    {{--
                        Nút thêm thẳng vào giỏ, không bắt mở trang chi tiết.

                        Phụ kiện là món phụ, giá nhỏ, khách đã biết mình cần
                        gì — bắt họ rời trang giỏ hàng để xem chi tiết một cái
                        đĩa lót chậu là đủ phiền để họ bỏ luôn.

                        Chỉ hiện khi module giỏ hàng đang bật; tắt cờ thì
                        route không tồn tại và nút sẽ là nút giả.
                    --}}
                    @if(config('features.cart') && $item->inStock())
                        {{-- class="product-buy" + data-add-to-cart: đủ để
                             add-to-cart.js nhận ra và thêm không tải lại
                             trang. Không có JavaScript thì vẫn là một biểu
                             mẫu bình thường. --}}
                        <form method="POST" action="{{ route('shop.cart.store') }}"
                              class="cross-sell__action product-buy">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $item->id }}">
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" class="btn btn-secondary-brand btn-sm"
                                    data-add-to-cart="{{ $item->id }}">Thêm</button>
                        </form>
                    @endif

                </div>
            @endforeach
        </div>

    </div>

@endif
