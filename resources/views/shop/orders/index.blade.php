@extends('layouts.app')

@section('title', 'Đơn hàng của tôi')

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="[['label' => 'Đơn hàng của tôi']]" />

        <h1 class="text-h2 mb-4">Đơn hàng của tôi</h1>

        @if($orders->isEmpty())

            <x-site.empty-state
                title="Bạn chưa có đơn hàng nào"
                text="Các đơn bạn đặt sẽ được lưu lại ở đây."
            >
                <x-slot:actions>
                    <a href="{{ route('shop.products.index') }}" class="btn btn-primary-brand">
                        Xem sản phẩm
                    </a>
                </x-slot:actions>
            </x-site.empty-state>

        @else

            <div class="order-history">
                @foreach($orders as $order)
                    <a href="{{ route('shop.orders.show', $order) }}" class="order-history__row">

                        <div class="order-history__main">
                            <div class="order-history__number">{{ $order->order_number }}</div>
                            <div class="order-history__meta">
                                {{ $order->created_at->format('d/m/Y H:i') }}
                                &middot; {{ $order->items_count }} sản phẩm

                                {{--
                                    Cả dòng đã là một thẻ <a>, nên KHÔNG
                                    đặt nút "Thanh toán lại" ở đây: một
                                    thẻ <a> lồng trong thẻ <a> là HTML
                                    hỏng, trình duyệt tự gỡ và nút mất
                                    tác dụng. Chỉ báo trạng thái; nút nằm
                                    ở trang chi tiết đơn.
                                --}}
                                @if($order->payment_method === \App\Enums\PaymentMethod::Momo
                                    && $order->payment_status === \App\Enums\PaymentStatus::Unpaid
                                    && ! $order->status->isFinal())
                                    &middot;
                                    <span class="order-history__unpaid">chưa thanh toán</span>
                                @endif
                            </div>
                        </div>

                        <span class="status-pill status-pill--{{ $order->status->badge() }}">
                            {{ $order->status->label() }}
                        </span>

                        <div class="order-history__total">
                            <x-site.money :amount="(float) $order->grand_total" />
                        </div>

                    </a>
                @endforeach
            </div>

            {{ $orders->links() }}

        @endif

    </div>
</section>

@endsection
