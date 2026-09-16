@php
    $promotion = app(\App\Services\Promotion\ActivePromotionProvider::class)->featured();
@endphp

@if($promotion)
    @php
        $deal = $promotion->headlineDiscount();
        $endsIn = $promotion->endsInText();
        $window = $promotion->scheduleText();

        $key = $promotion->slug.'|'.($promotion->ends_at?->timestamp ?? 'mo');
    @endphp

    <div class="announcement-bar" data-announcement="{{ $key }}" hidden>
        <div class="container-shop announcement-bar__inner">

            <div class="announcement-bar__text">

                @if($deal)
                    <span class="announcement-bar__deal">{{ $deal }}</span>
                @endif

                <span class="announcement-bar__name">{{ $promotion->name }}</span>

                @if($window)
                    <span class="announcement-bar__window">{{ $window }}</span>
                @endif

                @if($endsIn)
                    <span class="announcement-bar__meta">{{ $endsIn }}</span>
                @endif

            </div>

            <div class="announcement-bar__actions">
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
