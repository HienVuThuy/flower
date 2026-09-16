@props([
    'items',
    'title' => 'Mua kèm',
    'note' => null,
    'source' => 'cross-sell',
])

{{-- GỢI Ý MUA KÈM (phụ kiện). --}}
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

                    @if(config('features.cart') && $item->inStock())
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
