@extends('layouts.app')

@section('title', $product->meta_title ?: $product->name)

@section('content')

@php
    $price = $product->price();
    $promotion = $product->activePromotion();
    $activeVariants = $product->variants;

@endphp

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="[
            ['label' => 'Danh mục', 'url' => route('shop.categories.index')],
            ['label' => $product->category->name, 'url' => route('shop.categories.show', $product->category)],
            ['label' => $product->name],
        ]" />

        <div class="row g-4 g-lg-5">

            {{-- ============ GALLERY ============ --}}
            <div class="col-lg-6">
                <x-product.gallery :product="$product" />
            </div>

            {{-- ============ THÔNG TIN & MUA HÀNG ============ --}}
            <div class="col-lg-6" data-product-panel>

                <span class="product-info__eyebrow">
                    {{ $product->selling_form?->label() }}
                </span>

                <h1 class="text-h1 product-info__title">{{ $product->name }}</h1>

                {{--
                    Chỉ còn mã sản phẩm.

                    Trước đây chỗ này in "Loại sản phẩm", nhưng với 11/15
                    sản phẩm nó lặp lại đúng tên Danh mục đã hiện ở
                    breadcrumb và ở bảng thông số bên dưới ("Danh mục: Hoa
                    / Loại sản phẩm: Hoa"). Xem QĐ-08.
                --}}
                <div class="product-info__meta">
                    <span>Mã: {{ $product->product_code }}</span>
                </div>

                {{-- Điểm đánh giá: bấm vào nhảy xuống khối đánh giá bên dưới. --}}
                @if($product->ratingCount() > 0 || ($daBan ?? 0) > 0)
                    <div class="product-info__proof">
                        @if($product->ratingCount() > 0)
                            <a href="#danh-gia" class="product-info__rating">
                                <x-product.rating-stars
                                    :value="$product->ratingAverage()"
                                    :count="$product->ratingCount()" />
                            </a>
                        @endif

                        {{--
                            ĐÃ BÁN — đếm THẬT từ đơn đã giao trong 30 ngày.
                            0 thì không in: "Đã bán 0" chỉ làm khách e ngại, còn
                            in số bịa là nói dối. Xem SocialProof::banGanDay().
                        --}}
                        @if(($daBan ?? 0) > 0)
                            <span class="product-info__sold">Đã bán {{ number_format($daBan, 0, ',', '.') }} trong 30 ngày qua</span>
                        @endif
                    </div>
                @endif

                <div class="product-info__price" data-price-display>
                    <x-product.price :product="$product" size="lg" />
                </div>

                @if($promotion)
                    <div class="promo-note mb-3">
                        <x-site.icon name="megaphone" />
                        <span>
                            Thuộc chương trình
                            <span class="promo-note__name">{{ $promotion->name }}</span>
                        </span>

                        @if(($days = $promotion->daysRemaining()) !== null)
                            <span class="promo-note__countdown">
                                {{ $days > 0 ? "Còn {$days} ngày" : 'Kết thúc hôm nay' }}
                            </span>
                        @endif
                    </div>
                @endif

                @if($product->track_inventory)
                    <div class="mb-3">
                        @if($product->inStock() && ($chiCon ?? null) !== null)
                            {{--
                                KHAN HIẾM THẬT — tồn kho còn từ 1 tới 5, không có quy cách.
                                Không có đồng hồ đếm ngược hay "N người đang xem": không
                                có dữ liệu nào đứng sau những thứ đó.
                            --}}
                            <span class="status-chip status-chip--warning">
                                <x-site.icon name="info-circle" /> Chỉ còn {{ $chiCon }} sản phẩm
                            </span>
                        @elseif($product->inStock())
                            <span class="status-chip status-chip--success">
                                <x-site.icon name="check-circle" /> Còn hàng
                            </span>
                        @else
                            <span class="status-chip status-chip--neutral">
                                <x-site.icon name="x-circle" /> Tạm hết hàng
                            </span>
                        @endif
                    </div>
                @endif

                @if($product->short_description)
                    <p>{{ $product->short_description }}</p>
                @endif

                {{-- ============ CTA (kèm chọn quy cách + số lượng) ============ --}}
                <x-product.actions :product="$product" :with-quantity="true" :variants="$activeVariants" :pho-bien="$quyCachPhoBien ?? null" />

                {{--
                    Nút yêu thích đặt DƯỚI "Thêm vào giỏ"/"Mua ngay", không
                    ngang hàng: lưu để xem sau là hành động phụ, không được
                    tranh chỗ với hành động mua.
                --}}
                <div class="product-info__wish">
                    <x-product.wishlist-button :product="$product" :active="$product->isWishlisted()" />
                    <span class="text-caption">
                        {{ $product->isWishlisted() ? 'Đang trong danh sách yêu thích' : 'Lưu để xem sau' }}
                    </span>
                </div>

                {{-- Cam kết do admin nhập; không nhập thì không hiện gì --}}
                <x-site.commitments />

            </div>

        </div>

        {{-- ============ MÔ TẢ ============ --}}
        @if($product->description || $product->blocks->isNotEmpty())
            <div class="mt-5" style="max-width: 68ch;">
                <h2 class="text-h3 mb-3">Mô tả chi tiết</h2>

                @if($product->description)
                    <div>{!! nl2br(e($product->description)) !!}</div>
                @endif

                {{-- Khối chữ/ảnh xen kẽ, nếu người bán có soạn. Sản phẩm cũ
                     không có khối nào thì phần trên vẫn hiện như trước. --}}
                <x-product.detail-blocks :product="$product" />
            </div>
        @endif

        <x-product.videos :product="$product" />

        {{-- ============ THÔNG SỐ ============ --}}
        <div class="row g-4 g-lg-5 mt-4">

            <div class="col-lg-6">
                <h2 class="text-h3 mb-3">Thông tin sản phẩm</h2>

                <div class="spec-list">
                    <div class="spec-list__row">
                        <span class="spec-list__label">Danh mục</span>
                        <span class="spec-list__value">{{ $product->category->name }}</span>
                    </div>
                    <div class="spec-list__row">
                        <span class="spec-list__label">Hình thức bán</span>
                        <span class="spec-list__value">{{ $product->selling_form?->label() ?? '—' }}</span>
                    </div>
                    <div class="spec-list__row">
                        <span class="spec-list__label">Mã sản phẩm</span>
                        <span class="spec-list__value">{{ $product->product_code }}</span>
                    </div>

                    {{--
                        Tình trạng hàng — hệ thống vẫn biết nhưng trước đây
                        không hiện cho khách.

                        Guide §4.1: không phải sản phẩm nào cũng quản lý số
                        lượng như hàng công nghiệp. Vì vậy KHÔNG in ra con số
                        tồn kho khô khan, mà diễn đạt theo đúng cách bán:
                        có quản lý kho thì nói còn/hết, không quản lý kho thì
                        nói làm theo đơn.
                    --}}
                    <div class="spec-list__row">
                        <span class="spec-list__label">Tình trạng</span>
                        <span class="spec-list__value">
                            @if($product->status === 'out_of_stock')
                                Tạm hết hàng
                            @elseif(! $product->track_inventory)
                                Làm theo đơn đặt
                            @elseif($product->stock_quantity > 0)
                                Còn hàng ({{ $product->stock_quantity }} sản phẩm)
                            @else
                                Tạm hết hàng
                            @endif
                        </span>
                    </div>

                    @if($activeVariants->isNotEmpty())
                        <div class="spec-list__row">
                            <span class="spec-list__label">Quy cách</span>
                            <span class="spec-list__value">{{ $activeVariants->pluck('name')->join(', ') }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{--
                Hướng dẫn chăm sóc — tiêu đề và nội dung đổi theo hình
                thức bán. Cây chậu thì "Chăm sóc cây", bó hoa thì "Giữ
                hoa tươi" (Guide mục 4.4).

                careEntries() đã lọc theo đúng hồ sơ và bỏ ô rỗng, nên
                nếu sản phẩm còn sót dữ liệu của hồ sơ khác thì cũng
                không hiện nhầm.
            --}}
            @if($product->careEntries())
                <div class="col-lg-6">
                    <h2 class="text-h3 mb-3">{{ $product->careProfile()->label() }}</h2>
                    <x-product.care-guide :product="$product" />
                </div>
            @endif

        </div>

        {{--
            PHÂN LOẠI & ĐẶC ĐIỂM — RA NGOÀI hàng cột, chiếm trọn chiều ngang.

            Trước đây nó là một `col-lg-6` nối đuôi hàng trên, nên rơi
            xuống dòng mới và chiếm đúng nửa trái, bỏ trống hẳn nửa phải.
            Khối này tự chia hai cột bên trong theo đúng bản chất dữ liệu
            — xem chú thích trong component.

            Vẫn đặt NGAY SAU hướng dẫn chăm sóc: hai khối trả lời cùng
            một câu hỏi từ hai phía, "chăm thế nào" và "vì sao phải chăm
            như thế". Cây sa mạc tưới thưa không phải vì cửa hàng bảo
            vậy, mà vì nó là cây sa mạc.

            Component tự ẩn hoàn toàn khi sản phẩm không có nhãn nào — bó
            hoa cưới phối nhiều loài thì không có phân loại, và đó là sự
            thật chứ không phải dữ liệu thiếu.
        --}}
        <x-product.classification :product="$product" />

        {{-- ============ KHÁCH KHOE CÂY NÀY (Góc cây, đã duyệt) ============ --}}
        @if(($baiKhoe ?? collect())->isNotEmpty())
            <div class="mt-5" data-khach-khoe>
                <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-3">
                    <h2 class="text-h3 mb-0">Khách khoe cây này</h2>
                    <a href="{{ route('shop.community.index') }}">Xem Góc cây</a>
                </div>
                <div class="row g-3">
                    @foreach($baiKhoe as $bai)
                        <div class="col-6 col-md-3">
                            <a href="{{ route('shop.community.show', $bai->id) }}" class="community-card d-block text-reset text-decoration-none">
                                @if($bai->photo)
                                    <x-site.image :path="$bai->photo" :alt="'Ảnh do ' . $bai->user?->name . ' chia sẻ'" class="community-card__img" />
                                @endif
                                <div class="community-card__body">
                                    <p class="community-card__text mb-1">{{ \Illuminate\Support\Str::limit($bai->body, 80) }}</p>
                                    <p class="community-card__meta">{{ $bai->user?->name ?? 'Khách' }}</p>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ============ ĐÁNH GIÁ ============ --}}
        <div class="mt-5">
            <x-product.reviews
                :product="$product"
                :reviews="$reviews"
                :reviewable-order="$reviewableOrder" />
        </div>

        {{-- ============ MUA KÈM ============ --}}
        <x-product.cross-sell
            :items="$accessories"
            title="Mua kèm cho món này"
            note="Những thứ thường được mua cùng để cây/hoa bền hơn."
            source="product-detail" />

        {{-- ============ SẢN PHẨM LIÊN QUAN ============ --}}
        @if($related->isNotEmpty())
            <div class="mt-5">
                <h2 class="text-h3 mb-3">Sản phẩm liên quan</h2>
                <div class="row g-4">
                    @foreach($related as $item)
                        <div class="col-6 col-md-3">
                            <x-product.card :product="$item" />
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </div>
</section>

{{--
    GỢI Ý THEO HÀNH VI — đặt SAU "Sản phẩm liên quan", không thay nó.

    Hai khối trả lời hai câu khác nhau: "còn gì giống thế này?" (liên
    quan, cùng danh mục, ai xem cũng thấy như nhau) và "còn gì hợp với
    tôi?" (gợi ý, dựa trên thứ chính người này vừa xem). Controller đã
    loại trừ chéo nên không khối nào lặp lại sản phẩm của khối kia.

    Component tự ẩn hoàn toàn khi không có gì để gợi ý.
--}}
<x-product.recommendations
    :items="$recommendations['items']"
    :personalized="$recommendations['personalized']"
    title="Có thể bạn cũng thích"
    source="product-detail" />

{{-- CTA cố định đáy màn hình trên mobile --}}
<div class="container-shop">
    <div class="product-sticky-cta">
        <x-product.actions :product="$product" :compact="true" />
    </div>
</div>

@endsection
