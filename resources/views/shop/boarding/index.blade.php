@extends('layouts.app')

@section('title', 'Chăm cây hộ')
@section('meta_description', 'Gửi cây cho cửa hàng chăm khi bạn đi xa, sau Tết hay khi cây khó chăm — nhận lại đúng hẹn, đúng dịp.')

@section('content')

@php
    use App\Enums\BoardingMode;
    use App\Services\Shop\Money;

    $cheDo = old('mode', BoardingMode::Thang->value);
    $rateChon = old('boarding_rate_id', optional($cacGia->first(fn ($r) => $r->care_difficulty?->value === $giaGoiY) ?? $cacGia->first())->id);
@endphp

<section class="section">
    <div class="container-shop">

        <x-site.breadcrumb :items="[['label' => 'Chăm cây hộ']]" />

        <div class="row g-4 g-lg-5">

            <div class="col-lg-5">
                <span class="text-label d-block mb-2">Dịch vụ</span>
                <h1 class="text-h1 mb-3 text-balance">Chăm cây hộ</h1>
                <p class="mb-3">
                    Đi công tác dài ngày, qua Tết không biết giữ cây đào, hay cây bonsai quá khó chăm?
                    Gửi cửa hàng chăm, đến hẹn (hoặc đúng dịp) nhận lại cây khoẻ.
                </p>

                <ol class="boarding-steps">
                    <li>Bạn gửi yêu cầu, xem ngay giá tạm tính.</li>
                    <li>Cửa hàng xác nhận, hẹn ngày nhận cây.</li>
                    <li>Trong thời gian gửi, cửa hàng gửi ảnh và ghi chú chăm sóc vào phiếu của bạn.</li>
                    <li>Đến hẹn nhận lại cây. Chọn "Nhận lại đúng dịp" thì năm sau có thể lặp lại.</li>
                </ol>

                @if($cacGia->isNotEmpty())
                    <h2 class="text-h4 mt-4 mb-2">Bảng giá</h2>
                    <div class="boarding-rates" data-bang-gia-cham-ho>
                        @foreach($cacGia as $gia)
                            <div class="boarding-rates__row">
                                <div>
                                    <strong>{{ $gia->name }}</strong>
                                    @if($gia->care_difficulty)
                                        <span class="text-caption">· độ khó {{ mb_strtolower($gia->care_difficulty->label()) }}</span>
                                    @endif
                                    @if($gia->description)
                                        <span class="d-block text-caption">{{ $gia->description }}</span>
                                    @endif
                                </div>
                                <div class="text-end">
                                    <span class="d-block">{{ Money::format($gia->monthly_price) }}<span class="text-caption"> / tháng</span></span>
                                    <span class="d-block text-caption">{{ Money::format($gia->giaNam()) }} / năm</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <ul class="boarding-rules text-caption">
                        <li>Tính tiền theo số tháng thực gửi; lố dưới {{ \App\Services\Boarding\BoardingPricing::NGAY_AN_HAN }} ngày không tính thêm tháng. Đủ 12 tháng thì áp giá năm.</li>
                        <li>Nhận cây sớm hơn hẹn thì tính lại theo thời gian thực gửi.</li>
                        @if(($phiGap = \App\Services\Shop\ThamSoKinhDoanh::so('kinh_doanh.cham_ho.phi_gap')) > 0)
                            <li>Cần nhận gấp (báo trước dưới {{ \App\Services\Shop\ThamSoKinhDoanh::so('kinh_doanh.cham_ho.bao_gap_ngay') }} ngày): thêm {{ Money::format($phiGap) }}.</li>
                        @endif
                        <li>Cửa hàng đến lấy và trả tận nơi: phí báo khi xác nhận, tuỳ quãng đường và cỡ cây.</li>
                        <li>Thanh toán trực tiếp khi giao nhận cây (tiền mặt hoặc chuyển khoản){{ app(\App\Services\Payment\MomoGateway::class)->configured() ? ', hoặc trả online qua MoMo sau khi cửa hàng xác nhận' : '' }}; mọi khoản đều ghi trong phiếu.</li>
                        <li>Muốn điền tay tại cửa hàng? <a href="{{ route('shop.boarding.blank') }}" target="_blank" rel="noopener">In phiếu trắng</a> — cùng mẫu với phiếu trên web.</li>
                    </ul>
                @endif
            </div>

            <div class="col-lg-7">
                @if($cacGia->isEmpty())
                    <x-site.empty-state title="Cửa hàng chưa mở nhận chăm cây hộ"
                                        text="Khi có bảng giá, bạn sẽ gửi yêu cầu được ngay tại đây." />
                @elseif(! auth()->check())
                    <div class="surface-card p-4">
                        <p class="mb-3">Đăng nhập để gửi yêu cầu và theo dõi cây của bạn trong thời gian gửi.</p>
                        <a href="{{ route('login') }}" class="btn btn-primary-brand">Đăng nhập</a>
                    </div>
                @else
                    <form method="POST" action="{{ route('shop.boarding.store') }}" enctype="multipart/form-data"
                          class="surface-card p-4" data-cham-ho data-bao-gia-url="{{ route('shop.boarding.quote') }}">
                        @csrf

                        @if($sanPham)
                            <input type="hidden" name="product_id" value="{{ $sanPham->id }}">
                        @endif

                        <h2 class="text-h4 mb-3">Gửi yêu cầu</h2>

                        @include('shop.boarding._fields', [
                            'tenCayMacDinh' => $sanPham?->name,
                            'sdtMacDinh' => auth()->user()->addresses()->where('is_default', true)->value('recipient_phone'),
                        ])

                        <button type="submit" class="btn btn-primary-brand w-100 mt-3">Gửi yêu cầu</button>
                        <p class="text-caption mt-2 mb-0">Chưa phải trả tiền lúc này. Cửa hàng xác nhận trước, rồi bạn trả trực tiếp khi giao nhận cây{{ app(\App\Services\Payment\MomoGateway::class)->configured() ? ' hoặc trả online' : '' }}.</p>
                    </form>

                    <p class="mt-3"><a href="{{ route('shop.boarding.mine') }}">Xem các cây bạn đã gửi</a></p>
                @endif
            </div>

        </div>
    </div>
</section>

@endsection
