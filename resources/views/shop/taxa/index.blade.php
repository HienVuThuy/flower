@extends('layouts.app')

@section('title', 'Cây theo loài')

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="[['label' => 'Cây theo loài']]" />

        <div class="section-header">
            <div>
                <h1 class="text-h1 section-header__title">Cây theo loài</h1>
                <p class="text-body-sm mt-2 mb-0" style="max-width: 62ch;">
                    Cách thiên nhiên xếp cây, không phải cách cửa hàng bày hàng.
                    Đi từ <strong>Ngành</strong> xuống dần tới <strong>Loài</strong> —
                    hoặc nhảy thẳng vào một <strong>Họ</strong> bên dưới.
                </p>
            </div>
        </div>

        @foreach($goc as $gioi)
            <div class="taxon-kingdom">
                <p class="taxon-kingdom__label">
                    <strong>{{ $gioi->displayName() }}</strong>
                    @if($gioi->scientific_name)
                        <em class="taxon-sci">{{ $gioi->scientific_name }}</em>
                    @endif
                </p>

                <div class="row g-3">
                    @foreach($gioi->children as $nganh)
                        <div class="col-md-4">
                            <a href="{{ route('shop.taxa.show', $nganh) }}" class="taxon-card">
                                <span class="taxon-card__rank">{{ $nganh->rank->label() }}</span>
                                <span class="taxon-card__name">{{ $nganh->name }}</span>
                                @if($nganh->scientific_name)
                                    <em class="taxon-sci">{{ $nganh->scientific_name }}</em>
                                @endif
                                @if($nganh->description)
                                    <span class="taxon-card__desc">{{ $nganh->description }}</span>
                                @endif
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        @if($ho->isNotEmpty())
            <div class="section-header mt-5">
                <div>
                    <h2 class="text-h2 section-header__title">Đi thẳng tới một họ</h2>
                </div>
            </div>

            <div class="row g-3">
                @foreach($ho as $muc)
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="{{ route('shop.taxa.show', $muc['taxon']) }}" class="taxon-tile">
                            @if($muc['taxon']->image)
                                <x-site.image :path="$muc['taxon']->image" :alt="$muc['taxon']->name" class="taxon-tile__img" />
                            @else
                                <span class="taxon-tile__img taxon-tile__img--empty">
                                    <x-site.leaf-placeholder />
                                </span>
                            @endif

                            <span class="taxon-tile__body">
                                <span class="taxon-tile__name">{{ $muc['taxon']->displayName() }}</span>
                                @if($muc['taxon']->scientific_name)
                                    <em class="taxon-sci">{{ $muc['taxon']->scientific_name }}</em>
                                @endif
                                <span class="taxon-tile__count">{{ $muc['soSanPham'] }} sản phẩm</span>
                            </span>
                        </a>
                    </div>
                @endforeach
            </div>
        @endif

    </div>
</section>

@endsection
