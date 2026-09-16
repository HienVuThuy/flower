@extends('layouts.admin')

@section('title', 'Xuất dữ liệu')

@section('content')

<div class="mb-4">
    <h1 class="admin-page-title">Xuất dữ liệu phân tích</h1>
    <p class="admin-page-subtitle">
        Chọn đúng những phần cần và định dạng phù hợp với việc bạn sắp làm.
        Mọi con số đếm trực tiếp từ cơ sở dữ liệu tại thời điểm bấm tải.
    </p>
</div>

{{-- GET chứ không POST. --}}
<form method="GET" action="{{ route('admin.analytics.export') }}">

    <div class="row g-3">

        <div class="col-lg-8">
            <div class="admin-panel p-4 h-100">

                <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-3">
                    <h2 class="h6 fw-bold mb-0">Chọn phần muốn xuất</h2>

                    <div class="export-pick-all d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-admin" data-pick-all>Chọn tất cả</button>
                        <button type="button" class="btn btn-sm btn-outline-admin" data-pick-none>Bỏ chọn</button>
                    </div>
                </div>

                @php
                    $theoNhom = collect($sections)->groupBy('group', preserveKeys: true);
                @endphp

                @foreach($theoNhom as $tenNhom => $muc)
                    <h3 class="admin-section-title">{{ $tenNhom }}</h3>

                    <div class="export-list mb-3">
                        @foreach($muc as $ma => $m)
                            <label class="export-item">
                                <input type="checkbox" class="form-check-input" name="phan[]"
                                       value="{{ $ma }}" checked data-pick>

                                <span class="export-item__body">
                                    <span class="export-item__name">{{ $m['label'] }}</span>
                                    <span class="export-item__note">{{ $m['note'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endforeach

            </div>
        </div>

        <div class="col-lg-4">
            <div class="admin-panel p-4 h-100">

                <h2 class="h6 fw-bold mb-3">Khoảng thời gian</h2>

                <div class="d-flex flex-column gap-2 mb-4">
                    @foreach($periods as $value => $label)
                        <label class="export-item">
                            <input type="radio" class="form-check-input" name="ky"
                                   value="{{ $value }}" @checked(! $ky->laTuyChon() && $ky->ma === (string) $value)>
                            <span class="export-item__body">
                                <span class="export-item__name">{{ $label }}</span>
                            </span>
                        </label>
                    @endforeach

                    <label class="export-item">
                        <input type="radio" class="form-check-input" name="ky"
                               value="{{ \App\Services\Analytics\ChonKy::TUY_CHON }}"
                               @checked($ky->laTuyChon())>
                        <span class="export-item__body">
                            <span class="export-item__name">Khoảng ngày tự chọn</span>
                        </span>
                    </label>

                    <div class="export-khoang">
                        <label class="visually-hidden" for="xuat-tu">Từ ngày</label>
                        <input type="date" id="xuat-tu" name="tu" class="form-control form-control-sm"
                               value="{{ $ky->oTu() }}" max="{{ \App\Services\Time\Gio::choONgay(now()) }}">

                        <span aria-hidden="true">–</span>

                        <label class="visually-hidden" for="xuat-den">Đến ngày</label>
                        <input type="date" id="xuat-den" name="den" class="form-control form-control-sm"
                               value="{{ $ky->oDen() }}" max="{{ \App\Services\Time\Gio::choONgay(now()) }}">
                    </div>
                </div>

                <h2 class="h6 fw-bold mb-3">Định dạng</h2>

                <div class="d-flex flex-column gap-2 mb-4">
                    @foreach($formats as $ma => $mo)
                        <label class="export-item">
                            <input type="radio" class="form-check-input" name="dinh_dang"
                                   value="{{ $ma }}" @checked($ma === 'csv')>
                            <span class="export-item__body">
                                <span class="export-item__name">{{ strtoupper($ma) }}</span>
                                <span class="export-item__note">{{ \Illuminate\Support\Str::after($mo, '— ') }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <p class="admin-page-subtitle mb-3">
                    Mở bằng Excel để lọc và cộng cột thì chọn <strong>XLSX</strong>
                    (mỗi phần một trang tính, số là số); chọn CSV khi cần một bảng
                    phẳng để dán đi nơi khác. Gửi cho người khác hoặc in ra giấy thì
                    chọn <strong>PDF</strong>; chọn HTML khi còn muốn sửa lại.
                </p>

                <button type="submit" class="btn btn-primary-brand w-100">Tải về</button>

            </div>
        </div>

    </div>

</form>

@endsection
