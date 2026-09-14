@extends('layouts.app')

@section('title', 'Góc cây của bạn')

@section('meta_description', 'Ảnh cây, ban công và góc xanh do chính khách hàng Angevil chia sẻ.')

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="[['label' => 'Góc cây của bạn']]" />

        <div class="section-header">
            <div>
                <h1 class="text-h1 section-header__title">Góc cây của bạn</h1>
                <p class="text-body-sm mt-2 mb-0" style="max-width: 62ch;">
                    Ảnh cây, ban công, góc bàn làm việc — do chính người mua chia sẻ.
                    Không có người theo dõi, không có bảng tin, chỉ là chỗ để khoe cây.
                </p>
            </div>
        </div>

        <div class="row g-4">

            {{-- ---------- CỘT TRÁI: ẢNH MỌI NGƯỜI ĐĂNG ---------- --}}
            <div class="col-lg-8">
                @if($posts->isEmpty())
                    <x-site.empty-state
                        title="Chưa có bài nào"
                        text="Chưa ai đăng gì ở đây. Bạn có thể là người đầu tiên." />
                @else
                    <div class="row g-3">
                        @foreach($posts as $post)
                            <div class="col-sm-6">
                                <article class="community-card">
                                    @if($post->photo)
                                        <x-site.image :path="$post->photo"
                                                      :alt="'Ảnh do ' . $post->user?->name . ' chia sẻ'"
                                                      class="community-card__img" />
                                    @endif

                                    <div class="community-card__body">
                                        {{--
                                            NỘI DUNG ĐƯỢC ESCAPE.

                                            Đây là chữ người lạ gửi lên. Bài Cẩm nang
                                            in HTML thô vì chỉ admin viết được và đã qua
                                            HtmlSanitizer; ở đây thì không, và cũng
                                            không cần — một dòng khoe cây không cần
                                            định dạng.
                                        --}}
                                        <p class="community-card__text">{{ $post->body }}</p>

                                        @if($post->product)
                                            <a href="{{ route('shop.products.show', $post->product) }}"
                                               class="community-card__product">
                                                Cây trong ảnh: {{ $post->product->name }}
                                            </a>
                                        @endif

                                        <p class="community-card__meta">
                                            {{ $post->user?->name ?? 'Khách' }}
                                            &middot; <x-site.time :at="$post->approved_at" relative />
                                        </p>
                                    </div>
                                </article>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4">{{ $posts->links() }}</div>
                @endif
            </div>

            {{-- ---------- CỘT PHẢI: ĐĂNG BÀI ---------- --}}
            <div class="col-lg-4">
                <div class="surface-card p-4 community-compose">
                    <h2 class="text-h4 mb-2">Khoe cây của bạn</h2>

                    @auth
                        <p class="text-body-sm">
                            Bài sẽ được cửa hàng duyệt trước khi hiện — thường trong ngày.
                        </p>

                        {{-- Luật thưởng nói trước, đọc từ đúng hằng số đang tính — không ghi tay con số. --}}
                        @php $thuong = \App\Services\Points\CommunityReward::class; @endphp
                        <p class="text-body-sm" data-luat-thuong>
                            Bài được duyệt: <strong>+{{ $thuong::CO_BAN }} điểm</strong>, có ảnh thêm {{ $thuong::CO_ANH }},
                            bài nổi bật thêm {{ $thuong::NOI_BAT }}. Tối đa {{ $thuong::TOI_DA_MOI_TUAN }} bài được thưởng mỗi tuần.
                            <a href="{{ route('shop.profile.edit', ['muc' => 'diem-thuong']) }}">Đổi điểm lấy voucher</a>.
                        </p>

                        {{--
                            NÓI THẲNG VỀ VIỆC TƯỚC METADATA.

                            Khách không biết ảnh điện thoại mang theo toạ độ GPS. Hệ
                            thống tự xoá là đúng, nhưng nói ra thì họ yên tâm đăng —
                            và người đã biết thì không phải tự hỏi.
                        --}}
                        <p class="text-body-sm community-compose__privacy">
                            Ảnh bạn tải lên được <strong>tự động xoá thông tin ẩn</strong>
                            (vị trí GPS, loại máy, giờ chụp) trước khi lưu.
                        </p>

                        <form method="POST" action="{{ route('shop.community.store') }}"
                              enctype="multipart/form-data">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label" for="body">Bạn muốn kể gì?</label>
                                <textarea name="body" id="body" rows="4"
                                          class="form-control @error('body') is-invalid @enderror"
                                          maxlength="1000" required
                                          placeholder="Cây monstera mua hồi tháng 6, giờ ra thêm bốn lá.">{{ old('body') }}</textarea>
                                <x-form-error name="body"/>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="photo">Ảnh</label>
                                <input type="file" name="photo" id="photo" class="form-control"
                                       accept="image/png,image/jpeg,image/webp">
                                <x-form-error name="photo"/>
                            </div>

                            @if($cayDaMua->isNotEmpty())
                                <div class="mb-3">
                                    <label class="form-label" for="product_id">Cây trong ảnh</label>
                                    <select name="product_id" id="product_id" class="form-select">
                                        <option value="">— Không gắn —</option>
                                        @foreach($cayDaMua as $p)
                                            <option value="{{ $p->id }}" @selected((int) old('product_id') === $p->id)>
                                                {{ $p->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    {{-- Chỉ cây đã mua — cùng luật với nhật ký (QĐ-129). --}}
                                    <p class="form-text">Chỉ liệt kê cây bạn đã mua ở đây.</p>
                                    <x-form-error name="product_id"/>
                                </div>
                            @endif

                            <button type="submit" class="btn btn-primary-brand w-100">Gửi bài</button>
                        </form>
                    @else
                        <p class="text-body-sm">Đăng nhập để chia sẻ ảnh cây của bạn.</p>
                        <a href="{{ route('login', ['redirect' => route('shop.community.index', [], false)]) }}"
                           class="btn btn-secondary-brand w-100">Đăng nhập</a>
                    @endauth
                </div>

                {{-- ---------- BÀI CỦA TÔI ---------- --}}
                @if($cuaToi->isNotEmpty())
                    {{--
                        NGƯỜI VỪA GỬI BÀI PHẢI THẤY NÓ Ở ĐÂU ĐÓ.

                        Gửi xong mà màn hình không đổi gì thì họ tưởng hỏng và gửi
                        lại — rồi admin có ba bài giống hệt để duyệt.
                    --}}
                    <div class="surface-card p-4 mt-3">
                        <h2 class="text-h4 mb-3">Bài của bạn</h2>

                        @foreach($cuaToi as $bai)
                            <div class="my-post">
                                <span class="status-pill status-pill--{{ $bai->statusBadge() }}">
                                    {{ $bai->statusText() }}
                                </span>

                                <p class="my-post__text">{{ \Illuminate\Support\Str::limit($bai->body, 90) }}</p>

                                @isset($diemBai[$bai->id])
                                    <p class="my-post__reason" data-diem-bai="{{ $bai->id }}">+{{ $diemBai[$bai->id] }} điểm</p>
                                @endisset

                                @if($bai->isRejected() && $bai->reject_reason)
                                    {{-- Lý do từ chối hiện lại cho chính người đăng:
                                         từ chối im lặng thì họ đăng lại y hệt. --}}
                                    <p class="my-post__reason">Lý do: {{ $bai->reject_reason }}</p>
                                @endif

                                <form method="POST" action="{{ route('shop.community.destroy', $bai->id) }}"
                                      onsubmit="return confirm('Xoá bài này?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-sm">Xoá</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>
    </div>
</section>

@endsection
