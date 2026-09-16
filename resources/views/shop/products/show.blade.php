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

            <div class="col-lg-6">
                <x-product.gallery :product="$product" />
            </div>

            <div class="col-lg-6" data-product-panel>

                <span class="product-info__eyebrow">
                    {{ $product->selling_form?->label() }}
                </span>

                <h1 class="text-h1 product-info__title">{{ $product->name }}</h1>

                <div class="product-info__meta">
                    <span>Mã: {{ $product->product_code }}</span>
                </div>

                @if($product->ratingCount() > 0 || ($daBan ?? 0) > 0)
                    <div class="product-info__proof">
                        @if($product->ratingCount() > 0)
                            <a href="#danh-gia" class="product-info__rating">
                                <x-product.rating-stars
                                    :value="$product->ratingAverage()"
                                    :count="$product->ratingCount()" />
                            </a>
                        @endif

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

                @if(($quaKem ?? collect())->isNotEmpty())
                    <details class="product-gifts mb-3" data-qua-san-pham>
                        <summary class="product-gifts__title">
                            <x-site.icon name="flower1" />
                            Mua {{ $quaKem->min('per_quantity') }} mặt hàng – nhận {{ $quaKem->count() > 1 ? $quaKem->count() . ' quà' : 'quà' }} miễn phí
                            <span class="product-gifts__names">{{ $quaKem->map(fn ($pg) => $pg->giftItem->name)->implode(', ') }}</span>
                        </summary>
                        <ul class="product-gifts__list list-unstyled mb-0 mt-2">
                            @foreach($quaKem as $pg)
                                <li class="product-gifts__item" data-qua-kem="{{ $pg->id }}">
                                    @if($pg->giftItem->product?->main_image)
                                        <x-site.image :path="$pg->giftItem->product->main_image" :alt="$pg->giftItem->name" class="product-gifts__img" />
                                    @else
                                        <span class="product-gifts__icon"><x-site.icon name="flower2" /></span>
                                    @endif
                                    <span>
                                        {{ $pg->giftItem->name }}
                                        <span class="text-caption d-block">
                                            {{ $pg->moTaLuat() }}@if($pg->variant) · khi chọn quy cách {{ $pg->variant->name }}@endif
                                        </span>
                                        @if($pg->giftItem->value !== null)
                                            <span class="text-caption d-block">Trị giá {{ \App\Services\Shop\Money::format((string) $pg->giftItem->value) }}</span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif

                @if($product->track_inventory)
                    <div class="mb-3">
                        @if($product->inStock() && ($chiCon ?? null) !== null)
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

                <x-product.actions :product="$product" :with-quantity="true" :variants="$activeVariants" :pho-bien="$quyCachPhoBien ?? null" />

                <div class="product-info__wish">
                    <x-product.wishlist-button :product="$product" :active="$product->isWishlisted()" />
                    <span class="text-caption">
                        {{ $product->isWishlisted() ? 'Đang trong danh sách yêu thích' : 'Lưu để xem sau' }}
                    </span>
                </div>

                <x-site.commitments />

            </div>

        </div>

        @if($product->description || $product->blocks->isNotEmpty())
            <div class="mt-5" style="max-width: 68ch;">
                <h2 class="text-h3 mb-3">Mô tả chi tiết</h2>

                @if($product->description)
                    <div>{!! nl2br(e($product->description)) !!}</div>
                @endif

                <x-product.detail-blocks :product="$product" />
            </div>
        @endif

        <x-product.videos :product="$product" />

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

            @if($product->careEntries())
                <div class="col-lg-6">
                    <h2 class="text-h3 mb-3">{{ $product->careProfile()->label() }}</h2>
                    <x-product.care-guide :product="$product" />
                </div>
            @endif

        </div>

        <x-product.classification :product="$product" />

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
                                @if($bai->anhDau())
                                    <x-site.image :path="$bai->anhDau()->path" :alt="'Ảnh do ' . $bai->user?->name . ' chia sẻ'" class="community-card__img" />
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

        <div class="mt-5">
            <x-product.reviews
                :product="$product"
                :reviews="$reviews"
                :reviewable-order="$reviewableOrder" />
        </div>

        <x-product.cross-sell
            :items="$accessories"
            title="Mua kèm cho món này"
            note="Những thứ thường được mua cùng để cây/hoa bền hơn."
            source="product-detail" />

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

<x-product.recommendations
    :items="$recommendations['items']"
    :personalized="$recommendations['personalized']"
    title="Có thể bạn cũng thích"
    source="product-detail" />

<div class="container-shop">
    <div class="product-sticky-cta">
        <x-product.actions :product="$product" :compact="true" />
    </div>
</div>

@endsection
