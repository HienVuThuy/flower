@extends('layouts.admin')

@section('title', 'Sổ thu chi')

@php
    $tien = fn ($v) => \App\Services\Shop\Money::format((string) $v);
    $dt = $bao['dong_tien'];
    $lai = $bao['lai'];
    $am = fn ($v) => bccomp((string) $v, '0', 2) < 0;
@endphp

@section('content')

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="admin-page-title">Sổ thu chi tháng {{ $nhanThang }}</h1>
        <p class="admin-page-subtitle mb-0">
            Tiền vào, tiền nhập hàng, hoàn tiền đọc thẳng từ đơn và phiếu nhập. Chỉ cần ghi những khoản hệ thống chưa có: lương, mặt bằng, điện nước, server, vật tư.
        </p>
    </div>

    <div class="d-flex flex-wrap gap-2 align-items-center">
        <a data-admin-link href="{{ route('admin.expenses.index', ['thang' => $thangTruoc]) }}" class="btn btn-sm btn-outline-admin">Tháng trước</a>
        @if($thangSau)
            <a data-admin-link href="{{ route('admin.expenses.index', ['thang' => $thangSau]) }}" class="btn btn-sm btn-outline-admin">Tháng sau</a>
        @endif
        <a data-admin-link href="{{ route('admin.expenses.create', ['thang' => $thang]) }}" class="btn btn-primary-brand">Ghi khoản chi</a>
    </div>
</div>

