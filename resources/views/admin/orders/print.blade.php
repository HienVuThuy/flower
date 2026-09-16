{{-- PHIẾU IN CHO MỘT ĐƠN: soạn hàng + giao hàng. --}}
@php
    $diaChi = collect([
        $order->shipping_address,
        $order->shipping_ward,
        $order->shipping_district,
        $order->shipping_province,
    ])->filter()->implode(', ');

    $daTra = $order->payment_status === \App\Enums\PaymentStatus::Paid;

    $tien = fn ($v) => \App\Services\Shop\Money::format((string) $v);
    $cuaHang = \App\Services\Shop\StoreProfile::name();
    $hotline = \App\Services\Shop\StoreProfile::hotline();
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>In đơn {{ $order->order_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #111; background: #eee; font-size: 14px; }
        .thanh-cong-cu { display: flex; gap: 8px; justify-content: center; padding: 12px; }
        .thanh-cong-cu button, .thanh-cong-cu a { font: inherit; padding: 8px 16px; border: 1px solid #333; background: #fff; color: #111; border-radius: 6px; cursor: pointer; text-decoration: none; }
        .to { width: 190mm; min-height: 130mm; margin: 0 auto 12px; background: #fff; padding: 12mm; }
        .dau { display: flex; justify-content: space-between; align-items: baseline; border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 10px; }
        .dau h1 { font-size: 20px; margin: 0; }
        .ma { font-size: 18px; font-weight: bold; letter-spacing: .5px; }
        .phu { color: #444; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border-bottom: 1px solid #bbb; padding: 6px 4px; text-align: left; vertical-align: top; }
        th { font-size: 12px; text-transform: uppercase; color: #444; }
        .so { text-align: right; white-space: nowrap; }
        .o-sl { font-size: 18px; font-weight: bold; }
        .khung { border: 1px solid #111; padding: 8px 10px; margin-top: 10px; }
        .khung strong { display: inline-block; min-width: 90px; }
        .thu-ho { border: 3px solid #111; padding: 10px; margin-top: 12px; font-size: 22px; font-weight: bold; text-align: center; }
        .ghi-chu { border: 2px dashed #111; padding: 8px 10px; margin-top: 10px; font-size: 15px; }
        .o-tick { display: inline-block; width: 14px; height: 14px; border: 1px solid #111; vertical-align: middle; }
        @media print {
            body { background: #fff; }
            .thanh-cong-cu { display: none; }
            .to { margin: 0; width: auto; min-height: 0; padding: 0; page-break-after: always; }
            .to:last-child { page-break-after: auto; }
            @page { size: A5; margin: 10mm; }
        }
    </style>
</head>
<body>

<div class="thanh-cong-cu">
    <button type="button" onclick="window.print()">In</button>
    <a href="{{ route('admin.orders.show', $order) }}">Về đơn hàng</a>
</div>

<section class="to" aria-label="Phiếu soạn hàng">
    <div class="dau">
        <h1>Phiếu soạn hàng</h1>
        <span class="ma">{{ $order->order_number }}</span>
    </div>

    <p class="phu">
        Đặt lúc <x-site.time :at="$order->created_at" />
        @if($order->delivery_date)
            &middot; <strong>Giao ngày {{ $order->delivery_date->format('d/m/Y') }}</strong>
        @endif
        &middot; {{ $order->status->label() }}
    </p>

    @if($order->delivery_note)
        <div class="ghi-chu"><strong>Khách dặn:</strong> {{ $order->delivery_note }}</div>
    @endif

    <table>
        <thead>
            <tr>
                <th scope="col" style="width:24px">Xong</th>
                <th scope="col">Mặt hàng</th>
                <th scope="col" class="so">SL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td><span class="o-tick"></span></td>
                    <td>
                        @if($item->is_gift)<strong>[Quà miễn phí]</strong> @endif{{ $item->product_name }}
                        @if($item->variant_name)
                            <div class="phu">{{ $item->variant_name }}</div>
                        @endif
                    </td>
                    <td class="so o-sl">{{ $item->quantity }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="phu" style="margin-top:10px">Tổng {{ $order->totalQuantity() }} món.</p>
</section>

<section class="to" aria-label="Phiếu giao hàng">
    <div class="dau">
        <h1>Phiếu giao hàng</h1>
        <span class="ma">{{ $order->order_number }}</span>
    </div>

    <div class="khung">
        <div><strong>Người nhận:</strong> {{ $order->recipient_name }}</div>
        <div><strong>Điện thoại:</strong> {{ $order->recipient_phone }}</div>
        <div><strong>Địa chỉ:</strong> {{ $diaChi }}</div>
        @if($order->delivery_date)
            <div><strong>Giao ngày:</strong> {{ $order->delivery_date->format('d/m/Y') }}</div>
        @endif
        @if($order->ghn_order_code)
            <div><strong>Mã GHN:</strong> {{ $order->ghn_order_code }}</div>
        @endif
    </div>

    @if($order->delivery_note)
        <div class="ghi-chu"><strong>Khách dặn:</strong> {{ $order->delivery_note }}</div>
    @endif

    <div class="thu-ho">
        @if($daTra)
            ĐÃ THANH TOÁN — KHÔNG THU TIỀN
        @else
            THU CỦA KHÁCH: {{ $tien($order->grand_total) }}
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th scope="col">Mặt hàng</th>
                <th scope="col" class="so">SL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->product_name }}@if($item->variant_name) — {{ $item->variant_name }}@endif</td>
                    <td class="so">{{ $item->quantity }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="phu" style="margin-top:12px">
        Gửi từ {{ $cuaHang }}@if($hotline) &middot; {{ $hotline }}@endif
    </p>
</section>

</body>
</html>
