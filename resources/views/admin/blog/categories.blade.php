@extends('layouts.admin')

@section('title', 'Chuyên mục Cẩm nang')

@section('content')

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="admin-page-title">Chuyên mục Cẩm nang</h1>
        <p class="admin-page-subtitle mb-0">
            Nhóm bài viết theo chủ đề. Chuyên mục còn bài thì không xoá được — chuyển bài sang chỗ khác trước.
        </p>
    </div>
</div>

<x-admin.nhom-tab ten="cam-nang" />

<div class="row g-3">
    <div class="col-lg-8">
        <div class="admin-panel">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Chuyên mục</th>
                            <th scope="col" class="text-end">Bài viết</th>
                            <th scope="col" class="text-end">Thứ tự</th>
                            <th scope="col" class="text-end">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($chuyenMuc as $cm)
                            <tr>
                                <td>
                                    <span class="fw-semibold">{{ $cm->name }}</span>
                                    <span class="d-block admin-page-subtitle small">/{{ $cm->slug }}</span>
                                    @if($cm->description)
                                        <span class="d-block admin-page-subtitle small">{{ $cm->description }}</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ $cm->posts_count }}</td>
                                <td class="text-end">{{ $cm->sort_order }}</td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-admin"
                                            data-bs-toggle="collapse" data-bs-target="#sua-cm-{{ $cm->id }}">
                                        Sửa
                                    </button>

                                    @if((int) $cm->posts_count === 0)
                                        <form method="POST" action="{{ route('admin.blog-categories.destroy', $cm) }}" class="d-inline"
                                              onsubmit="return confirm('Xoá chuyên mục {{ $cm->name }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Xoá</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            <tr class="collapse" id="sua-cm-{{ $cm->id }}">
                                <td colspan="4">
                                    <form method="POST" action="{{ route('admin.blog-categories.update', $cm) }}"
                                          class="row g-2 align-items-end">
                                        @csrf
                                        @method('PUT')
                                        <div class="col-md-4">
                                            <label class="form-label small mb-1" for="ten-{{ $cm->id }}">Tên</label>
                                            <input id="ten-{{ $cm->id }}" name="name" required maxlength="100"
                                                   class="form-control form-control-sm" value="{{ $cm->name }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small mb-1" for="slug-{{ $cm->id }}">Địa chỉ</label>
                                            <input id="slug-{{ $cm->id }}" name="slug" maxlength="120"
                                                   class="form-control form-control-sm" value="{{ $cm->slug }}">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small mb-1" for="mt-{{ $cm->id }}">Mô tả</label>
                                            <input id="mt-{{ $cm->id }}" name="description" maxlength="300"
                                                   class="form-control form-control-sm" value="{{ $cm->description }}">
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label small mb-1" for="tt-{{ $cm->id }}">Thứ tự</label>
                                            <input id="tt-{{ $cm->id }}" name="sort_order" type="number" min="0" max="65535"
                                                   class="form-control form-control-sm" value="{{ $cm->sort_order }}">
                                        </div>
                                        <div class="col-md-1">
                                            <button type="submit" class="btn btn-sm btn-primary-brand w-100">Lưu</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <x-admin.empty-row :colspan="4">Chưa có chuyên mục nào.</x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="admin-panel p-4">
            <h2 class="h6 fw-bold mb-3">Thêm chuyên mục</h2>

            <form method="POST" action="{{ route('admin.blog-categories.store') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label" for="name">Tên <span aria-hidden="true">*</span></label>
                    <input id="name" name="name" required maxlength="100"
                           class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}">
                    <x-form-error name="name" />
                </div>

                <div class="mb-3">
                    <label class="form-label" for="slug">Địa chỉ</label>
                    <input id="slug" name="slug" maxlength="120"
                           class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug') }}"
                           placeholder="để trống sẽ tự tạo từ tên">
                    <x-form-error name="slug" />
                </div>

                <div class="mb-3">
                    <label class="form-label" for="description">Mô tả</label>
                    <input id="description" name="description" maxlength="300"
                           class="form-control @error('description') is-invalid @enderror" value="{{ old('description') }}">
                    <x-form-error name="description" />
                </div>

                <div class="mb-3">
                    <label class="form-label" for="sort_order">Thứ tự</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" max="65535"
                           class="form-control @error('sort_order') is-invalid @enderror" value="{{ old('sort_order', 0) }}">
                    <x-form-error name="sort_order" />
                </div>

                <button type="submit" class="btn btn-primary-brand w-100">Thêm chuyên mục</button>
            </form>
        </div>
    </div>
</div>

@endsection
