@extends('layouts.admin')

@section('title', 'Tham số kinh doanh')

@section('content')

@php
    use App\Http\Controllers\Admin\BusinessParamsController as Ts;
    use App\Services\Shop\Money;
    use App\Services\Shop\ThamSoKinhDoanh;

    $giaTriO = function (string $khoa, array $dn) {
        $v = ThamSoKinhDoanh::giaTri($khoa);

        return match ($dn['kieu']) {
            'tien' => Money::number($v),
            'moc_tien' => implode(', ', array_map(fn ($m) => Money::number($m), (array) $v)),
            default => (string) (int) $v,
        };
    };
@endphp

<div class="mb-4">
    <h1 class="admin-page-title">Cài đặt hệ thống</h1>
    <p class="admin-page-subtitle">
        Các ngưỡng, mức phí, điểm thưởng và thời hạn cửa hàng tự quyết. Lưu là áp dụng ngay;
        xoá trắng một ô thì quay về mặc định.
    </p>
</div>

<x-admin.nhom-tab ten="cai-dat" />

<form action="{{ route('admin.business-params.update') }}" method="POST" data-tham-so>
    @csrf
    @method('PUT')

    <div class="row g-4">
        @foreach($nhom as $tenNhom => $cacThamSo)
            <div class="col-xl-6">
                <div class="admin-panel p-4 h-100">
                    <h2 class="h6 fw-bold mb-3">{{ $tenNhom }}</h2>

                    @foreach($cacThamSo as $khoa => $dn)
                        @php
                            $o = Ts::tenO($khoa);
                            $id = 'ts-' . $o;
                        @endphp

                        <div class="tham-so {{ ! $loop->last ? 'mb-3' : '' }}" data-tham-so-dong="{{ $khoa }}">
                            <label class="form-label mb-1" for="{{ $id }}">
                                {{ $dn['nhan'] }}
                                @if(ThamSoKinhDoanh::daDoi($khoa))
                                    <span class="badge text-bg-secondary ms-1">đã đổi</span>
                                @endif
                            </label>

                            <div class="input-group">
                                <input type="text" inputmode="{{ $dn['kieu'] === 'moc_tien' ? 'text' : 'numeric' }}"
                                       class="form-control @error('ts.' . $o) is-invalid @enderror"
                                       id="{{ $id }}" name="ts[{{ $o }}]" maxlength="80" autocomplete="off"
                                       value="{{ old('ts.' . $o, $giaTriO($khoa, $dn)) }}">
                                <span class="input-group-text">
                                    {{ in_array($dn['kieu'], ['tien', 'moc_tien'], true) ? Money::symbol() : ($dn['don_vi'] ?? '') }}
                                </span>
                            </div>
                            <x-form-error :name="'ts.' . $o" />

                            <div class="form-text">
                                @isset($dn['goi_y']){{ $dn['goi_y'] }} @endisset
                                Mặc định: {{ Ts::hien($dn, ThamSoKinhDoanh::macDinh($khoa)) }}.
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="d-flex justify-content-end mt-4">
        <button type="submit" class="btn btn-primary-brand">Lưu tham số</button>
    </div>
</form>

@endsection
