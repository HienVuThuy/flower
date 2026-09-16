@props([
    'items' => null,
    'personalized' => false,
    'title' => null,
    'source' => 'home',
])

{{-- Khối gợi ý sản phẩm. --}}
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

                        <x-product.card
                            :product="$item['product']"
                            :ref="'goi-y:' . $source" />

                        <p class="reco-reason">{{ $item['reason'] }}</p>

                    </div>
                @endforeach
            </div>

        </div>
    </section>

@endif
