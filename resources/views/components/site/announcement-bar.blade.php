@php
    $promotion = app(\App\Services\Promotion\ActivePromotionProvider::class)->featured();
@endphp

@if($promotion)
    @php
        $deal = $promotion->headlineDiscount();
        $endsIn = $promotion->endsInText();
        $window = $promotion->scheduleText();

        /*
         * Khoá nhận dạng để nhớ khách đã đóng thanh này chưa.
         *
         * Gồm slug VÀ ngày kết thúc: chương trình được gia hạn hay đổi
         * nội dung là khoá đổi theo, nên thanh hiện lại. Chỉ dùng slug
         * thì gia hạn tới Tết mà khách đã đóng từ Giáng sinh sẽ không bao
         * giờ thấy lại.
         */
        $key = $promotion->slug.'|'.($promotion->ends_at?->timestamp ?? 'mo');
    @endphp

    {{--
        THANH THÔNG BÁO KHUYẾN MẠI.

        Trước đây chỗ này chỉ in TÊN chương trình ("Giáng sinh an lành –
        Ưu đãi ngập tràn") kèm "còn 7 ngày". Nghe hay nhưng không nói gì:
        khách không biết được giảm bao nhiêu nên không có lý do để bấm.

        Nay nói đủ ba thứ mà một thanh khuyến mại phải nói, đúng thứ tự
        khách cần:
          1. ĐƯỢC GÌ      — "Giảm đến 30%"  (nổi bật nhất)
          2. TÊN chương trình — cho khách biết đây là dịp gì
          3. CÒN BAO LÂU  — và nói đúng mức cấp bách: giờ, không phải ngày
                            khi chỉ còn vài tiếng

        ĐÓNG ĐƯỢC. Thanh này chiếm chỗ trên mọi trang; không cho đóng thì
        khách đã xem rồi vẫn phải nhìn nó suốt phiên. Trạng thái đóng nhớ
        ở trình duyệt (localStorage) — không cần lưu ở máy chủ vì đây là
        sở thích hiển thị, không phải dữ liệu của cửa hàng.
    --}}
    <div class="announcement-bar" data-announcement="{{ $key }}" hidden>
        <div class="container-shop announcement-bar__inner">

            <div class="announcement-bar__text">

                @if($deal)
                    {{-- Con số thật, tính từ dữ liệu chương trình.
                         Không có mức quy về được thì không hiện gì. --}}
                    <span class="announcement-bar__deal">{{ $deal }}</span>
                @endif

                <span class="announcement-bar__name">{{ $promotion->name }}</span>

                @if($window)
                    {{-- Chương trình chỉ chạy trong khung giờ / vài thứ
                         nhất định (xem QĐ-24). Không nói thì khách quay
                         lại lúc 10h sáng và thấy giá khác mà không hiểu. --}}
                    <span class="announcement-bar__window">{{ $window }}</span>
                @endif

                @if($endsIn)
                    <span class="announcement-bar__meta">{{ $endsIn }}</span>
                @endif

            </div>

            <div class="announcement-bar__actions">
                {{--
                    "Xem sự kiện", KHÔNG PHẢI "Xem N sản phẩm".

                    Nút cũ dẫn tới trang danh sách đã lọc — đúng hàng nhưng
                    KHÔNG CÓ GÌ CỦA SỰ KIỆN. Khách bấm vào một banner Giáng
                    sinh rồi rơi vào một lưới sản phẩm bình thường: không
                    biết chương trình là gì, giảm bao nhiêu, tới bao giờ,
                    hay có mã nào để lấy.

                    Nay dẫn tới trang sự kiện riêng, nơi có đủ cả ba.
                --}}
                <a
                    href="{{ route('shop.events.show', $promotion) }}"
                    class="announcement-bar__link"
                >
                    Xem sự kiện
                </a>

                <button
                    type="button"
                    class="announcement-bar__close"
                    data-announcement-close
                    aria-label="Đóng thông báo khuyến mại"
                >
                    <x-site.icon name="x-circle" />
                </button>
            </div>

        </div>
    </div>
@endif
