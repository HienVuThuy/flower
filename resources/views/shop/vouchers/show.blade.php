@extends('layouts.app')

@section('title', 'Điều kiện mã ' . $coupon->code)

@section('content')

@php
    use App\Enums\CouponType;

    $remaining = $coupon->remainingUses();
    $paymentMethods = $coupon->allowedPaymentMethods();
@endphp

<section class="section-sm">
    <div class="container-shop container-narrow">

        <x-site.breadcrumb :items="[
            ['label' => 'Voucher', 'url' => route('shop.vouchers.index')],
            ['label' => 'Mã ' . $coupon->code],
        ]" />

        <h1 class="text-h3 mb-3">Chi tiết mã giảm giá</h1>

        {{--
            THẺ VOUCHER ĐẦY ĐỦ ở đầu trang.

            Hiện lại đúng cái thẻ khách vừa bấm vào, để họ chắc chắn đang
            đọc điều kiện của ĐÚNG mã đó. Không có nó thì trang này chỉ là
            một danh sách gạch đầu dòng trôi nổi.
        --}}
        <x-shop.voucher-card
            :coupon="$coupon"
            :saved="$saved"
            :used-count="$usedCount"
            :show-terms-link="false" />

        {{--
            ============ DANH SÁCH ĐIỀU KHOẢN ============

            MỖI MỤC CHỈ HIỆN KHI CÓ DỮ LIỆU THẬT.

            Mã không giới hạn hình thức thanh toán thì KHÔNG in ra một
            dòng "áp dụng mọi hình thức" — đó là chữ thừa. Danh sách càng
            ngắn thì mấy điều kiện thật sự quan trọng càng dễ đọc, và cả
            trang này tồn tại để khách đọc được chúng.
        --}}
        <div class="voucher-terms">

            <h2 class="voucher-terms__heading">Hạn sử dụng mã</h2>
            <p class="voucher-terms__text">
                @if($coupon->starts_at || $coupon->ends_at)
                    {{ $coupon->starts_at?->format('H:i d/m/Y') ?? 'Từ khi mở' }}
                    &ndash;
                    {{ $coupon->ends_at?->format('H:i d/m/Y') ?? 'chưa có ngày kết thúc' }}
                @else
                    Không giới hạn thời gian.
                @endif

                @unless($coupon->isRunning())
                    {{-- Nói thẳng ở dòng đầu tiên. Để khách đọc hết mười
                         dòng điều kiện rồi mới phát hiện mã đã hết hạn là
                         phí thời gian của họ. --}}
                    <strong class="voucher-terms__expired">Mã hiện không dùng được.</strong>
                @endunless
            </p>

            <h2 class="voucher-terms__heading">Ưu đãi</h2>
            <ul class="voucher-terms__list">
                <li>
                    @if($coupon->type === CouponType::Percent)
                        Giảm {{ rtrim(rtrim(number_format((float) $coupon->value, 1, ',', '.'), '0'), ',') }}% giá trị hàng.
                    @else
                        Giảm <x-site.money :amount="(float) $coupon->value" /> trên tổng tiền hàng.
                    @endif
                </li>

                @if($coupon->min_order_amount)
                    <li>Áp dụng cho đơn từ
                        <strong><x-site.money :amount="(float) $coupon->min_order_amount" /></strong>
                        (tính trên tiền hàng, chưa gồm phí giao).</li>
                @else
                    <li>Không yêu cầu giá trị đơn tối thiểu.</li>
                @endif

                @if($coupon->type === CouponType::Percent && $coupon->max_discount_amount)
                    <li>Số tiền giảm tối đa
                        <strong><x-site.money :amount="(float) $coupon->max_discount_amount" /></strong>.</li>
                @endif

                @if($coupon->perUserText())
                    <li>{{ $coupon->perUserText() }}.</li>
                @endif

                @if($remaining !== null)
                    <li>
                        Số lượng có hạn — còn <strong>{{ $remaining }}</strong> lượt
                        trên tổng {{ $coupon->usage_limit }}.
                    </li>
                @endif

                <li>Mỗi đơn hàng chỉ dùng được một mã giảm giá.</li>
            </ul>

            <h2 class="voucher-terms__heading">Áp dụng cho sản phẩm</h2>
            @if($coupon->promotion)
                <p class="voucher-terms__text">
                    Mã thuộc chương trình
                    <a href="{{ route('shop.events.show', $coupon->promotion) }}">
                        {{ $coupon->promotion->name }}</a>.
                    Mã giảm trên <strong>tổng tiền hàng của đơn</strong>, không giới hạn
                    sản phẩm — nhưng mức giảm của từng sản phẩm trong chương trình
                    được tính trước, rồi mã mới áp lên tổng.
                </p>
            @else
                <p class="voucher-terms__text">
                    Áp dụng cho <strong>mọi sản phẩm</strong> đang bán, gồm cả phụ kiện
                    và vật tư chăm sóc. Mã giảm trên tổng tiền hàng, không giảm phí giao.
                </p>
            @endif

            @if($paymentMethods !== [])
                <h2 class="voucher-terms__heading">Phương thức thanh toán</h2>
                <ul class="voucher-terms__list">
                    @foreach($paymentMethods as $method)
                        <li>{{ $method->label() }}</li>
                    @endforeach
                </ul>
                <p class="voucher-terms__note">
                    {{-- Nói rõ hệ thống CHẶN THẬT, không chỉ ghi cho có.
                         Điều kiện ghi trên giấy mà không ai kiểm thì lần
                         sau khách không tin điều kiện nào nữa. --}}
                    Chọn hình thức khác ở bước thanh toán thì mã sẽ bị từ chối.
                </p>
            @endif

            <h2 class="voucher-terms__heading">Điều kiện khác</h2>
            <ul class="voucher-terms__list">
                <li>Mã: <strong class="voucher-terms__code">{{ $coupon->code }}</strong></li>
                <li>Mã không quy đổi thành tiền mặt.</li>
                <li>Đơn bị huỷ sẽ được hoàn lại lượt dùng mã.</li>
                @if($coupon->description)
                    <li>{{ $coupon->description }}</li>
                @endif
            </ul>

            @if($coupon->promotion)
                <p class="voucher-terms__text mt-4">
                    <a href="{{ route('shop.events.show', $coupon->promotion) }}" class="btn btn-secondary-brand btn-sm">
                        Xem sự kiện {{ $coupon->promotion->name }}
                    </a>
                </p>
            @endif

        </div>

        <p class="mt-4">
            <a href="{{ route('shop.vouchers.index') }}">&larr; Về trang Voucher</a>
        </p>

    </div>
</section>

@endsection
