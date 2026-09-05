@extends('layouts.app')

@section('title', 'Trang chủ')

@section('content')

{{-- ============ 1. HERO ============ --}}
<section class="hero-section">
    <div class="container-shop">

        {{--
            HERO LÀ MỘT CSS GRID RIÊNG, KHÔNG DÙNG .row/.col CỦA BOOTSTRAP.
            ============================================================
            LỖI ĐÃ SỬA — và đây là hậu quả đo được của việc trộn hai hệ
            thống lưới:

            `.hero-section__grid` đã là `display: grid` với
            `grid-template-columns: 0.95fr 1.05fr` từ 992px trở lên. Bọc
            thêm `.row` + `.col-lg-6` vào giữa thì mỗi cột Bootstrap trở
            thành một GRID ITEM, và `width: 50%` của nó được tính trên
            chiều rộng của LÀN grid chứ không phải của cả hàng.

            Đo được ở khổ 1400px trước khi sửa:

                khối chữ   269px  (max-width cho phép 494px)
                ảnh        290px
                chỗ trống giữa hai cột  360px

            Tiêu đề bị ép xuống bốn dòng và cụm chữ nghiêng "gần hơn" bị
            ngắt làm đôi giữa hai dòng.

            Hai con TRỰC TIẾP của grid, đúng như CSS được viết ra để
            dùng: `.hero-section__copy` và `.hero-section__visual`.
        --}}
        <div class="hero-section__grid">

            <div class="hero-section__copy">
                <span class="text-label hero-section__eyebrow d-block">
                    {{ \App\Services\Shop\StoreProfile::tagline() }}
                </span>

                <h1 class="text-h1 hero-section__title">
                    Vẻ đẹp tự nhiên,
                    {{--
                        KHÔNG CHO NGẮT DÒNG GIỮA "gần hơn".

                        Chữ nghiêng ở đây là một CỤM có nghĩa, không phải
                        hai từ rời. Để nó vắt qua hai dòng thì mắt đọc
                        thành "gần" ở cuối dòng này và "hơn" ở đầu dòng
                        sau — nhấn mạnh bị vỡ làm đôi, và đó đúng là chỗ
                        câu tiêu đề muốn nhấn.

                        &nbsp; giữa hai từ là cách giữ chúng liền nhau mà
                        vẫn cho phép xuống dòng TRƯỚC hoặc SAU cả cụm.
                    --}}
                    <em>gần&nbsp;hơn</em> mỗi ngày
                </h1>

                <p class="hero-section__lead">
                    Hoa tươi cắt trong ngày và cây cảnh chọn theo đúng chỗ bạn
                    định đặt. Giao trong nội thành Hà Nội, gói cẩn thận, kèm
                    hướng dẫn chăm sóc.
                </p>

                <div class="hero-section__actions">
                    <a href="{{ route('shop.products.index') }}" class="btn btn-primary-brand">
                        Xem sản phẩm
                    </a>
                    <a href="{{ route('shop.advisor.index') }}" class="btn btn-secondary-brand">
                        Chọn cây theo nhu cầu
                    </a>
                </div>
            </div>

            {{--
                ẢNH HERO LUÂN PHIÊN theo theme đang bật.

                `heroImages` do View Composer chia sẻ sẵn (xem
                AppServiceProvider) — bộ ảnh đổi theo theme mùa vụ, và
                admin thay được từng ảnh ở trang Cấu hình.

                Ảnh ĐẦU TIÊN mang `is-active`: không có nó thì trước khi
                JavaScript chạy, cả chồng ảnh cùng ẩn và khung hero trống
                một nhịp.
            --}}
            @if(!empty($heroImages))
                <div class="hero-section__visual hero-carousel"
                     data-hero-carousel
                     data-interval="6000">
                    @foreach($heroImages as $i => $image)
                        <img
                            src="{{ $image['url'] }}"
                            alt="{{ $image['alt'] }}"
                            class="hero-carousel__slide {{ $i === 0 ? 'is-active' : '' }}"
                            {{-- Ảnh đầu tải ngay (nó nằm trong màn hình đầu
                                 tiên), những ảnh sau để lazy — chúng chỉ hiện
                                 sau 6 giây. --}}
                            loading="{{ $i === 0 ? 'eager' : 'lazy' }}"
                            @if($i === 0) fetchpriority="high" @endif
                        >
                    @endforeach
                </div>
            @endif

        </div>
    </div>
