@extends('layouts.app')

@section('title', 'Hồ sơ tài khoản')

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="[['label' => 'Hồ sơ tài khoản']]" />

        <div class="section-header">
            <div>
                <span class="text-label section-header__eyebrow d-block">Tài khoản</span>
                <h1 class="text-h2 section-header__title">Hồ sơ của bạn</h1>
            </div>
        </div>

        <nav class="profile-tabs" aria-label="Mục hồ sơ">
            @foreach($cacMuc as $ma => $nhan)
                <a href="{{ route('shop.profile.edit', ['muc' => $ma]) }}"
                   class="profile-tabs__item {{ $muc === $ma ? 'is-active' : '' }}"
                   @if($muc === $ma) aria-current="page" @endif>
                    {{ $nhan }}
                </a>
            @endforeach
        </nav>

        <div class="row g-4">

            <div class="col-lg-8">

                @if($muc === 'thong-tin')

                    @include('shop.profile.partials.thong-tin')

                @elseif($muc === 'diem-thuong')

                    @include('shop.profile.partials.diem-thuong')

                @elseif($muc === 'hang-thanh-vien')

                    @include('shop.profile.partials.hang-thanh-vien')

                @elseif($muc === 'tra-gop')

                    @include('shop.profile.partials.tra-gop')

                @elseif($muc === 'bao-mat')

                    @include('shop.profile.partials.doi-mat-khau')

                    <div class="mt-4">
                        @include('shop.profile.partials.thiet-bi')
                    </div>

                    <div class="mt-5">
                        @include('shop.profile.partials.xoa-tai-khoan')
                    </div>

                @else

                    @include('shop.profile.partials.nen-sang-toi')

                    <div class="mt-4">
                        @include('shop.profile.partials.thu-thong-bao')
                    </div>

                @endif

            </div>

            <div class="col-lg-4">
                @include('shop.profile.partials.tom-tat')
            </div>

        </div>

    </div>
</section>

@endsection
