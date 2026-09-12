@extends('layouts.app')

@section('title', 'Đánh giá của tôi')

@section('content')

<section class="section-sm">
    <div class="container-shop" style="max-width: 52rem;">

        <h1 class="text-h3 mb-2">Đánh giá của tôi</h1>
        <p class="text-caption mb-4">
            Tất cả đánh giá bạn đã viết. Gỡ lúc nào cũng được &mdash; gỡ rồi thì
            điểm trung bình của sản phẩm sẽ tính lại mà không còn bài này.
        </p>

        @if($reviews->isEmpty())

            {{--
                Nói rõ ĐIỀU KIỆN để viết được đánh giá, không chỉ nói
                "chưa có gì". Người mở trang này mà thấy trống thường
                đang tự hỏi vì sao mình không viết được — câu trả lời là
                phải mua và nhận hàng trước.
            --}}
            <div class="surface-card p-4 text-center">
                <p class="mb-2">Bạn chưa viết đánh giá nào.</p>
                <p class="text-caption mb-3">
                    Chỉ đánh giá được sản phẩm đã mua và đã nhận hàng.
                </p>
                <a href="{{ route('shop.orders.index') }}" class="btn btn-secondary-brand btn-sm">
                    Xem đơn hàng của tôi
                </a>
            </div>

        @else

            <div class="d-flex flex-column gap-3">
                @foreach($reviews as $review)
                    <div class="surface-card p-4">

                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                @if($review->product)
                                    {{--
                                        Sản phẩm đã bị gỡ khỏi cửa hàng thì KHÔNG
                                        còn trang để mở — hiện tên trơn thay vì một
                                        liên kết dẫn tới lỗi 404. Đánh giá vẫn là
                                        của người dùng và vẫn gỡ được.
                                    --}}
                                    @if($review->product->trashed())
                                        <span class="fw-semibold">{{ $review->product->name }}</span>
                                        <span class="text-caption">(sản phẩm đã ngừng bán)</span>
                                    @else
                                        <a href="{{ route('shop.products.show', $review->product) }}"
                                           class="fw-semibold">
                                            {{ $review->product->name }}
                                        </a>
                                    @endif
                                @else
                                    <span class="text-caption">(sản phẩm đã bị xoá)</span>
                                @endif

                                <div class="text-caption">
                                    {{ $review->rating }}/5 sao
                                    &middot; <x-site.time :at="$review->created_at" format="d/m/Y" />
                                    @unless($review->is_visible)
                                        {{--
                                            Nói thẳng khi bài đang bị ẩn. Không nói
                                            thì người viết vào trang sản phẩm không
                                            thấy bài của mình và tưởng hệ thống mất
                                            dữ liệu.
                                        --}}
                                        &middot; <span class="text-danger">đang bị ẩn khỏi trang sản phẩm</span>
                                    @endunless
                                </div>
                            </div>

                            <form action="{{ route('shop.reviews.destroy', $review) }}"
                                  method="POST"
                                  onsubmit="return confirm('Gỡ đánh giá này?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm">
                                    <x-site.icon name="trash" /> Gỡ
                                </button>
                            </form>
                        </div>

                        @if($review->comment)
                            <p class="mb-0">{{ $review->comment }}</p>
                        @endif

                        @if($review->admin_reply)
                            {{--
                                Phản hồi của cửa hàng hiện ngay dưới bài, đúng như
                                trên trang sản phẩm: người viết cần thấy mình đã
                                được trả lời mà không phải đi tìm.
                            --}}
                            <div class="mt-3 p-3" style="background: var(--surface-alt); border-radius: var(--radius-sm);">
                                <div class="text-caption mb-1">Phản hồi từ cửa hàng</div>
                                <p class="mb-0">{{ $review->admin_reply }}</p>
                            </div>
                        @endif

                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $reviews->links() }}
            </div>

        @endif

    </div>
</section>

@endsection
