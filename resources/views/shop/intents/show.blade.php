@extends('layouts.app')

@section('title', $intent->heading())

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="[
            ['label' => 'Chọn theo nhu cầu', 'url' => route('shop.advisor.index')],
            ['label' => $intent->label()],
        ]" />

        <div class="section-header">
            <div>
                <span class="text-label section-header__eyebrow d-block">Bạn đang tìm</span>
                <h1 class="text-h2 section-header__title">{{ $intent->heading() }}</h1>
                <p class="mb-0">{{ $intent->tagline() }}</p>
            </div>
        </div>

        <div class="row g-4 g-lg-5">

            {{--
                HƯỚNG DẪN ĐI TRƯỚC, HÀNG ĐI SAU.

                Khách bấm vào thẻ này vì họ CHƯA BIẾT chọn gì — nếu biết
                rồi thì đã vào thẳng trang sản phẩm. Đưa lưới hàng lên
                trước là trả lời câu hỏi họ chưa kịp hỏi.

                Trên màn hình rộng thì hai cột: hướng dẫn bên trái đọc
                được liên tục, ảnh minh hoạ bên phải bám theo khi cuộn.
            --}}
            <div class="col-lg-7">
                <div class="intent-page">
                    @include('shop.intents._guide', ['intent' => $intent])
                </div>
            </div>

            <div class="col-lg-5">
                <div class="intent-page__aside">
                    <img
                        src="{{ Vite::asset('resources/images/catalog/' . $intent->image()) }}"
                        alt=""
                        class="intent-page__image"
                        loading="lazy"
                        decoding="async"
                    >

                    {{--
                        Ba lối đi tiếp, đặt ngay cạnh bài viết.

                        Đọc xong hướng dẫn là lúc khách sẵn sàng làm gì đó;
                        bắt họ cuộn lên header tìm menu là đánh mất đúng
                        khoảnh khắc đó.
                    --}}
                    <div class="intent-page__links">
                        <a href="{{ route('shop.advisor.index') }}" class="btn btn-secondary-brand w-100">
                            Lọc cây theo điều kiện nhà bạn
                        </a>
                        <a href="{{ route('shop.supplies.index') }}" class="btn btn-ghost w-100 mt-2">
                            Xem phụ kiện &amp; vật tư
                        </a>
                        <a href="{{ route('shop.bulk-inquiry.create') }}" class="btn btn-ghost w-100 mt-2">
                            Cần số lượng lớn? Gửi yêu cầu
                        </a>
                    </div>
                </div>
            </div>

        </div>

        {{-- ============ HÀNG GỢI Ý ============ --}}
        <div class="mt-5">
            <div class="section-header">
                <div>
                    <span class="text-label section-header__eyebrow d-block">Gợi ý</span>
                    <h2 class="text-h3 section-header__title">Hợp với nhu cầu này</h2>
                </div>
                <a href="{{ route('shop.products.index') }}" class="btn btn-ghost">Xem tất cả</a>
            </div>

            @if($products->isEmpty())
                <x-site.empty-state
                    title="Chưa có sản phẩm nào khớp nhu cầu này"
                    text="Cửa hàng đang bổ sung. Bạn xem toàn bộ hoa và cây cảnh nhé."
                >
                    <x-slot:actions>
                        <a href="{{ route('shop.products.index') }}" class="btn btn-secondary-brand btn-sm">Xem hoa &amp; cây cảnh</a>
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
            @endif
        </div>

        {{--
            ============ MUA KÈM ============
            Không render nếu rỗng — một khối "mua kèm" trống chỉ là một
            lời mời rỗng. Xem IntentController::extrasFor().
        --}}
        <x-product.cross-sell
            :items="$extras"
            title="Dụng cụ nên có sẵn"
            note="Những thứ thường phải mua thêm cho nhu cầu này."
            :source="'nhu-cau:' . $intent->value" />

    </div>
</section>

@endsection
