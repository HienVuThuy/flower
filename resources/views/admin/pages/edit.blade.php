@extends('layouts.admin')

@section('title', 'Trang nội dung')

@section('content')

<div class="mb-4">
    <h1 class="admin-page-title">Trang nội dung</h1>
    <p class="admin-page-subtitle mb-0">
        Giới thiệu, liên hệ và các trang chính sách. Ô soạn đã điền sẵn nội dung đang hiện trên trang —
        sửa đúng chỗ cần sửa rồi bấm Lưu. <strong>Xoá trắng một ô thì trang đó quay về bản viết sẵn.</strong>
    </p>
</div>

<x-admin.nhom-tab ten="cai-dat" />

<div class="row g-3">
    <div class="col-lg-8">
        <form method="POST" action="{{ route('admin.page-contents.update') }}">
            @csrf
            @method('PUT')

            @foreach($trang as $slug => $t)
                <details class="admin-panel p-4 mb-3" @if($loop->first) open @endif>
                    <summary class="d-flex flex-wrap justify-content-between align-items-baseline gap-2">
                        <span class="h6 fw-bold mb-0">{{ $t['tieu_de'] }}</span>
                        <span class="admin-page-subtitle small">
                            {{ $t['da_sua'] ? 'đang dùng nội dung đã sửa' : 'đang dùng bản viết sẵn' }}
                        </span>
                    </summary>

                    <div class="mt-3">
                        <label class="visually-hidden" for="nd-{{ $slug }}">Nội dung trang {{ $t['tieu_de'] }}</label>
                        <textarea id="nd-{{ $slug }}" name="noi_dung[{{ $slug }}]" rows="18"
                                  maxlength="{{ \App\Http\Controllers\Admin\PageContentController::DAI_TOI_DA }}"
                                  class="form-control font-monospace @error('noi_dung.' . $slug) is-invalid @enderror"
                                  spellcheck="false">{{ old('noi_dung.' . $slug, $t['noi_dung']) }}</textarea>
                        <x-form-error :name="'noi_dung.' . $slug" />

                        <a class="small d-inline-block mt-2" href="{{ route('shop.pages.show', $slug) }}" target="_blank" rel="noopener">
                            Xem trang đang hiện
                        </a>
                    </div>
                </details>
            @endforeach

            <button type="submit" class="btn btn-primary-brand">Lưu nội dung</button>
        </form>
    </div>

    <div class="col-lg-4">
        <div class="admin-panel p-4">
            <h2 class="h6 fw-bold mb-3">Cách viết</h2>
            <p class="admin-page-subtitle small">Các khối cách nhau bằng <strong>một dòng trống</strong>.</p>

            <table class="table table-sm small mb-3">
                <tbody>
                    <tr><td><code>## Tiêu đề</code></td><td>tiêu đề mục</td></tr>
                    <tr><td><code>### Tiêu đề</code></td><td>tiêu đề nhỏ</td></tr>
                    <tr><td><code>- mục</code></td><td>danh sách</td></tr>
                    <tr><td><code>1. mục</code></td><td>danh sách đánh số</td></tr>
                    <tr><td><code>Nhãn:: nội dung</code></td><td>khối thông tin</td></tr>
                    <tr><td><code>| ô | ô |</code></td><td>bảng, dòng đầu là tiêu đề</td></tr>
                    <tr><td><code>&gt; chữ</code></td><td>khung lưu ý</td></tr>
                    <tr><td><code>**chữ**</code></td><td>chữ đậm</td></tr>
                    <tr><td><code>[chữ](/duong-dan)</code></td><td>liên kết</td></tr>
                </tbody>
            </table>

            <h3 class="h6 fw-bold">Tự điền theo Cài đặt</h3>
            <p class="admin-page-subtitle small mb-2">Đổi thông tin ở Cài đặt chung là mọi trang đổi theo.</p>
            <ul class="small ps-3 mb-0">
                <li><code>{ten_cua_hang}</code>, <code>{hotline}</code>, <code>{email}</code>, <code>{dia_chi}</code></li>
                <li><code>{bang_phi_giao_hang}</code> — một dòng riêng</li>
                <li><code>{mien_phi_giao_tu}</code></li>
            </ul>

            <p class="admin-page-subtitle small mt-3 mb-0">
                Chỉ là chữ, không nhận HTML: thẻ gõ vào sẽ hiện nguyên thành chữ trên trang.
            </p>
        </div>
    </div>
</div>

@endsection
