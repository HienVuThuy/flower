@props([
    'items' => null,
    'personalized' => false,
    'title' => null,
    /*
     * Nhãn cho biết khối này nằm ở trang nào ('home', 'product-detail').
     * Đi kèm mỗi liên kết dưới dạng ?ref=, để đo được gợi ý ở chỗ nào
     * thật sự có người bấm. Xem ProductController::show.
     */
    'source' => 'home',
])

{{--
    Khối gợi ý sản phẩm.

    KHÔNG RENDER GÌ khi không có sản phẩm nào — thà không có khối còn hơn
    có một khối trống với tiêu đề rỗng bên dưới.

    Tiêu đề đổi theo việc gợi ý có thật sự dựa trên hành vi hay không.
    Gọi một danh sách "phổ biến nhất" là "gợi ý riêng cho bạn" thì chỉ
    cần hai người ngồi cạnh nhau mở máy là lộ ngay.
--}}
@if($items && $items->isNotEmpty())

    <section class="section-sm">
        <div class="container-shop">

            <div class="section-header">
                <div>
                    <span class="text-label section-header__eyebrow d-block">
                        {{ $personalized ? 'Dành riêng cho bạn' : 'Khám phá' }}
                    </span>

                    <h2 class="text-h2 section-header__title">
                        {{ $title ?? ($personalized ? 'Gợi ý cho bạn' : 'Được quan tâm nhiều') }}
                    </h2>

                    <p class="mb-0">
                        @if($personalized)
                            Dựa trên những sản phẩm bạn vừa xem và quan tâm.
                        @else
                            Những sản phẩm nhiều người đang xem nhất.
                        @endif
                    </p>
                </div>
            </div>

            <div class="row g-4">
                @foreach($items as $item)
                    <div class="col-6 col-md-3">

                        {{-- Dùng lại thẻ sản phẩm chuẩn — không dựng thẻ riêng
                             cho khối này, nếu không mỗi lần sửa thẻ phải sửa
                             hai nơi. --}}
                        <x-product.card
                            :product="$item['product']"
                            :ref="'goi-y:' . $source" />

                        {{--
                            Lý do đặt DƯỚI thẻ, cỡ chữ nhỏ: nó là chú thích
                            giúp khách hiểu vì sao thấy sản phẩm này, không
                            được tranh chỗ với tên và giá.
                        --}}
                        <p class="reco-reason">{{ $item['reason'] }}</p>

                    </div>
                @endforeach
            </div>

        </div>
    </section>

@endif
