@extends('layouts.admin')

@section('title', 'Phân tích thu mua')

@section('content')

@include('admin.analytics._header', [
    'tieuDe' => 'Thu mua',
    'moTa' => 'Lấy hàng ở đâu thì đáng tiền nhất — tính cả phần hỏng và phần phải trả lại, không chỉ giá trên hoá đơn.',
])

@php
    $tien = fn ($v) => $v === null ? null : \App\Services\Shop\Money::format((string) $v);
    $so = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    $pt = fn ($v) => $v === null ? '—' : number_format($v, 1, ',', '.') . '%';
@endphp

{{--
    NÓI CÁCH ĐỌC TRƯỚC KHI ĐƯA SỐ.

    Hai cột giá đứng cạnh nhau là cả luận điểm của trang. Người đọc lướt
    chỉ nhìn cột quen mắt ("đơn giá") nếu không được bảo cột kia là gì.
--}}
<div class="admin-panel p-4 mb-3">
    <h2 class="h6 fw-bold mb-2">Cách đọc</h2>
    <ul class="mb-0 ps-3 admin-page-subtitle">
        <li><strong>Đơn giá</strong> — giá trên hoá đơn, chia cho số đã lấy về.</li>
        <li><strong>Giá dùng được</strong> — tiền thật sự tốn (đã trừ phần vựa đền) chia cho số còn bán được sau khi bỏ hao hụt và hàng trả lại. <strong>Bảng xếp theo cột này.</strong></li>
        <li>Cùng một loại hoa mua theo hai đơn vị là <strong>hai bảng riêng</strong>: giá mỗi bó và giá mỗi cân không so được với nhau.</li>
        <li>Chỉ tính phiếu nhập <strong>đã ghi sổ</strong>. Nguồn dưới {{ \App\Services\Analytics\PurchasingReport::DU_LIEU_MONG }} lần mua được đánh dấu <em>ít dữ liệu</em> — số vẫn đúng, chỉ là chưa đủ để kết luận.</li>
    </ul>
</div>

@if($thieuNguon['lo_hoa'] > 0 || $thieuNguon['phieu'] > 0)
    <div class="alert alert-warning">
        <strong>Có lần mua không ghi nguồn</strong> nên không so sánh được với nơi nào:
        @if($thieuNguon['lo_hoa'] > 0) {{ $thieuNguon['lo_hoa'] }} lô hoa @endif
        @if($thieuNguon['lo_hoa'] > 0 && $thieuNguon['phieu'] > 0) và @endif
        @if($thieuNguon['phieu'] > 0) {{ $thieuNguon['phieu'] }} phiếu nhập @endif
        trong kỳ. Chúng vẫn hiện trong bảng dưới dòng "Không ghi nguồn".
    </div>
@endif

