@extends('layouts.admin')

@section('title', $post->exists ? 'Sửa bài' : 'Viết bài mới')

@section('content')

@php $suaBai = $post->exists; @endphp

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="admin-page-title">{{ $suaBai ? 'Sửa bài' : 'Viết bài mới' }}</h1>
        @if($suaBai)
            <p class="admin-page-subtitle mb-0">
                <span class="status-pill status-pill--{{ $post->statusBadge() }}">{{ $post->statusText() }}</span>
                &middot; {{ number_format($post->view_count) }} lượt xem
                &middot; <code>/cam-nang/{{ $post->slug }}</code>
            </p>
        @endif
    </div>

    @if($suaBai && $post->isPublished())
        <a href="{{ route('shop.blog.show', $post) }}" class="btn btn-outline-admin"
           target="_blank" rel="noopener">Xem trên trang</a>
    @endif
</div>

<form method="POST"
      action="{{ $suaBai ? route('admin.blog.update', $post) : route('admin.blog.store') }}"
      enctype="multipart/form-data">
    @csrf
    @if($suaBai) @method('PUT') @endif

    <div class="row g-4">

        <div class="col-lg-8">
            <div class="admin-panel p-4 mb-4">
                <div class="mb-3">
                    <label class="form-label" for="title">Tiêu đề <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="title"
                           class="form-control @error('title') is-invalid @enderror"
                           value="{{ old('title', $post->title) }}" maxlength="200" required
                           placeholder="7 loại cây để bàn ít cần ánh sáng">
                    <x-form-error name="title"/>

                    @if($suaBai)
                        <p class="form-text">
                            Đường dẫn giữ nguyên <code>{{ $post->slug }}</code> dù đổi tiêu đề —
                            đổi đường dẫn là làm chết link cũ và mất thứ hạng đã có.
                        </p>
                    @endif
                </div>

                <div class="mb-3">
                    <label class="form-label" for="excerpt">Tóm tắt</label>
                    <textarea name="excerpt" id="excerpt" rows="2" class="form-control"
                              maxlength="300"
                              placeholder="Một hai câu nói bài này trả lời câu hỏi gì.">{{ old('excerpt', $post->excerpt) }}</textarea>
                    <p class="form-text">Hiện ở thẻ bài và làm mặc định cho mô tả trên Google.</p>
                    <x-form-error name="excerpt"/>
                </div>

                <div class="mb-0">
                    <label class="form-label" for="body">Nội dung <span class="text-danger">*</span></label>
                    <textarea name="body" id="body" rows="20"
                              class="form-control @error('body') is-invalid @enderror"
                              placeholder="&lt;h2&gt;Tiêu đề phụ&lt;/h2&gt;&#10;&lt;p&gt;Đoạn văn…&lt;/p&gt;">{{ old('body', $post->body) }}</textarea>
                    <x-form-error name="body"/>

                    <p class="form-text">
                        Giữ được: <code>h2 h3 h4 p strong em ul ol li blockquote a table code hr</code>.
                        Mọi thẻ khác bị gỡ nhưng <strong>giữ lại phần chữ</strong> bên trong.
                        Thẻ <code>script</code>, <code>iframe</code>, <code>img</code> và mọi
                        thuộc tính <code>on…</code> bị xoá vì lý do an toàn — ảnh trong bài
                        dùng ô Ảnh bìa bên cạnh.
                    </p>
                </div>
            </div>

            <div class="admin-panel p-4">
                <h2 class="h6 fw-bold mb-1">Cây nhắc trong bài</h2>
                <p class="admin-page-subtitle mb-3">
                    Hiện thành thẻ sản phẩm ở cuối bài. Đây là chỗ bài viết dẫn thẳng ra đơn hàng —
                    khách đọc xong mà phải tự đi tìm cây trong danh mục thì phần lớn sẽ không tìm.
                </p>

                @php
                    $hienCo = $daChon->values();
                    $soHang = max(3, $hienCo->count() + 1);
                @endphp

                @for($i = 0; $i < $soHang; $i++)
                    @php $sp = $hienCo[$i] ?? null; @endphp

                    <div class="row g-2 mb-2">
                        <div class="col-md-5">
                            <select name="products[{{ $i }}][id]" class="form-select form-select-sm"
                                    aria-label="Sản phẩm {{ $i + 1 }}">
                                <option value="">— Không chọn —</option>
                                @foreach($products as $p)
                                    <option value="{{ $p->id }}" @selected($sp?->id === $p->id)>{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-7">
                            <input type="text" name="products[{{ $i }}][note]"
                                   class="form-control form-control-sm" maxlength="200"
                                   value="{{ $sp?->pivot?->note }}"
                                   placeholder="Vì sao nhắc cây này"
                                   aria-label="Ghi chú {{ $i + 1 }}">
                        </div>
                    </div>
                @endfor

                <p class="form-text mb-0">Hàng nào không dùng thì để trống.</p>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="admin-panel p-4 mb-4">
                <h2 class="h6 fw-bold mb-3">Xuất bản</h2>

                <div class="mb-3">
                    <label class="form-label" for="published_at">Ngày đăng</label>
                    <input type="datetime-local" name="published_at" id="published_at"
                           class="form-control @error('published_at') is-invalid @enderror"
                           value="{{ old('published_at', \App\Services\Time\Gio::choO($post->published_at)) }}">
                    <x-form-error name="published_at"/>
                    <p class="form-text">
                        Để trống = bản nháp. Đặt ngày ở tương lai = hẹn giờ đăng, bài tự hiện khi tới giờ.
                    </p>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="blog_category_id">Chuyên mục</label>
                    <select name="blog_category_id" id="blog_category_id" class="form-select">
                        <option value="">— Chưa xếp —</option>
                        @foreach($categories as $c)
                            <option value="{{ $c->id }}"
                                @selected((int) old('blog_category_id', $post->blog_category_id) === $c->id)>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>

                    <div class="form-text">
                        @if($categories->isEmpty())
                            Chưa có chuyên mục nào.
                        @endif
                        <a data-admin-link href="{{ route('admin.blog-categories.index') }}">Thêm hoặc sửa chuyên mục</a>
                        — nên tạo chuyên mục trước khi viết bài.
                    </div>
                </div>

                <div class="mb-0">
                    <label class="form-label" for="cover_image">Ảnh bìa</label>

                    @if($post->cover_image)
                        <div class="mb-2">
                            <x-site.image :path="$post->cover_image" alt="Ảnh bìa hiện tại"
                                          class="admin-thumb" />
                            <label class="d-block mt-1">
                                <input type="checkbox" name="remove_cover" value="1"> Bỏ ảnh bìa
                            </label>
                        </div>
                    @endif

                    <input type="file" name="cover_image" id="cover_image" class="form-control"
                           accept="image/png,image/jpeg,image/webp">
                    <x-form-error name="cover_image"/>
                    <p class="form-text">Thông tin ẩn trong ảnh (GPS, máy chụp) được xoá tự động khi lưu.</p>
                </div>
            </div>

            <div class="admin-panel p-4 mb-4">
                <h2 class="h6 fw-bold mb-1">Hiện trên Google</h2>
                <p class="admin-page-subtitle mb-3">
                    Để trống thì lấy tiêu đề và tóm tắt ở trên — chỉ điền khi muốn nói khác đi.
                    Tiêu đề trên trang viết cho người đã ở đây; tiêu đề trên Google viết cho người
                    chưa biết cửa hàng này tồn tại.
                </p>

                <div class="mb-3">
                    <label class="form-label" for="meta_title">Tiêu đề SEO</label>
                    <input type="text" name="meta_title" id="meta_title" class="form-control"
                           value="{{ old('meta_title', $post->meta_title) }}" maxlength="200">
                </div>

                <div class="mb-0">
                    <label class="form-label" for="meta_description">Mô tả SEO</label>
                    <textarea name="meta_description" id="meta_description" rows="3"
                              class="form-control" maxlength="300">{{ old('meta_description', $post->meta_description) }}</textarea>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary-brand">
                    {{ $suaBai ? 'Lưu thay đổi' : 'Tạo bài' }}
                </button>
                <a href="{{ route('admin.blog.index') }}" class="btn btn-ghost">Huỷ</a>
            </div>
        </div>

    </div>
</form>

@if($suaBai)
    <div class="admin-panel p-4 mt-4">
        <h2 class="h6 fw-bold mb-2">Xoá bài này</h2>
        <p class="admin-page-subtitle">
            Bài được xoá mềm — vẫn khôi phục được từ cơ sở dữ liệu nếu cần.
            Nhưng mọi link đã chia sẻ sẽ trả về 404 ngay lập tức.
        </p>

        <form method="POST" action="{{ route('admin.blog.destroy', $post) }}"
              onsubmit="return confirm('Xoá bài &quot;{{ $post->title }}&quot;?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-ghost text-danger">Xoá bài</button>
        </form>
    </div>
@endif

@endsection
