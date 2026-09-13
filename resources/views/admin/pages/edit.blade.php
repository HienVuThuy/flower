@extends('layouts.admin')

@section('title', 'Trang nội dung')

@section('content')

<div class="mb-4">
    <h1 class="admin-page-title">Trang nội dung</h1>
    <p class="admin-page-subtitle mb-0">
        Giới thiệu, liên hệ và các trang chính sách. <strong>Để trống thì trang dùng bản viết sẵn.</strong>
        Viết văn bản thường: dòng bắt đầu bằng <code>## </code> thành tiêu đề, dòng trống tách đoạn.
    </p>
</div>

<form method="POST" action="{{ route('admin.page-contents.update') }}">
    @csrf
    @method('PUT')

    @foreach($trang as $slug => $t)
        <div class="admin-panel p-4 mb-3">
            <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-2">
                <label class="h6 fw-bold mb-0" for="nd-{{ $slug }}">{{ $t['tieu_de'] }}</label>
                <span class="admin-page-subtitle small">
                    {{ $t['noi_dung'] === '' ? 'đang dùng bản viết sẵn' : 'đang dùng nội dung đã sửa' }}
                    &middot;
                    <a href="{{ route('shop.pages.show', $slug) }}" target="_blank" rel="noopener">xem trang</a>
                </span>
            </div>

            <textarea id="nd-{{ $slug }}" name="noi_dung[{{ $slug }}]" rows="8"
                      maxlength="{{ \App\Http\Controllers\Admin\PageContentController::DAI_TOI_DA }}"
                      class="form-control @error('noi_dung.' . $slug) is-invalid @enderror"
                      placeholder="Để trống để dùng bản viết sẵn.">{{ old('noi_dung.' . $slug, $t['noi_dung']) }}</textarea>
            <x-form-error :name="'noi_dung.' . $slug" />
        </div>
    @endforeach

    <button type="submit" class="btn btn-primary-brand">Lưu nội dung</button>
</form>

@endsection
