@php
    $promotion = app(\App\Services\Promotion\ActivePromotionProvider::class)->featured();
@endphp

@if($promotion)
    <section class="section-sm">
        <div class="container-shop">

            <div class="campaign-banner {{ $promotion->banner ? 'campaign-banner--has-image' : '' }}">

                @if($promotion->banner)
                    <img
                        src="{{ asset('storage/' . $promotion->banner) }}"
                        alt=""
                        class="campaign-banner__image"
                        loading="lazy"
                    >
                @endif

                <div class="campaign-banner__content">

                    <span class="text-label campaign-banner__eyebrow d-block mb-2">
                        Chương trình đang diễn ra
                    </span>

                    <h2 class="text-h2 mb-2">{{ $promotion->name }}</h2>

                    @if($promotion->short_description)
                        <p class="mb-3">{{ $promotion->short_description }}</p>
                    @endif

                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <a
                            href="{{ route('shop.products.index', ['promotion' => $promotion->slug]) }}"
                            class="btn btn-primary-brand"
                        >
                            Xem {{ $promotion->products_count }} sản phẩm ưu đãi
                        </a>

                        @if(($days = $promotion->daysRemaining()) !== null)
                            <span class="campaign-banner__countdown">
                                {{ $days > 0 ? "Còn {$days} ngày" : 'Kết thúc hôm nay' }}
                            </span>
                        @endif
                    </div>

                </div>

            </div>

        </div>
    </section>
@endif
