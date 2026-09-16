@php
    $cacChuongTrinh = app(\App\Services\Promotion\ActivePromotionProvider::class)->dangChay(3);
@endphp

<section class="section-sm">
    <div class="container-shop">

        <div class="banner-rotator" data-banner-rotator data-interval="7000">

            <div class="banner-rotator__track">

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

                                    @if($conLai)
                                        <span class="campaign-banner__countdown">{{ ucfirst($conLai) }}</span>
                                    @endif
                                </div>

                            </div>

                        </div>

                    </article>
                @endforeach

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