@foreach([
    ['nhom' => $hoa, 'tieu_de' => 'Hoa tươi — theo loại hoa và đơn vị', 'hao' => true,
     'trong' => 'Chưa có lô hoa nào trong kỳ này.', 'ghi' => route('admin.flower-lots.create'), 'nut' => 'Ghi lô hoa'],
    ['nhom' => $hang, 'tieu_de' => 'Hàng đếm được — chậu, cây, phụ kiện', 'hao' => false,
     'trong' => 'Chưa có phiếu nhập nào đã ghi sổ trong kỳ này.', 'ghi' => route('admin.stock-receipts.create'), 'nut' => 'Lập phiếu nhập'],
] as $phan)
    <h2 class="admin-section-title">{{ $phan['tieu_de'] }}</h2>

    @if($phan['nhom']->isEmpty())
        <div class="admin-panel p-4 mb-4">
            <p class="analytics-empty mb-2">{{ $phan['trong'] }}</p>
            <a data-admin-link href="{{ $phan['ghi'] }}" class="btn btn-sm btn-outline-admin">{{ $phan['nut'] }}</a>
        </div>
    @else
        @foreach($phan['nhom'] as $g)
            <section class="admin-panel p-4 mb-3">
                <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-2">
                    <h3 class="h6 fw-bold mb-0">
                        {{ $g['ten'] }}
                        @if($phan['hao'])
                            <span class="admin-page-subtitle fw-normal">· theo {{ $g['don_vi'] }}</span>
                        @endif
                    </h3>
                    <span class="admin-page-subtitle small">
                        {{ $g['so_lan'] }} lần mua · {{ $g['so_nguon'] }} nguồn
                    </span>
                </div>

                {{--
                    CHỈ NÓI KHI HAI CÂU TRẢ LỜI KHÁC NHAU.

                    Một nguồn, hoặc rẻ nhất cũng là đáng tiền nhất, thì in
                    câu này ra là nói một câu rỗng — và người đọc sẽ học
                    cách bỏ qua nó, đúng lúc nó có điều đáng nói.
                --}}
                @if($g['khac_nhau'])
                    <p class="purchasing-callout mb-3">
                        Rẻ nhất trên hoá đơn là <strong>{{ $g['re_nhat'] }}</strong>,
                        nhưng đáng tiền nhất khi tính phần hỏng và phần trả là
                        <strong>{{ $g['dang_tien_nhat'] }}</strong>.
                    </p>
                @endif

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Nguồn</th>
                                <th scope="col" class="text-end text-nowrap">Lần mua</th>
                                <th scope="col" class="text-end text-nowrap">Đã lấy</th>
                                <th scope="col" class="text-end text-nowrap">Đơn giá</th>
                                @if($phan['hao'])
                                    <th scope="col" class="text-end text-nowrap">Hao hụt</th>
                                @endif
                                <th scope="col" class="text-end text-nowrap">Phải trả lại</th>
                                <th scope="col" class="text-end text-nowrap">Giá dùng được</th>
                                <th scope="col" class="text-end text-nowrap">Lần gần nhất</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($g['nguon'] as $i => $n)
                                <tr>
                                    <td>
                                        {{ $n['ten'] ?? 'Không ghi nguồn' }}
                                        @if($n['mong'])
                                            <span class="badge text-bg-light border ms-1">ít dữ liệu</span>
                                        @endif
                                        @if($n['thieu_gia'] > 0)
                                            <span class="d-block admin-page-subtitle small">
                                                {{ $n['thieu_gia'] }} cái chưa điền giá — không tính vào giá
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ $n['so_lan'] }}</td>
                                    <td class="text-end text-nowrap">{{ $so($n['so_luong']) }}</td>
                                    <td class="text-end text-nowrap">{{ $tien($n['don_gia']) ?? '—' }}</td>
                                    @if($phan['hao'])
                                        <td class="text-end text-nowrap">{{ $pt($n['ti_le_hao']) }}</td>
                                    @endif
                                    <td class="text-end text-nowrap">{{ $pt($n['ti_le_tra']) }}</td>
                                    <td class="text-end text-nowrap fw-bold">
                                        @if($n['gia_dung_duoc'] === null)
                                            {{-- null KHÁC 0: không còn gì dùng được thì không có giá để nói. --}}
                                            <span class="admin-page-subtitle fw-normal">chưa tính được</span>
                                        @else
                                            {{ $tien($n['gia_dung_duoc']) }}
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        @if($n['gan_nhat'])
                                            {{ \Illuminate\Support\Carbon::parse($n['gan_nhat'])->format('d/m/Y') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if(count($g['thang']) > 1)
                    <details class="mt-3">
                        <summary class="small">Đơn giá theo tháng</summary>
                        <table class="table table-sm mb-0 mt-2 purchasing-months">
                            <thead>
                                <tr>
                                    <th scope="col">Tháng</th>
                                    <th scope="col" class="text-end">Lần mua</th>
                                    <th scope="col" class="text-end">Đơn giá bình quân</th>
                                    <th scope="col" class="text-end">So tháng trước</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($g['thang'] as $t)
                                    <tr>
                                        <td>{{ $t['nhan'] }}</td>
                                        <td class="text-end">{{ $t['so_lan'] }}</td>
                                        <td class="text-end text-nowrap">{{ $tien($t['gia']) ?? '—' }}</td>
                                        <td class="text-end text-nowrap">
                                            @if($t['doi'] === null)
                                                —
                                            @else
                                                {{-- Giá nhập TĂNG là tin xấu cho cửa hàng: dấu và chữ nói rõ, không chỉ màu. --}}
                                                <span class="{{ $t['doi'] > 0 ? 'text-danger' : ($t['doi'] < 0 ? 'text-success' : '') }}">
                                                    {{ $t['doi'] > 0 ? 'tăng' : ($t['doi'] < 0 ? 'giảm' : 'giữ') }}
                                                    {{ number_format(abs($t['doi']), 1, ',', '.') }}%
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </details>
                @endif
            </section>
        @endforeach
    @endif
@endforeach

@endsection
