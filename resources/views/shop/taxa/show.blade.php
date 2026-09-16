@extends('layouts.app')

@section('title', $taxon->fullName())

@section('content')

<section class="section-sm">
    <div class="container-shop">

        {{-- BREADCRUMB DỰNG TỪ CHÍNH ĐƯỜNG DẪN PHÂN LOẠI. --}}
        <x-site.breadcrumb :items="collect([['label' => 'Cây theo loài', 'url' => route('shop.taxa.index')]])
            ->merge($chain->map(fn ($nut) => [
                'label' => $nut->displayName(),
                'url' => route('shop.taxa.show', $nut),
            ]))
            ->all()" />

        <div class="section-header">
            <div>
                <span class="text-label section-header__eyebrow d-block">{{ $taxon->rank->label() }}</span>
                <h1 class="text-h1 section-header__title">{{ $taxon->name }}</h1>

                @if($taxon->scientific_name)
                    <p class="taxon-sci taxon-sci--lead mb-0">
                        @if($taxon->rank->italic())
                            <em>{{ $taxon->scientific_name }}</em>
                        @else
                            {{ $taxon->scientific_name }}
                        @endif
                    </p>
                @endif

                @if($taxon->description)
                    <p class="text-body-sm mt-2 mb-0" style="max-width: 62ch;">{{ $taxon->description }}</p>
                @endif
            </div>
        </div>

        @if($nhanhCon->isNotEmpty())
            <div class="filter-chip-group mb-4">
                @foreach($nhanhCon as $muc)
                    <a href="{{ route('shop.taxa.show', $muc['taxon']) }}" class="filter-chip">
                        {{ $muc['taxon']->displayName() }}
                        <span class="filter-chip__count">{{ $muc['soSanPham'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        @if($products->isEmpty())
            <x-site.empty-state
                title="Chưa có hàng trong nhóm này"
                text="Cửa hàng chưa nhập cây thuộc nhóm phân loại này. Thử một nhánh khác nhé."
            >
                <x-slot:actions>
                    <a href="{{ route('shop.taxa.index') }}" class="btn btn-primary-brand">Xem các nhóm khác</a>
                </x-slot:actions>
            </x-site.empty-state>
        @else
            <div class="row g-4">
                @foreach($products as $product)
                    <div class="col-6 col-md-4 col-lg-3">
                        <x-product.card :product="$product" />
                    </div>
                @endforeach
            </div>

            @if($products->hasPages())
                <div class="mt-4">{{ $products->links() }}</div>
            @endif
        @endif

    </div>
</section>

@endsection
