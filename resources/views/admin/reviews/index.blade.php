@extends('layouts.admin')

@section('title', 'Đánh giá sản phẩm')

@section('content')

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="admin-page-title">Đánh giá sản phẩm</h1>
            <p class="admin-page-subtitle">
                Đánh giá hiện ngay khi khách gửi. Dùng trang này để ẩn bài có nội dung không phù hợp.
            </p>
        </div>
    </div>

    {{-- Bộ lọc: giữ nguyên dạng liên kết, không cần JavaScript. --}}
    <div class="mb-3 d-flex flex-wrap gap-2">
        <a href="{{ route('admin.reviews.index') }}"
           class="btn btn-sm {{ $filter ? 'btn-outline-admin' : 'btn-primary-brand' }}">
            Tất cả
        </a>
        <a href="{{ route('admin.reviews.index', ['trang_thai' => 'hien']) }}"
           class="btn btn-sm {{ $filter === 'hien' ? 'btn-primary-brand' : 'btn-outline-admin' }}">
            Đang hiện
        </a>
        <a href="{{ route('admin.reviews.index', ['trang_thai' => 'an']) }}"
           class="btn btn-sm {{ $filter === 'an' ? 'btn-primary-brand' : 'btn-outline-admin' }}">
            Đã ẩn @if($hiddenCount > 0) ({{ $hiddenCount }}) @endif
        </a>
    </div>

    <x-admin.filter-bar
    :action="route('admin.reviews.index')"
    placeholder="Tìm trong nhận xét hoặc tên sản phẩm…"
    :total="$reviews->total()"
>
    @if(request('trang_thai'))
        <input type="hidden" name="trang_thai" value="{{ request('trang_thai') }}">
    @endif

    {{--
        LỌC THEO SỐ SAO — việc admin cần nhất ở trang này.

        Đánh giá 1-2 sao là lời phàn nàn cần trả lời, và chúng lẫn giữa
        hàng chục đánh giá 5 sao. "Từ 2 sao trở xuống" gom cả hai mức đó
        vào một lần bấm.
    --}}
    <select name="sao" class="form-select" aria-label="Lọc theo số sao">
        <option value="">Mọi mức sao</option>
        <option value="thap" @selected(request('sao') === 'thap')>Từ 2 sao trở xuống</option>
        @for($i = 5; $i >= 1; $i--)
            <option value="{{ $i }}" @selected(request('sao') === (string) $i)>{{ $i }} sao</option>
        @endfor
    </select>

    {{--
        Ghép với ô số sao thì ra đúng hàng đợi: phàn nàn CHƯA AI TRẢ
        LỜI. Đây là đích của dòng tương ứng ở trang tổng quan.
    --}}
    <select name="tra_loi" class="form-select" aria-label="Lọc theo trả lời">
        <option value="">Đã trả lời hay chưa</option>
        <option value="chua" @selected(request('tra_loi') === 'chua')>Chưa trả lời</option>
        <option value="roi" @selected(request('tra_loi') === 'roi')>Đã trả lời</option>
    </select>
</x-admin.filter-bar>

<x-admin.bulk-bar
    :action="route('admin.reviews.bulk')"
    :viec="[
        'an' => 'Ẩn khỏi trang sản phẩm',
        'hien' => 'Hiện lại',
    ]"
/>

