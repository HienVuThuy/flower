@extends('layouts.admin')

@section('title', 'Phân tích đánh giá')

@section('content')

@include('admin.analytics._header', [
    'tieuDe' => 'Đánh giá của khách',
    'moTa' => 'Khách chấm bao nhiêu sao, sản phẩm nào bị chê, và cửa hàng trả lời nhanh cỡ nào. Theo ngày viết đánh giá.',
])

@php $t = $tongQuan; @endphp

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <x-admin.kpi label="Điểm trung bình" :note="$t['so_bai'] . ' bài, ' . $t['co_don_hang'] . ' bài gắn với đơn đã mua'">
            @if($t['trung_binh'] === null)
                {{-- null khác 0 sao: chưa có bài nào để tính. --}}
                <span class="admin-page-subtitle">chưa có đánh giá</span>
            @else
                {{ number_format($t['trung_binh'], 2, ',', '.') }} / 5
            @endif
        </x-admin.kpi>
    </div>
    <div class="col-6 col-lg-3">
        <x-admin.kpi label="Bài 1–2 sao chưa trả lời"
                     :href="$t['thap_chua_tra_loi'] > 0 ? route('admin.reviews.index', ['sao' => 'thap', 'tra_loi' => 'chua']) : null"
                     :note="$t['ti_le_tra_loi_thap'] === null ? 'Không có bài 1–2 sao trong kỳ' : 'Đã trả lời ' . number_format($t['ti_le_tra_loi_thap'], 1, ',', '.') . '% bài 1–2 sao'">
            <span class="{{ $t['thap_chua_tra_loi'] > 0 ? 'text-danger' : '' }}">{{ $t['thap_chua_tra_loi'] }}</span>
        </x-admin.kpi>
    </div>
    <div class="col-6 col-lg-3">
        <x-admin.kpi label="Thời gian trả lời (trung vị)" note="Từ lúc khách viết tới lúc cửa hàng trả lời.">
            @if($t['gio_tra_loi_trung_vi'] === null)
                <span class="admin-page-subtitle">chưa trả lời bài nào</span>
            @elseif($t['gio_tra_loi_trung_vi'] < 48)
                {{ number_format($t['gio_tra_loi_trung_vi'], 1, ',', '.') }} giờ
            @else
                {{ number_format($t['gio_tra_loi_trung_vi'] / 24, 1, ',', '.') }} ngày
            @endif
        </x-admin.kpi>
    </div>
    <div class="col-6 col-lg-3">
        <x-admin.kpi label="Đang ẩn" note="Vẫn được tính vào mọi con số trên trang này.">
            {{ $t['dang_an'] }}
        </x-admin.kpi>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="admin-panel p-4 h-100">
            <x-admin.chart.bars
                title="Phân bố số sao"
                note="Đủ cả 5 mức, kể cả mức không có bài nào."
                empty="Chưa có đánh giá nào trong kỳ."
                :rows="collect($t['phan_bo'])->map(fn ($n, $sao) => ['label' => $sao . ' sao', 'value' => $n])->values()" />
        </div>
    </div>

    <div class="col-lg-7">
        <div class="admin-panel p-4 h-100">
            <h3 class="h6 fw-bold mb-1">Sản phẩm bị chấm thấp nhất</h3>
            <p class="admin-page-subtitle small">
                Chỉ sản phẩm có từ {{ \App\Services\Analytics\ReviewReport::TOI_THIEU_BAI }} bài trở lên —
                một bài 2 sao duy nhất không đủ để xếp trên một sản phẩm có 30 bài.
            </p>

            @if($biChe->isEmpty())
                <p class="analytics-empty mb-0">Chưa sản phẩm nào đủ số bài để xếp hạng.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Sản phẩm</th>
                                <th class="text-end text-nowrap">TB</th>
                                <th class="text-end text-nowrap">Bài</th>
                                <th class="text-end text-nowrap">1–2 sao</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($biChe as $d)
                                <tr>
                                    <td>
                                        @if($d['san_pham'])
                                            <a data-admin-link href="{{ route('admin.reviews.index', ['q' => $d['ten']]) }}">{{ $d['ten'] }}</a>
                                        @else
                                            {{ $d['ten'] }}
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">{{ number_format($d['trung_binh'], 2, ',', '.') }}</td>
                                    <td class="text-end text-nowrap">{{ $d['so_bai'] }}</td>
                                    <td class="text-end {{ $d['so_bai_thap'] > 0 ? 'text-danger' : '' }}">{{ $d['so_bai_thap'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

<div class="admin-panel p-4 mb-4">
    <h3 class="h6 fw-bold mb-2">Điểm trung bình theo tháng</h3>

    @if($theoThang->isEmpty())
        <p class="analytics-empty mb-0">Chưa có dữ liệu trong kỳ này.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr><th>Tháng</th><th class="text-end text-nowrap">Số bài</th><th class="text-end text-nowrap">Trung bình</th></tr>
                </thead>
                <tbody>
                    @foreach($theoThang as $m)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $m['thang'])->format('m/Y') }}</td>
                            <td class="text-end text-nowrap">{{ $m['so_bai'] }}</td>
                            <td class="text-end text-nowrap">{{ number_format($m['trung_binh'], 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection
