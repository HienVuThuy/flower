{{--
    MỘT BÀI TRÊN BẢNG TIN.

    Thứ tự đọc: ai đăng → bài viết gì → ảnh / video → cây gắn kèm → số lượt →
    hành động → vài bình luận gần nhất → ô bình luận. Đúng thứ tự người ta đọc
    một bài trên mạng xã hội, nên không phải học lại.

    Menu "⋯" là <details> chứ không phải dropdown JavaScript: lưu bài, sao chép
    liên kết, sửa / xoá bài của mình, báo cáo bài người khác.
--}}
@php
    $laCuaToi = auth()->id() === $post->user_id;
    $camXucBai = ($camXucCuaToi ?? [])[$post->id] ?? null;
    $daLuuBai = in_array($post->id, $daLuu ?? [], true);
    $dangHien = $post->isApproved() && ! $post->isHidden();
    $binhLuanXemTruoc = $post->relationLoaded('comments') ? $post->comments->sortBy('created_at') : collect();
@endphp

<article class="gc-bai" id="bai-{{ $post->id }}" data-bai="{{ $post->id }}">

    <header class="gc-bai__head">
        <span class="avatar" aria-hidden="true">{{ mb_substr($post->user?->name ?? 'K', 0, 1) }}</span>

        <div class="gc-bai__who">
            <span class="gc-bai__author">{{ $post->user?->name ?? 'Người dùng đã xoá' }}</span>
            <p class="gc-bai__meta mb-0">
                <a href="{{ route('shop.community.show', $post->id) }}" class="gc-bai__time">
                    <x-site.time :at="$post->approved_at ?? $post->created_at" relative />
                </a>
                @if($post->edited_at)
                    <span>· đã chỉnh sửa</span>
                @endif
                @if(! $dangHien)
                    <span class="status-pill status-pill--{{ $post->statusBadge() }}">{{ $post->statusText() }}</span>
                @endif
            </p>
        </div>

        <details class="post-menu">
            <summary class="post-menu__toggle" title="Tuỳ chọn bài">
                <x-site.icon name="three-dots" label="Tuỳ chọn bài" />
            </summary>

            <div class="post-menu__list">
                @auth
                    @if($dangHien)
                        <form method="POST" action="{{ route('shop.community.save', $post->id) }}" data-toggle-json data-loai="luu">
                            @csrf
                            <button type="submit" class="post-menu__item" data-luu="{{ $post->id }}">
                                <x-site.icon :name="$daLuuBai ? 'bookmark-fill' : 'bookmark'" data-icon-luu />
                                <span data-nhan-luu>{{ $daLuuBai ? 'Bỏ lưu bài' : 'Lưu bài' }}</span>
                            </button>
                        </form>
                    @endif
                @endauth

                @if($dangHien)
                    <button type="button" class="post-menu__item" data-copy="{{ route('shop.community.show', $post->id) }}">
                        <x-site.icon name="link-45deg" /> Sao chép liên kết
                    </button>
                @endif

                @if($laCuaToi)
                    <a href="{{ route('shop.community.edit', $post->id) }}" class="post-menu__item">
                        <x-site.icon name="pencil" /> Sửa bài
                    </a>
                    <form method="POST" action="{{ route('shop.community.destroy', $post->id) }}"
                          onsubmit="return confirm('Xoá bài này? Ảnh, video và bình luận của bài cũng bị xoá.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="post-menu__item post-menu__item--danger">
                            <x-site.icon name="trash" /> Xoá bài
                        </button>
                    </form>
                @elseif(auth()->check())
                    <button type="button" class="post-menu__item" data-bao-cao data-loai="post" data-id="{{ $post->id }}"
                            data-bs-toggle="modal" data-bs-target="#hop-bao-cao">
                        <x-site.icon name="flag" /> Báo cáo bài
                    </button>
                @endif
            </div>
        </details>
    </header>

    {{-- Chữ người lạ gửi lên: LUÔN escape. --}}
    @if(trim((string) $post->body) !== '')
        <p class="gc-bai__text">{{ $post->body }}</p>
    @endif

    @if($post->isRejected() && $post->reject_reason)
        {{-- Lý do hiện lại cho chính người đăng: từ chối im lặng thì họ đăng lại y hệt. --}}
        <p class="gc-bai__note">Lý do không được duyệt: {{ $post->reject_reason }}</p>
    @endif

    @if($post->isHidden() && $post->hidden_reason)
        <p class="gc-bai__note">Bài đang bị ẩn: {{ $post->hidden_reason }}</p>
    @endif

    @isset($diemBai[$post->id])
        <p class="gc-bai__diem" data-diem-bai="{{ $post->id }}">+{{ $diemBai[$post->id] }} điểm</p>
    @endisset

    @include('shop.community.partials.media-grid', ['post' => $post])

    @if($post->product)
        <a href="{{ route('shop.products.show', $post->product) }}" class="gc-bai__product">
            <x-site.icon name="flower2" /> Cây trong bài: {{ $post->product->name }}
        </a>
    @endif

    @if($dangHien)
        <div class="gc-bai__stats">
            <x-community.reaction-summary :tom-tat="($tomTatCamXuc ?? [])[$post->id] ?? []" :count="$post->likers_count ?? 0" />
            <a href="{{ route('shop.community.show', $post->id) }}#binh-luan" data-so-binh-luan="{{ $post->id }}">
                {{ $post->so_binh_luan ?? 0 }} bình luận
            </a>
        </div>

        <div class="gc-bai__actions">
            <x-community.like-button :post="$post" :cam-xuc="$camXucBai" :count="$post->likers_count ?? 0" />

            <a href="{{ route('shop.community.show', $post->id) }}#binh-luan" class="post-action">
                <x-site.icon name="chat" />
                <span class="post-action__nhan">Bình luận</span>
            </a>

            <x-community.save-button :post="$post" :saved="$daLuuBai" />
        </div>

        @if($binhLuanXemTruoc->isNotEmpty())
            <div class="gc-bai__comments">
                @foreach($binhLuanXemTruoc as $bl)
                    @include('shop.community.partials.comment', [
                        'bl' => $bl,
                        'traLoi' => [],
                        'post' => $post,
                        'coTheBinhLuan' => $coTheBinhLuan ?? false,
                    ])
                @endforeach

                @if(($post->so_binh_luan ?? 0) > $binhLuanXemTruoc->count())
                    <a href="{{ route('shop.community.show', $post->id) }}#binh-luan" class="gc-bai__xem-them">
                        Xem tất cả {{ $post->so_binh_luan }} bình luận
                    </a>
                @endif
            </div>
        @endif

        @auth
            @if($coTheBinhLuan ?? false)
                <form method="POST" action="{{ route('shop.community.comment', $post->id) }}" class="comment-form comment-form--nhanh">
                    @csrf
                    <label class="visually-hidden" for="bl-nhanh-{{ $post->id }}">Viết bình luận</label>
                    <textarea id="bl-nhanh-{{ $post->id }}" name="body" rows="1" required
                              maxlength="{{ \App\Services\Community\CommunityInteraction::DO_DAI_BINH_LUAN }}"
                              class="form-control" placeholder="Viết bình luận…"></textarea>
                    <x-community.emoji-picker :target="'#bl-nhanh-' . $post->id" />
                    <button type="submit" class="btn btn-primary-brand btn-sm">Gửi</button>
                </form>
            @else
                <p class="text-caption mb-0" data-khong-binh-luan>Xác thực email của tài khoản để bình luận.</p>
            @endif
        @endauth
    @endif

</article>