<div class="admin-panel">

        @if($reviews->isEmpty())

            <div class="p-4 text-center admin-page-subtitle">
                <p class="mb-0">
                    @if($filter)
                        Không có đánh giá nào ở mục này.
                    @else
                        Chưa có đánh giá nào. Khách chỉ đánh giá được sản phẩm đã mua và đã nhận hàng.
                    @endif
                </p>
            </div>

        @else

            <div class="table-responsive">
                <table class="admin-table align-middle mb-0">

                    <thead>
                        <tr>
                            <th style="width: 2.5rem;">
                                <input type="checkbox" class="admin-check"
                                       form="bulk-form" data-bulk-all
                                       aria-label="Chọn tất cả dòng đang hiện">
                            </th>
                            <th>Sản phẩm</th>
                            <th>Người viết</th>
                            <th>Sao</th>
                            <th>Nhận xét</th>
                            <th>Ngày</th>
                            <th>Trạng thái</th>
                            <th class="text-end">Thao tác</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($reviews as $review)
                            <tr>
                                <td>
                                    <input type="checkbox" class="admin-check"
                                           form="bulk-form" name="ids[]" value="{{ $review->id }}"
                                           data-bulk-item
                                           aria-label="Chọn đánh giá #{{ $review->id }}">
                                </td>

                                <td>
                                    @if($review->product)
                                        <a href="{{ route('shop.products.show', $review->product) }}" target="_blank" rel="noopener">
                                            {{ $review->product->name }}
                                        </a>
                                    @else
                                        <span class="admin-page-subtitle">(sản phẩm đã xoá)</span>
                                    @endif
                                </td>

                                <td>
                                    {{-- Trang quản trị hiện tên đầy đủ; trang khách thì che bớt. --}}
                                    {{ $review->user?->name ?? '(tài khoản đã xoá)' }}
                                    @if($review->order_id)
                                        <div class="admin-page-subtitle">Đã mua hàng</div>
                                    @endif
                                </td>

                                <td class="text-nowrap">{{ $review->rating }}/5</td>

                                <td style="max-width: 26rem;">
                                    {{ $review->comment ?: '—' }}

                                    {{--
                                        PHẢN HỒI CỦA CỬA HÀNG — công khai.

                                        Trước đây admin chỉ làm được đúng
                                        một việc với đánh giá: ẩn nó đi.
                                        Với một đánh giá 2 sao thì đó là
                                        lựa chọn tệ nhất — khách viết ra
                                        vì muốn được nghe.

                                        Ô nhập để ngay dưới nội dung, mở
                                        bằng <details> nên không chiếm
                                        chỗ khi admin chỉ đang đọc lướt.
                                    --}}
                                    <details class="mt-2" @if($review->hasReply()) open @endif>
                                        <summary class="admin-page-subtitle" style="cursor:pointer">
                                            @if($review->hasReply())
                                                Đã phản hồi
                                                <x-site.time :at="$review->admin_replied_at" format="d/m/Y" />
                                            @else
                                                Trả lời khách
                                            @endif
                                        </summary>

                                        <form method="POST"
                                              action="{{ route('admin.reviews.reply', $review) }}"
                                              class="mt-2">
                                            @csrf
                                            @method('PATCH')

                                            <textarea name="admin_reply"
                                                      class="form-control form-control-sm mb-2"
                                                      rows="3"
                                                      maxlength="1000"
                                                      placeholder="Cảm ơn anh/chị đã phản ánh. Cửa hàng đã…"
                                            >{{ $review->admin_reply }}</textarea>

                                            <button type="submit" class="btn btn-outline-admin btn-sm">
                                                Lưu phản hồi
                                            </button>

                                            {{-- Xoá phản hồi = gửi ô trống. Không cần
                                                 một route riêng cho việc đó. --}}
                                            @if($review->hasReply())
                                                <span class="admin-page-subtitle ms-1">
                                                    Xoá hết chữ rồi lưu để gỡ phản hồi.
                                                </span>
                                            @endif
                                        </form>
                                    </details>
                                </td>

                                <td class="admin-page-subtitle text-nowrap">
                                    <x-site.time :at="$review->created_at" format="d/m/Y" />
                                </td>

                                <td>
                                    <span class="status-pill status-pill--{{ $review->is_visible ? 'success' : 'secondary' }}">
                                        {{ $review->is_visible ? 'Đang hiện' : 'Đã ẩn' }}
                                    </span>
                                </td>

                                <td class="text-end">
                                    <form method="POST" action="{{ route('admin.reviews.toggle', $review) }}" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-outline-admin btn-sm">
                                            {{ $review->is_visible ? 'Ẩn' : 'Hiện lại' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                </table>
            </div>

        @endif

    </div>

    @if($reviews->hasPages())
        <div class="mt-3">{{ $reviews->links() }}</div>
    @endif

@endsection