</section>

{{-- ============ 2. MUA THEO NHU CẦU ============ --}}
{{--
    Đặt NGAY SAU hero, trước cả danh mục.

    Danh mục là cách CỬA HÀNG xếp hàng ("Hoa cưới", "Cây để bàn"); nhu
    cầu là cách KHÁCH nghĩ khi vừa vào ("tôi cần quà tặng", "tôi mới
    trồng cây lần đầu"). Người chưa biết mình muốn gì thì câu hỏi thứ hai
    dễ trả lời hơn nhiều.
--}}
<section class="section-sm">
    <div class="container-shop">

        <div class="section-header">
            <div>
                <h2 class="text-h2 section-header__title">Bạn đang tìm gì?</h2>
            </div>
        </div>

        <div class="intent-grid">
            @foreach(\App\Enums\ShoppingIntent::cases() as $intent)
                <a href="{{ route('shop.intents.show', $intent->value) }}" class="intent-card">
                    <img
                        src="{{ Vite::asset('resources/images/catalog/' . $intent->image()) }}"
                        alt=""
                        class="intent-card__image"
                        loading="lazy"
                    >
                    <span>
                        {{ $intent->label() }}
                        <span class="intent-card__tagline">{{ $intent->tagline() }}</span>
                    </span>
                </a>
            @endforeach
        </div>

    </div>
</section>

{{-- ============ 3. DANH MỤC NỔI BẬT ============ --}}
<section class="section-sm">
    <div class="container-shop">

        <div class="section-header">
            <div>
                <h2 class="text-h2 section-header__title">Danh mục</h2>
            </div>
            <a href="{{ route('shop.categories.index') }}" class="btn btn-ghost">Xem tất cả</a>
        </div>

        <div class="row g-4">
            @foreach($categories as $category)
                <div class="col-6 col-lg-3">
                    <x-category.card :category="$category" />
                </div>
            @endforeach
        </div>

    </div>
</section>

{{-- ============ 4. HÀNG MỚI VỀ ============ --}}
{{--
    KHÔNG CÓ HÀNG MỚI THÌ ẨN HẲN CẢ KHỐI.

    Trước đây khối này luôn hiện, và khi rỗng thì hiện "Chưa có sản phẩm
    để hiển thị". Nhưng nó chỉ rỗng khi cửa hàng đã lâu không nhập hàng —
    tức là đúng lúc KHÔNG nên chiếm một màn hình đầu trang để nói rằng
    không có gì. Nhường chỗ cho khối gợi ý bên dưới thì khách được xem
    thứ có ích hơn.

    Khác với các khối khác trong trang: ở đó "rỗng" nghĩa là dữ liệu chưa
    được dựng và màn hình trống là một lời nhắc cho người quản trị. Ở đây
    "rỗng" là một sự thật bình thường về nhịp nhập hàng.

    Ngưỡng ngày ở config/catalog.php.
--}}
@if($featuredProducts->isNotEmpty())
<section class="section-sm">
    <div class="container-shop">

        <div class="section-header">
            <div>
                {{-- Bỏ nhãn nhỏ "Mới về": nó chép lại đúng tiêu đề ngay
                     bên dưới, cùng lỗi lặp chữ đã dọn ở các trang khác. --}}
                <h2 class="text-h2 section-header__title">Hàng mới về</h2>
            </div>
            <a href="{{ route('shop.products.index') }}" class="btn btn-ghost">Xem tất cả</a>
        </div>

        <div class="row g-4">
            @foreach($featuredProducts as $product)
                <div class="col-6 col-md-4 col-lg-3">
                    <x-product.card :product="$product" />
                </div>
            @endforeach
        </div>

    </div>
</section>
@endif

{{--
    ============ 5. GỢI Ý CÁ NHÂN HOÁ ============
    Đặt NGAY SAU khối hàng mới về: khách vừa lướt qua một loạt sản phẩm
    do cửa hàng chọn, đây là lúc hợp lý để đưa thứ hợp với riêng họ.
    Component tự ẩn hoàn toàn khi không có gì để gợi ý.
--}}
<x-product.recommendations
    :items="$recommendations"
    :personalized="$recommendationsArePersonal"
    ref="home"
/>

{{-- ============ 6. ĐƯỢC YÊU THÍCH GẦN ĐÂY ============ --}}
{{--
    DỰNG TỪ BẢNG `wishlists` THẬT, xếp theo LẦN THÍCH MỚI NHẤT.

    Chưa ai thích gì thì khối này KHÔNG hiện. Đây là chỗ dễ bịa nhất trên
    cả trang chủ: rất dễ đổ đại vài sản phẩm vào cho đỡ trống, và khách
    sẽ tin rằng chúng được yêu thích. Thà thiếu một khối còn hơn một khối
    nói sai — xem HomeController::mostWished().
--}}
@if($mostWished->isNotEmpty())
<section class="section-sm">
    <div class="container-shop">

        <div class="section-header">
            <div>
                <h2 class="text-h2 section-header__title">Được yêu thích gần đây</h2>
                <p class="text-body-sm mt-2 mb-0">
                    Những món khách vừa lưu vào danh sách yêu thích.
                </p>
            </div>
        </div>

        <div class="row g-4">
            @foreach($mostWished as $product)
                <div class="col-6 col-md-4 col-lg-3">
                    <x-product.card :product="$product" />
                </div>
            @endforeach
        </div>

    </div>
</section>
@endif

{{-- ============ 7. BANNER KHUYẾN MẠI & SỰ KIỆN ============ --}}
{{--
    Component tự lo phần nội dung: nó gộp "Chương trình đang diễn ra" và
    "Sự kiện & số lượng lớn" thành một khối luân phiên, và tự ẩn khi
    không có chương trình nào.
--}}
<section class="section-sm">
    <div class="container-shop">
        <x-site.banner-carousel />
    </div>
</section>

{{-- ============ 8. CÂU CHUYỆN CỬA HÀNG ============ --}}
<section class="section-sm">
    <div class="container-shop">
        <div class="story-section">
            <div class="story-section__grid">

                <div class="story-section__media">
                    <img
                        src="{{ Vite::asset('resources/images/editorial/story-studio.jpg') }}"
                        alt="Không gian chuẩn bị hoa tại {{ \App\Services\Shop\StoreProfile::name() }}"
                        loading="lazy"
                    >
                </div>

                <div class="story-section__copy">
                    <span class="text-label story-section__eyebrow d-block">Về cửa hàng</span>

                    <h2 class="text-h2">Hoa được chọn từng cành, cây được chọn theo chỗ đặt</h2>

                    <p>
                        Mỗi bó hoa được bó trong ngày giao, không giữ sẵn qua đêm.
                        Cây cảnh thì chọn theo ánh sáng và độ ẩm của đúng chỗ bạn định
                        đặt — vì cây hợp chỗ thì mới sống lâu.
                    </p>

                    <div class="hero-section__actions">
                        <a href="{{ route('shop.pages.show', 'gioi-thieu') }}" class="btn btn-secondary-brand">
                            Tìm hiểu thêm
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

{{-- ============ 9. CAM KẾT DỊCH VỤ ============ --}}
{{--
    Danh sách do admin tự nhập ở trang Cấu hình. Chưa nhập gì thì
    component không in ra gì cả — cố ý không cài sẵn câu mẫu nào, vì chỉ
    chủ cửa hàng mới biết mình làm được gì.
--}}
<section class="section-sm">
    <div class="container-shop">
        <x-site.commitments />
    </div>
</section>

@endsection
