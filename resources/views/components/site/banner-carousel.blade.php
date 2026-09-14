@php
    /*
     * GỘP HAI BANNER LÀM MỘT.
     *
     * Trước đây "Chương trình đang diễn ra" và "Sự kiện & số lượng lớn"
     * là hai khối to bằng nhau, xếp chồng nhau trên trang chủ — hai lời
     * mời cùng cỡ đặt cạnh nhau thì không lời nào nổi bật, mà trang lại
     * dài thêm gấp đôi.
     *
     * Nay chúng luân phiên trong cùng một khung, có chấm điều hướng để
     * khách quay lại xem cái vừa trôi qua.
     *
     * Nội dung chiến dịch lấy TỪ DATABASE (bảng promotions) — sang mùa
     * sau admin chỉ cần tạo chương trình mới, không phải sửa code.
     *
     * ============================================================
     * TỐI ĐA BA CHƯƠNG TRÌNH ĐANG CHẠY THẬT, không chỉ một.
     *
     * Trước đây khung chỉ có chỗ cho chương trình ưu tiên cao nhất; cửa hàng
     * chạy song song "Tuần lễ sen đá" và "Giờ vàng" thì cái thứ hai không
     * có mặt ở đâu trên trang chủ. "Đang chạy" nghĩa là qua isRunning() —
     * đúng ngày, đúng thứ, đúng khung giờ — xem ActivePromotionProvider.
     * Không có chương trình nào thì khung chỉ còn slide sự kiện và JS tự
     * bỏ qua phần luân phiên.
     */
    $cacChuongTrinh = app(\App\Services\Promotion\ActivePromotionProvider::class)->dangChay(3);
@endphp

<section class="section-sm">
    <div class="container-shop">

        <div class="banner-rotator" data-banner-rotator data-interval="7000">

            <div class="banner-rotator__track">

                {{-- ---------- Slide: các chương trình khuyến mại ---------- --}}
                @foreach($cacChuongTrinh as $promotion)
                    @php
                        $mucGiam = $promotion->headlineDiscount();
                        $conLai = $promotion->endsInText();
                    @endphp
                    <article class="banner-rotator__slide {{ $loop->first ? 'is-active' : '' }}"
                             data-banner-slide
                             aria-roledescription="slide"
                             aria-label="Chương trình khuyến mại">

                        <div class="campaign-banner {{ $promotion->banner ? 'campaign-banner--has-image' : '' }}">

                            @if($promotion->banner)
                                <img src="{{ asset('storage/' . $promotion->banner) }}"
                                     alt=""
                                     class="campaign-banner__image"
                                     loading="lazy">
                            @endif

                            <div class="campaign-banner__content">

                                <span class="text-label campaign-banner__eyebrow d-block mb-2">
                                    Chương trình đang diễn ra
                                </span>

                                <h2 class="text-h2 mb-2">{{ $promotion->name }}</h2>

                                {{--
                                    MỨC GIẢM THẬT, tính từ sản phẩm đã gắn — nói "được
                                    gì" trước khi mời bấm. Không tính được thì không in
                                    một câu "ưu đãi hấp dẫn" chung chung.
                                --}}
                                @if($mucGiam)
                                    <p class="campaign-banner__deal mb-2"><strong>{{ $mucGiam }}</strong></p>
                                @endif

                                @if($promotion->short_description)
                                    <p class="mb-3">{{ $promotion->short_description }}</p>
                                @endif

                                <div class="d-flex align-items-center gap-3 flex-wrap">
                                    <a href="{{ route('shop.products.index', ['promotion' => $promotion->slug]) }}"
                                       class="btn btn-primary-brand">
                                        Xem {{ $promotion->products_count }} sản phẩm ưu đãi
                                    </a>

                                    {{-- Thời hạn THẬT từ ends_at; không có ngày kết thúc thì không giục. --}}
                                    @if($conLai)
                                        <span class="campaign-banner__countdown">{{ ucfirst($conLai) }}</span>
                                    @endif
                                </div>

                            </div>

                        </div>

                    </article>
                @endforeach

                {{-- ---------- Slide: sự kiện & số lượng lớn ---------- --}}
                {{--
                    Giữ .event-banner CHỨ KHÔNG dùng .campaign-banner: đây là
                    lời mời để lại yêu cầu báo giá, không phải khuyến mại.
                    Trước đây mượn class của campaign nên artwork mùa vụ dành
                    cho campaign đè cả lên nút bấm ở đây.
                --}}
                <article class="banner-rotator__slide {{ $cacChuongTrinh->isEmpty() ? 'is-active' : '' }}"
                         data-banner-slide
                         aria-roledescription="slide"
                         aria-label="Sự kiện và số lượng lớn">

                    <div class="event-banner">
                        <div class="event-banner__content">
                            <span class="text-label event-banner__eyebrow d-block mb-2">Sự kiện &amp; số lượng lớn</span>
                            <h2 class="text-h2 mb-2">Bạn đang chuẩn bị một sự kiện?</h2>
                            <p class="mb-3">Đám cưới, khai trương, hội nghị hay quà tặng doanh nghiệp — để lại thông tin, chúng tôi sẽ liên hệ tư vấn và báo giá.</p>
                            <a href="{{ route('shop.bulk-inquiry.create') }}" class="btn btn-primary-brand">
                                Nhận tư vấn &amp; báo giá
                            </a>
                        </div>
                    </div>

                </article>

            </div>

            {{--
                CHẤM ĐIỀU HƯỚNG hình thoi — một chấm cho mỗi slide.

                Chỉ in ra khi có từ 2 slide trở lên — một chấm đơn độc
                không điều hướng được đi đâu cả.

                Là <button> thật để bàn phím dùng được; JS bỏ thuộc tính
                hidden, nên tắt JS thì không có nút bấm vô tác dụng.
            --}}
            @if($cacChuongTrinh->isNotEmpty())
                <div class="banner-rotator__dots" data-banner-dots hidden role="tablist" aria-label="Chọn banner">
                    @foreach($cacChuongTrinh as $promotion)
                        <button type="button" class="banner-rotator__dot {{ $loop->first ? 'is-active' : '' }}"
                                data-banner-dot="{{ $loop->index }}"
                                role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                                aria-label="{{ $promotion->name }}"></button>
                    @endforeach
                    <button type="button" class="banner-rotator__dot" data-banner-dot="{{ $cacChuongTrinh->count() }}"
                            role="tab" aria-selected="false" aria-label="Sự kiện và số lượng lớn"></button>
                </div>
            @endif

        </div>

    </div>
</section>
