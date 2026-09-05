@extends('layouts.app')

@section('title', 'Voucher')

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="[['label' => 'Voucher']]" />

        <div class="section-header">
            <div>
                <span class="text-label section-header__eyebrow d-block">Ưu đãi</span>
                <h1 class="text-h2 section-header__title">Voucher của cửa hàng</h1>
                <p class="mb-0">
                    Lưu mã về ví, tới bước thanh toán chọn lại là xong — không phải nhớ mã.
                </p>
            </div>
        </div>

        {{-- ============ VÍ CỦA TÔI ============ --}}
        @auth
            <h2 class="text-h4 mb-3">Ví của tôi</h2>

            @if($mine->isEmpty())
                <div class="surface-card empty-state mb-5">
                    <p class="empty-state__title">Ví voucher đang trống.</p>
                    <p class="mb-0">Lưu mã ở phần bên dưới để dùng cho lần mua sau.</p>
                </div>
            @else
                <div class="voucher-grid mb-5">
                    @foreach($mine as $row)
                        <x-shop.voucher-card
                            :coupon="$row['coupon']"
                            :saved="true"
                            :used-count="$row['usedCount']"
                            :exhausted-for-user="$row['exhaustedForUser']" />
                    @endforeach
                </div>
            @endif
        @endauth

        {{-- ============ ĐANG MỜI ============ --}}
        <h2 class="text-h4 mb-3">
            @auth Mã khác đang mở @else Mã đang mở @endauth
        </h2>

        @if($claimable->isEmpty())
            <div class="surface-card empty-state">
                <p class="empty-state__title">
                    @auth
                        Bạn đã lưu hết mã đang mở.
                    @else
                        Hiện chưa có chương trình voucher nào.
                    @endauth
                </p>
                <p class="mb-0">
                    {{--
                        KHÔNG hứa hẹn "hãy quay lại sau" một cách chung
                        chung khi chưa biết có chương trình nào sắp chạy
                        hay không. Chỉ nói đúng thứ khách làm được ngay.
                    --}}
                    Mã in trên phiếu mua hàng hoặc gửi riêng cho bạn vẫn nhập tay
                    được ở bước thanh toán.
                </p>
            </div>
        @else
            <div class="voucher-grid">
                @foreach($claimable as $coupon)
                    <x-shop.voucher-card :coupon="$coupon" />
                @endforeach
            </div>
        @endif

        <p class="text-caption mt-4 mb-0">
            Mỗi đơn hàng chỉ dùng được một mã. Mã đã lưu không tự áp — bạn chọn
            ở bước thanh toán.
        </p>

    </div>
</section>

@endsection
