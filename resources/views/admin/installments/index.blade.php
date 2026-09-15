@extends('layouts.admin')

@section('title', 'Trả góp')

@section('content')

<div class="mb-4">
    <h1 class="admin-page-title">Trả góp</h1>
    <p class="admin-page-subtitle mb-0">
        Trả góp trước khi giao: cửa hàng giữ hàng, khách trả trước một phần rồi trả nốt theo kỳ, giao khi đã trả đủ.
        Quá hạn một kỳ vượt số ngày ân hạn thì đơn tự huỷ, hàng về kho và tiền đã trả thành khoản phải hoàn.
    </p>
</div>

<div class="row g-4">

    <div class="col-xl-8">
        <nav class="analytics-tabs mb-3" aria-label="Lọc theo tình trạng">
            <a data-admin-link href="{{ route('admin.installments.index') }}" class="analytics-tabs__tab {{ $loc === null ? 'is-active' : '' }}">Tất cả</a>
            @foreach(\App\Enums\InstallmentStatus::cases() as $tt)
                <a data-admin-link href="{{ route('admin.installments.index', ['trang_thai' => $tt->value]) }}"
                   class="analytics-tabs__tab {{ $loc === $tt ? 'is-active' : '' }}">
                    {{ $tt->label() }} ({{ (int) ($dem[$tt->value] ?? 0) }})
                </a>
            @endforeach
        </nav>

        <div class="admin-panel p-0">
            @if($keHoach->isEmpty())
                <p class="p-4 mb-0 admin-page-subtitle">Chưa có kế hoạch trả góp nào.</p>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Đơn</th>
                                <th>Khách</th>
                                <th class="text-end">Đã thu / Tổng</th>
                                <th>Kỳ kế tiếp</th>
                                <th>Tình trạng</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($keHoach as $kh)
                                @php
                                    $kyToi = $kh->kyKeTiep();
                                @endphp
                                <tr data-ke-hoach="{{ $kh->id }}">
                                    <td>
                                        @if($kh->order)
                                            <a data-admin-link href="{{ route('admin.orders.show', $kh->order) }}">{{ $kh->order->order_number }}</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $kh->user?->name ?? $kh->order?->recipient_name ?? '—' }}</td>
                                    <td class="text-end">
                                        <x-site.money :amount="$kh->daTra()" /> / <x-site.money :amount="(string) $kh->total_amount" />
                                    </td>
                                    <td>
                                        @if($kh->dangTra() && $kyToi)
                                            {{ $kyToi->nhan() }} · {{ $kyToi->due_on->format('d/m/Y') }}
                                            @if($kyToi->quaHan()) <span class="text-danger small d-block">Quá hạn</span> @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td><span class="status-pill status-pill--{{ $kh->status->badge() }}">{{ $kh->status->label() }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="mt-3">{{ $keHoach->links() }}</div>
    </div>

    <div class="col-xl-4">
        <form method="POST" action="{{ route('admin.installments.settings') }}" class="admin-panel p-4" data-cau-hinh-tra-gop>
            @csrf
            @method('PUT')

            <h2 class="h6 fw-bold mb-3">Cấu hình trả góp</h2>

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="tg-bat" name="bat" value="1" @checked(old('_token') !== null ? old('bat') === '1' : $cauHinh['bat'] === '1')>
                <label class="form-check-label" for="tg-bat">Mở trả góp cho khách</label>
            </div>

            @php
                $o = [
                    'don_toi_thieu' => ['Đơn tối thiểu (₫)', 'Đơn nhỏ hơn không trả góp được.'],
                    'so_ngay_moi_ky' => ['Số ngày mỗi kỳ', 'Hoa và cây để lâu có thể hỏng — nên để kỳ ngắn.'],
                    'ngay_an_han' => ['Ân hạn (ngày)', 'Quá hạn thêm từng này ngày thì kế hoạch tự huỷ.'],
                ];
            @endphp

            @foreach($o as $ten => [$nhan, $goiY])
                <div class="mb-3">
                    <label class="form-label" for="tg-{{ $ten }}">{{ $nhan }}</label>
                    <input type="number" min="0" id="tg-{{ $ten }}" name="{{ $ten }}" value="{{ old($ten, $cauHinh[$ten]) }}"
                           class="form-control @error($ten) is-invalid @enderror">
                    <div class="form-text">{{ $goiY }}</div>
                    <x-form-error :name="$ten" />
                </div>
            @endforeach

            <h3 class="h6 fw-bold mt-4 mb-2">Theo điểm tín dụng</h3>
            <p class="admin-page-subtitle small">
                Điểm tính từ lịch sử thanh toán của khách (0–100, khởi đầu 50). Dưới điểm tối thiểu thì không trả góp được.
            </p>

            <div class="row g-2">
                <div class="col-6">
                    <label class="form-label" for="tg-diem_toi_thieu">Điểm tối thiểu</label>
                    <input type="number" min="0" max="100" id="tg-diem_toi_thieu" name="diem_toi_thieu" value="{{ old('diem_toi_thieu', $cauHinh['diem_toi_thieu']) }}" class="form-control @error('diem_toi_thieu') is-invalid @enderror">
                    <x-form-error name="diem_toi_thieu" />
                </div>
                <div class="col-6">
                    <label class="form-label" for="tg-diem_tot">Điểm mức tốt</label>
                    <input type="number" min="0" max="100" id="tg-diem_tot" name="diem_tot" value="{{ old('diem_tot', $cauHinh['diem_tot']) }}" class="form-control @error('diem_tot') is-invalid @enderror">
                    <x-form-error name="diem_tot" />
                </div>
                <div class="col-6">
                    <label class="form-label" for="tg-ky_toi_da_thuong">Số kỳ tối đa — thường</label>
                    <input type="number" min="1" max="12" id="tg-ky_toi_da_thuong" name="ky_toi_da_thuong" value="{{ old('ky_toi_da_thuong', $cauHinh['ky_toi_da_thuong']) }}" class="form-control @error('ky_toi_da_thuong') is-invalid @enderror">
                    <x-form-error name="ky_toi_da_thuong" />
                </div>
                <div class="col-6">
                    <label class="form-label" for="tg-ky_toi_da_tot">Số kỳ tối đa — tốt</label>
                    <input type="number" min="1" max="12" id="tg-ky_toi_da_tot" name="ky_toi_da_tot" value="{{ old('ky_toi_da_tot', $cauHinh['ky_toi_da_tot']) }}" class="form-control @error('ky_toi_da_tot') is-invalid @enderror">
                    <x-form-error name="ky_toi_da_tot" />
                </div>
                <div class="col-6">
                    <label class="form-label" for="tg-tra_truoc_thuong">% trả trước — thường</label>
                    <input type="number" min="10" max="90" id="tg-tra_truoc_thuong" name="tra_truoc_thuong" value="{{ old('tra_truoc_thuong', $cauHinh['tra_truoc_thuong']) }}" class="form-control @error('tra_truoc_thuong') is-invalid @enderror">
                    <x-form-error name="tra_truoc_thuong" />
                </div>
                <div class="col-6">
                    <label class="form-label" for="tg-tra_truoc_tot">% trả trước — tốt</label>
                    <input type="number" min="10" max="90" id="tg-tra_truoc_tot" name="tra_truoc_tot" value="{{ old('tra_truoc_tot', $cauHinh['tra_truoc_tot']) }}" class="form-control @error('tra_truoc_tot') is-invalid @enderror">
                    <x-form-error name="tra_truoc_tot" />
                </div>
            </div>

            <p class="admin-page-subtitle small mt-3">Sửa ở đây chỉ áp cho đơn mới; kế hoạch đang chạy giữ điều kiện lúc tạo.</p>

            <button type="submit" class="btn btn-primary-brand w-100">Lưu cấu hình</button>
        </form>
    </div>

</div>

@endsection
