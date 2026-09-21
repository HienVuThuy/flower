{{-- PHIẾU GỬI CÂY CHĂM HỘ — một mẫu cho cả hai kênh:
     có $phieu thì in đúng phiếu online / phiếu đã lập; không có thì in PHIẾU TRẮNG để khách viết tay tại quầy.
     Thứ tự ô trùng với form (_fields.blade.php) để cửa hàng chép từ phiếu giấy lên hệ thống. --}}
@php
    use App\Enums\BoardingHandover;
    use App\Enums\BoardingMode;
    use App\Services\Boarding\BoardingPricing;
    use App\Services\Shop\Money;
    use App\Services\Shop\StoreProfile;
    use App\Services\Shop\ThamSoKinhDoanh;

    $trang = $phieu === null;
    $o = fn (bool $chon) => '<span class="o' . ($chon ? ' o--x' : '') . '"></span>';
    $gt = fn ($v) => $trang || $v === null || $v === '' ? '<span class="dong"></span>' : e($v);
    $ngay = fn ($d) => $trang || ! $d ? '<span class="dong dong--ngan"></span>' : $d->format('d/m/Y');
    $tien = fn ($v) => $trang ? '<span class="dong dong--ngan"></span>' : Money::format((string) $v);
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $trang ? 'Phiếu gửi cây chăm hộ (mẫu trắng)' : 'Phiếu chăm hộ ' . $phieu->code }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #111; background: #eee; font-size: 13px; line-height: 1.45; }
        .cong-cu { display: flex; gap: 8px; justify-content: center; padding: 12px; }
        .cong-cu button, .cong-cu a { font: inherit; padding: 8px 16px; border: 1px solid #333; background: #fff; color: #111; border-radius: 6px; cursor: pointer; text-decoration: none; }
        .to { width: 190mm; margin: 0 auto 12px; background: #fff; padding: 10mm 12mm; }
        .dau { display: flex; justify-content: space-between; gap: 12px; border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 8px; }
        .dau h1 { font-size: 19px; margin: 0 0 2px; letter-spacing: .3px; }
        .phu { color: #444; font-size: 11.5px; }
        .ma { text-align: right; font-size: 13px; }
        .ma strong { font-size: 16px; }
        h2 { font-size: 13px; text-transform: uppercase; margin: 10px 0 4px; border-bottom: 1px solid #999; padding-bottom: 2px; }
        .hang { display: grid; grid-template-columns: 34mm 1fr; gap: 2px 8px; align-items: end; }
        .hang--2 { grid-template-columns: 34mm 1fr 30mm 1fr; }
        .nhan { color: #333; }
        .dong { display: inline-block; width: 100%; min-width: 30mm; border-bottom: 1px dotted #555; height: 1.2em; }
        .dong--ngan { width: 32mm; min-width: 0; }
        .o { display: inline-block; width: 11px; height: 11px; border: 1.2px solid #111; vertical-align: -1px; margin-right: 5px; position: relative; }
        .o--x::after { content: ''; position: absolute; inset: 2px; background: #111; }
        .chon { display: grid; grid-template-columns: 1fr 1fr; gap: 3px 12px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 4px; border-bottom: 1px solid #ddd; vertical-align: bottom; }
        td.so { text-align: right; white-space: nowrap; width: 40mm; }
        tr.tong td { font-weight: bold; border-top: 1.5px solid #111; border-bottom: none; }
        .gia td { font-size: 12px; }
        .dieu-khoan { font-size: 11px; color: #333; margin: 0; padding-left: 16px; }
        .ky { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 14px; text-align: center; }
        .ky .cho { height: 22mm; }
        @media print {
            body { background: #fff; }
            .cong-cu { display: none; }
            .to { margin: 0; padding: 8mm 10mm; width: auto; }
        }
    </style>
</head>
<body>

<div class="cong-cu">
    <button type="button" onclick="window.print()">In phiếu</button>
    <a href="{{ $quayLai }}">Quay lại</a>
</div>

<div class="to" data-phieu-in="{{ $trang ? 'trang' : $phieu->code }}">
    <div class="dau">
        <div>
            <h1>PHIẾU GỬI CÂY CHĂM HỘ</h1>
            <div class="phu">
                {{ StoreProfile::name() }}
                @if(StoreProfile::hotline()) · {{ StoreProfile::hotline() }} @endif
                @if(StoreProfile::address()) · {{ StoreProfile::address() }} @endif
            </div>
        </div>
        <div class="ma">
            Mã phiếu: <strong>{!! $trang ? '<span class="dong dong--ngan"></span>' : e($phieu->code) !!}</strong><br>
            <span class="phu">
                {!! $o(! $trang && $phieu->source === \App\Enums\BoardingSource::Online) !!}Gửi online
                {!! $o(! $trang && $phieu->source === \App\Enums\BoardingSource::TaiQuay) !!}Tại quầy
            </span>
        </div>
    </div>

    <h2>1. Khách hàng</h2>
    <div class="hang hang--2">
        <span class="nhan">Họ tên</span><span>{!! $gt($trang ? null : $phieu->tenKhach()) !!}</span>
        <span class="nhan">Số điện thoại</span><span>{!! $gt($phieu?->contact_phone) !!}</span>
        <span class="nhan">Địa chỉ</span><span style="grid-column: span 3">{!! $gt($phieu?->address) !!}</span>
    </div>

    <h2>2. Cây gửi chăm</h2>
    <div class="hang">
        <span class="nhan">Tên / mô tả cây</span><span>{!! $gt($phieu?->plant_name) !!}</span>
        <span class="nhan">Loại giá áp dụng</span><span>{!! $gt($phieu?->rate?->name) !!}</span>
        <span class="nhan">Tình trạng lúc gửi</span><span>{!! $gt($phieu?->plant_note) !!}</span>
    </div>

    <h2>3. Thời gian gửi</h2>
    <div class="chon">
        @foreach(BoardingMode::cases() as $m)
            <span>
                {!! $o(! $trang && $phieu->mode === $m) !!}{{ $m->label() }}
                @if($m === BoardingMode::Thang) — {!! $trang ? '<span class="dong dong--ngan" style="width:12mm"></span>' : ($phieu->months ?? '…') !!} tháng @endif
                @if($m === BoardingMode::Nam) — {!! $trang ? '<span class="dong dong--ngan" style="width:12mm"></span>' : ($phieu->years ?? '…') !!} năm @endif
                @if($m === BoardingMode::TheoDip) — {!! $trang ? '<span class="dong dong--ngan"></span>' : e($phieu->window?->name ?? '…') !!} @endif
            </span>
        @endforeach
        <span>{!! $o(! $trang && $phieu->repeat_yearly) !!}Lặp lại mỗi năm (theo dịp)</span>
    </div>
    <div class="hang hang--2" style="margin-top: 6px">
        <span class="nhan">Ngày gửi cây</span><span>{!! $ngay($phieu?->received_on ?? $phieu?->drop_off_on) !!}</span>
        <span class="nhan">Ngày nhận lại</span><span>{!! $ngay($phieu?->returned_on ?? $phieu?->return_on) !!}</span>
    </div>

    <h2>4. Giao nhận cây</h2>
    <div class="chon">
        @foreach(BoardingHandover::cases() as $h)
            <span>{!! $o(! $trang && $phieu->handover === $h) !!}{{ $h->label() }}</span>
        @endforeach
    </div>

    <h2>5. Chi phí</h2>
    <table>
        <tr><td>{{ ! $trang && ! $phieu->daChotGia() ? 'Giá tham khảo (chưa chốt)' : 'Giá chốt cho cây' }}</td><td class="so">{!! $trang ? '<span class="dong dong--ngan"></span>' : Money::format((string) $phieu->monthly_price) . '/tháng · ' . Money::format((string) $phieu->yearly_price) . '/năm' !!}</td></tr>
        <tr><td>Tiền chăm {{ ! $trang && ! $phieu->returned_on ? '(tạm tính)' : '' }}</td><td class="so">{!! $tien($phieu?->care_amount) !!}</td></tr>
        @if($trang)
            <tr><td>Việc làm thêm (thay chậu, tạo dáng…) — báo giá riêng từng việc</td><td class="so"><span class="dong dong--ngan"></span></td></tr>
        @else
            @foreach($phieu->extras->filter(fn ($x) => $x->status->tinhTien()) as $x)
                <tr><td>Làm thêm: {{ $x->title }}</td><td class="so">{{ Money::format((string) $x->price) }}</td></tr>
            @endforeach
        @endif
        <tr><td>Phí đến lấy / trả cây</td><td class="so">{!! $tien($phieu?->handover_fee) !!}</td></tr>
        <tr><td>Phí nhận gấp</td><td class="so">{!! $tien($phieu?->rush_fee) !!}</td></tr>
        <tr><td>Điều chỉnh {{ ! $trang && $phieu->adjustment_reason ? '— ' . $phieu->adjustment_reason : '' }}</td><td class="so">{!! $tien($phieu?->adjustment) !!}</td></tr>
        <tr class="tong"><td>Tổng</td><td class="so">{!! $trang ? '<span class="dong dong--ngan"></span>' : Money::format($phieu->tongTien()) !!}</td></tr>
        <tr><td>Đã thanh toán {!! $trang ? '— ' . $o(false) . 'Tiền mặt ' . $o(false) . 'Chuyển khoản ' . $o(false) . 'MoMo' : '' !!}</td><td class="so">{!! $tien($phieu?->paid_amount) !!}</td></tr>
        @if(! $trang)
            @foreach($phieu->payments as $tra)
                <tr class="gia"><td>&nbsp;&nbsp;{{ $tra->paid_at->format('d/m/Y') }} · {{ $tra->method->label() }}</td><td class="so">{{ Money::format((string) $tra->amount) }}</td></tr>
            @endforeach
        @endif
    </table>

    @if($trang && $cacGia->isNotEmpty())
        <h2>Bảng giá tham khảo</h2>
        <table class="gia">
            @foreach($cacGia as $gia)
                <tr><td>{{ $gia->name }}{{ $gia->care_difficulty ? ' · độ khó ' . mb_strtolower($gia->care_difficulty->label()) : '' }}</td>
                    <td class="so">{{ Money::format((string) $gia->monthly_price) }}/tháng · {{ Money::format($gia->giaNam()) }}/năm</td></tr>
            @endforeach
        </table>
    @endif

    <h2>Điều khoản</h2>
    <ul class="dieu-khoan">
        <li>Bảng giá là giá tham khảo; cửa hàng xem cây rồi chốt giá riêng cho từng cây. Việc làm thêm báo giá riêng, khách đồng ý mới tính.</li>
        <li>Tính tiền theo số tháng thực gửi; lố dưới {{ BoardingPricing::NGAY_AN_HAN }} ngày không tính thêm tháng; đủ 12 tháng áp giá năm.</li>
        <li>Nhận cây sớm hơn hẹn thì tính lại theo thời gian thực gửi; tiền trả thừa cửa hàng hoàn lại.</li>
        @if(ThamSoKinhDoanh::so('kinh_doanh.cham_ho.phi_gap') > 0)
            <li>Cần nhận gấp (báo trước dưới {{ ThamSoKinhDoanh::so('kinh_doanh.cham_ho.bao_gap_ngay') }} ngày): thêm {{ Money::format(ThamSoKinhDoanh::so('kinh_doanh.cham_ho.phi_gap')) }}.</li>
        @endif
        <li>Thanh toán trực tiếp khi giao nhận cây, hoặc trả online qua MoMo trên trang phiếu sau khi cửa hàng xác nhận.</li>
        <li>Phiếu giấy và phiếu trên web là một: mọi khoản thu, ảnh và ghi chú chăm sóc đều ghi theo mã phiếu.</li>
    </ul>

    <div class="ky">
        <div>Khách hàng<br><span class="phu">(ký, ghi rõ họ tên)</span><div class="cho"></div></div>
        <div>Cửa hàng<br><span class="phu">(ký, ghi rõ họ tên)</span><div class="cho"></div></div>
    </div>
    <p class="phu" style="text-align:right; margin: 0">In ngày {{ now(\App\Services\Time\Gio::mui())->format('d/m/Y H:i') }}</p>
</div>

</body>
</html>
