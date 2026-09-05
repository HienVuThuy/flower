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
