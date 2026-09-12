@props(['product', 'reviews', 'reviewableOrder'])

{{--
    Khối đánh giá ở trang chi tiết sản phẩm.

    Form CHỈ hiện khi $reviewableOrder khác null — biến đó do
    Review::pendingOrderFor() trả về, đúng hàm mà ReviewController dùng để
    chặn. Một nguồn sự thật, nên không có chuyện nút hiện ra rồi bấm vào
    lại báo "bạn không có quyền".
--}}

<div class="product-reviews" id="danh-gia">

    <div class="d-flex flex-wrap align-items-baseline gap-3 mb-3">
        <h2 class="text-h3 mb-0">Đánh giá</h2>

        @if($product->ratingCount() > 0)
            <x-product.rating-stars
                :value="$product->ratingAverage()"
                :count="$product->ratingCount()" />
        @endif
    </div>

    {{-- ============ FORM VIẾT ĐÁNH GIÁ ============ --}}
    @if($reviewableOrder)

        <form method="POST"
              action="{{ route('shop.reviews.store', $product) }}"
              class="review-form surface-card p-4 mb-4">
            @csrf

            <p class="text-caption mb-3">
                Bạn đã mua sản phẩm này trong đơn
                <strong>{{ $reviewableOrder->order_number }}</strong>.
            </p>

            <fieldset class="mb-3">
                <legend class="text-label mb-2">Bạn chấm mấy sao?</legend>

                {{--
                    Radio thật, không phải div bấm bằng JavaScript: bàn phím
                    dùng được, và tắt JS vẫn gửi được đánh giá.
                    Thứ tự 5→1 để CSS ~ tô các sao đứng trước khi rê chuột.
                --}}
                <div class="rating-input @error('rating') is-invalid @enderror">
                    @foreach([5, 4, 3, 2, 1] as $star)
                        <input type="radio"
                               class="rating-input__radio"
                               name="rating"
                               id="rating-{{ $star }}"
                               value="{{ $star }}"
                               @checked((int) old('rating') === $star)
                               required>
                        <label class="rating-input__label"
                               for="rating-{{ $star }}"
                               title="{{ $star }} sao">
                            <x-site.icon name="star-fill" />
                            <span class="visually-hidden">{{ $star }} sao</span>
                        </label>
                    @endforeach
                </div>

                <x-form-error name="rating" />
            </fieldset>

            <div class="mb-3">
                <label class="text-label d-block mb-2" for="comment">
                    Nhận xét <span class="text-muted text-lowercase">(không bắt buộc)</span>
                </label>
                <textarea name="comment"
                          id="comment"
                          rows="3"
                          maxlength="1000"
                          class="form-control @error('comment') is-invalid @enderror"
                          placeholder="Hoa có tươi không? Gói có đẹp không? Giao có đúng hẹn không?">{{ old('comment') }}</textarea>
                <x-form-error name="comment" />
            </div>

            <button type="submit" class="btn btn-primary-brand">Gửi đánh giá</button>
        </form>

    @elseif(auth()->guest())

        <p class="text-caption mb-4">
            <a href="{{ route('login') }}">Đăng nhập</a> để đánh giá sản phẩm bạn đã mua.
        </p>

    @endif

    {{-- ============ DANH SÁCH ĐÁNH GIÁ ============ --}}
    @if($reviews->isEmpty())

        <p class="text-caption mb-0">
            Chưa có đánh giá nào cho sản phẩm này.
        </p>

    @else

        <ul class="review-list">
            @foreach($reviews as $review)
                <li class="review-item">

                    <div class="review-item__head">
                        <div>
                            <span class="review-item__author">{{ $review->authorName() }}</span>

                            {{--
                                Nhãn này chỉ xuất hiện được khi có order_id,
                                mà order_id chỉ được ghi khi đơn đã giao —
                                nên nó không thể là lời quảng cáo suông.
                            --}}
                            @if($review->order_id)
                                <span class="review-item__verified">
                                    <x-site.icon name="check-circle" /> Đã mua hàng
                                </span>
                            @endif
                        </div>

                        <span class="review-item__date">
                            <x-site.time :at="$review->created_at" format="d/m/Y" />
                        </span>
                    </div>

                    <x-product.rating-stars :value="$review->rating" class="mb-2 d-inline-flex" />

                    @if($review->comment)
                        <p class="review-item__body mb-0">{{ $review->comment }}</p>
                    @endif

                    {{--
                        PHẢN HỒI CỦA CỬA HÀNG.

                        Thụt vào và đổi nền để người đọc thấy ngay đây là
                        tiếng nói của bên bán, không phải của một khách
                        khác. Không phân biệt được là chỗ dễ hiểu nhầm
                        nhất trên trang đánh giá.

                        Một lời xin lỗi công khai kèm cách xử lý cứu được
                        nhiều khách hơn là giấu lời phàn nàn đi.
                    --}}
                    @if($review->hasReply())
                        <div class="review-item__reply">
                            <div class="review-item__reply-head">
                                <x-site.icon name="flower1" />
                                <strong>Phản hồi từ {{ \App\Services\Shop\StoreProfile::name() }}</strong>
                                @if($review->admin_replied_at)
                                    <span class="review-item__date">
                                        <x-site.time :at="$review->admin_replied_at" format="d/m/Y" />
                                    </span>
                                @endif
                            </div>
                            <p class="mb-0">{{ $review->admin_reply }}</p>
                        </div>
                    @endif

                    @auth
                        @if($review->user_id === auth()->id())
                            <form method="POST"
                                  action="{{ route('shop.reviews.destroy', $review) }}"
                                  class="mt-2"
                                  onsubmit="return confirm('Gỡ đánh giá này?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-link-danger">
                                    <x-site.icon name="trash" /> Gỡ đánh giá của tôi
                                </button>
                            </form>
                        @endif
                    @endauth

                </li>
            @endforeach
        </ul>

        @if($reviews->hasPages())
            <div class="mt-3">{{ $reviews->links() }}</div>
        @endif

    @endif

</div>