<div class="row g-3 mb-4">
    {{-- DÒNG TIỀN: tiền trong két tăng hay giảm. --}}
    <div class="col-lg-6">
        <div class="admin-panel p-4 h-100">
            <h2 class="h6 fw-bold mb-1">Dòng tiền</h2>
            <p class="admin-page-subtitle small">Tiền thật đi vào và đi ra trong tháng.</p>

            <dl class="admin-detail-list mb-0">
                <div><dt>Tiền vào từ đơn đã giao (sau hoàn tiền)</dt><dd>{{ $tien($dt['tien_vao']) }}</dd></div>
                <div><dt>Tiền nhập hàng và lô hoa</dt><dd>− {{ $tien($dt['thu_mua']) }}</dd></div>
                <div><dt>Chi phí vận hành</dt><dd>− {{ $tien($dt['chi_phi']) }}</dd></div>
                <div class="fw-bold" data-dong="chenh-dong-tien">
                    <dt>Chênh lệch</dt>
                    <dd class="{{ $am($dt['chenh']) ? 'text-danger' : '' }}">{{ $tien($dt['chenh']) }}</dd>
                </div>
            </dl>

            @if($am($dt['chenh']) && bccomp($dt['thu_mua'], '0', 2) > 0)
                <p class="admin-page-subtitle small mt-2 mb-0">
                    Âm không có nghĩa là lỗ: hàng nhập tháng này có thể bán dần những tháng sau. Xem lãi ở bên cạnh.
                </p>
            @endif
        </div>
    </div>

    {{-- LÃI RÒNG ƯỚC TÍNH: cửa hàng có lời không. --}}
    <div class="col-lg-6">
        <div class="admin-panel p-4 h-100">
            <h2 class="h6 fw-bold mb-1">Lãi ròng ước tính</h2>
            <p class="admin-page-subtitle small">Lãi trên hàng đã bán, trừ mọi chi phí có số.</p>

            <dl class="admin-detail-list mb-0">
                <div><dt>Lãi gộp hàng (phần có giá vốn)</dt><dd>{{ $tien($lai['lai_gop_hang']) }}</dd></div>
                <div>
                    <dt>Lãi gộp hoa tươi (lô đã đóng)</dt>
                    <dd>
                        @if($lai['lai_gop_hoa'] === null)
                            <span class="admin-page-subtitle">chưa đóng lô nào</span>
                        @else
                            {{ $tien($lai['lai_gop_hoa']) }}
                        @endif
                    </dd>
                </div>
                <div><dt>Chi phí vận hành</dt><dd>− {{ $tien($lai['chi_phi']) }}</dd></div>
                <div><dt>Cửa hàng bù ship</dt><dd>− {{ $tien($lai['bu_ship']) }}</dd></div>
                <div><dt>Hoàn tiền cho đơn đã giao</dt><dd>− {{ $tien($lai['hoan_tien']) }}</dd></div>
                <div data-dong="chi-phi-qua"><dt>Giá vốn quà tặng</dt><dd>− {{ $tien($lai['chi_phi_qua']) }}</dd></div>
                <div class="fw-bold" data-dong="lai-rong">
                    <dt>Lãi ròng ước tính</dt>
                    <dd class="{{ $am($lai['lai_rong']) ? 'text-danger' : '' }}">{{ $tien($lai['lai_rong']) }}</dd>
                </div>
            </dl>

            {{-- Nói ra khi con số cao hơn sự thật — không để nó đứng một mình. --}}
            @php
                $canhBao = [];
                if ($lai['ti_le_phu'] !== null && $lai['ti_le_phu'] < 100) {
                    $canhBao[] = 'Mới ' . $lai['ti_le_phu'] . '% doanh thu hàng có giá vốn — phần còn lại chưa trừ vốn, nên lãi thật thấp hơn.';
                }
                if ($lai['co_lo_hoa_mo']) {
                    $canhBao[] = 'Còn lô hoa chưa đóng — lãi hoa của chúng chưa tính.';
                }
                if ($bao['dong_tien']['chi_phi'] === '0.00') {
                    $canhBao[] = 'Tháng này chưa ghi khoản chi nào — lãi ròng đang chưa trừ lương, mặt bằng.';
                }
            @endphp
            @if($canhBao)
                <ul class="admin-page-subtitle small mt-2 mb-0 ps-3" data-canh-bao-lai>
                    @foreach($canhBao as $c)
                        <li>{{ $c }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>

@if($soCoDinhChuaChep > 0)
    <div class="admin-panel p-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>Tháng trước có <strong>{{ $soCoDinhChuaChep }}</strong> khoản cố định chưa có trong tháng này.</span>
        <form method="POST" action="{{ route('admin.expenses.copy-fixed') }}">
            @csrf
            <input type="hidden" name="thang" value="{{ $thang }}">
            <button type="submit" class="btn btn-sm btn-outline-admin">Chép sang tháng này</button>
        </form>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-4">
        <div class="admin-panel p-4">
            <h2 class="h6 fw-bold mb-3">Chi phí theo loại</h2>
            @if($bao['chi_phi_theo_loai'] === [])
                <p class="admin-page-subtitle mb-0">Chưa ghi khoản nào.</p>
            @else
                <dl class="admin-detail-list mb-0">
                    @foreach($bao['chi_phi_theo_loai'] as $loai => $soTien)
                        <div>
                            <dt>{{ \App\Enums\ExpenseCategory::from($loai)->label() }}</dt>
                            <dd>{{ $tien($soTien) }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </div>
    </div>

    <div class="col-lg-8">
        <div class="admin-panel">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Ngày</th>
                            <th scope="col">Nội dung</th>
                            <th scope="col">Loại</th>
                            <th scope="col" class="text-end">Số tiền</th>
                            <th scope="col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cacKhoan as $k)
                            <tr data-khoan="{{ $k->id }}">
                                <td class="text-nowrap">
                                    {{ $k->spent_on->format('d/m') }}
                                    @if($k->spent_on->toDateString() > $homNay)
                                        <span class="d-block admin-page-subtitle small">chưa tới ngày</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $k->description }}
                                    @if($k->is_fixed)
                                        <span class="badge text-bg-secondary">cố định</span>
                                    @endif
                                    @if($k->note)
                                        <span class="d-block admin-page-subtitle small fst-italic">{{ $k->note }}</span>
                                    @endif
                                    @if($k->created_by_name)
                                        <span class="d-block admin-page-subtitle small">Ghi bởi {{ $k->created_by_name }}</span>
                                    @endif
                                </td>
                                <td>{{ $k->category->label() }}</td>
                                <td class="text-end text-nowrap">{{ $tien($k->amount) }}</td>
                                <td class="text-end text-nowrap">
                                    <a data-admin-link href="{{ route('admin.expenses.edit', $k) }}" class="btn btn-sm btn-outline-admin">Sửa</a>
                                    <form method="POST" action="{{ route('admin.expenses.destroy', $k) }}" class="d-inline"
                                          onsubmit="return confirm('Xoá khoản chi này?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Xoá</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            {{-- Không dùng x-admin.empty-row: nó coi `?thang=` là đang lọc và báo
                                 "không khớp bộ lọc" — trong khi tháng đó thật sự chưa ghi gì. --}}
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">Tháng này chưa ghi khoản chi nào.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection
