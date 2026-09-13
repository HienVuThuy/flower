@extends('layouts.admin')

@section('title', 'Cẩm nang')

@section('content')

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="admin-page-title">Cẩm nang</h1>
        <p class="admin-page-subtitle mb-0">
            Bài hướng dẫn và gợi ý — nơi kéo khách từ Google về trang sản phẩm.
        </p>
    </div>

    <a href="{{ route('admin.blog.create') }}" class="btn btn-primary-brand">Viết bài mới</a>
</div>

<x-admin.nhom-tab ten="cam-nang" />

<div class="admin-panel p-4">
    <form method="GET" class="mb-3">
        <input type="search" name="q" value="{{ $q }}" class="form-control"
               placeholder="Tìm theo tiêu đề..." aria-label="Tìm bài">
    </form>

    @if($posts->isEmpty())
        <x-site.empty-state title="Chưa có bài nào" text="Bấm “Viết bài mới” để bắt đầu." />
    @else
        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Tiêu đề</th>
                        <th>Chuyên mục</th>
                        <th>Trạng thái</th>
                        <th>Lượt xem</th>
                        <th><span class="visually-hidden">Thao tác</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($posts as $post)
                        <tr>
                            <td>
                                <a href="{{ route('admin.blog.edit', $post) }}">{{ $post->title }}</a>
                                <div class="admin-page-subtitle">
                                    {{ $post->author?->name ?? 'Không rõ tác giả' }}
                                </div>
                            </td>
                            <td>{{ $post->category?->name ?? '—' }}</td>
                            <td>
                                <span class="status-pill status-pill--{{ $post->statusBadge() }}">
                                    {{ $post->statusText() }}
                                </span>
                            </td>
                            <td>{{ number_format($post->view_count) }}</td>
                            <td class="text-end">
                                @if($post->isPublished())
                                    <a href="{{ route('shop.blog.show', $post) }}"
                                       class="btn btn-ghost btn-sm" target="_blank" rel="noopener">Xem</a>
                                @endif
                                <a href="{{ route('admin.blog.edit', $post) }}" class="btn btn-ghost btn-sm">Sửa</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $posts->links() }}</div>
    @endif
</div>

@endsection
