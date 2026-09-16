@extends('layouts.app')

@section('title', 'Góc cây của ' . ($post->user?->name ?? 'Khách'))

@section('meta_description', \Illuminate\Support\Str::limit($post->body, 150))

@section('content')

<section class="section-sm">
    <div class="container-shop community-post">

        <x-site.breadcrumb :items="[['label' => 'Góc cây của bạn', 'url' => route('shop.community.index')], ['label' => 'Bài viết']]" />

        <p class="mb-3"><a href="{{ route('shop.community.index') }}">&larr; Quay lại Góc cây</a></p>

        <article class="gc-bai gc-bai--don" id="bai-{{ $post->id }}" data-bai="{{ $post->id }}">

            <header class="gc-bai__head">
                <span class="avatar" aria-hidden="true">{{ mb_substr($post->user?->name ?? 'K', 0, 1) }}</span>

                <div class="gc-bai__who">
                    @if($post->user)
                        <a href="{{ route('shop.community.profile', $post->user->id) }}" class="gc-bai__author">{{ $post->user->name }}</a>
                    @else
                        <span class="gc-bai__author">Người dùng đã xoá</span>
                    @endif
                    <p class="gc-bai__meta mb-0">
                        <x-site.time :at="$post->approved_at" relative />
                        @if($post->edited_at)
                            <span>· đã chỉnh sửa</span>
                        @endif
                    </p>
                </div>

                <details class="post-menu">
                    <summary class="post-menu__toggle" title="Tuỳ chọn bài">
                        <x-site.icon name="three-dots" label="Tuỳ chọn bài" />
                    </summary>

                    <div class="post-menu__list">
                        <button type="button" class="post-menu__item" data-copy="{{ route('shop.community.show', $post->id) }}">
                            <x-site.icon name="link-45deg" /> Sao chép liên kết
                        </button>

                        @auth
                            <form method="POST" action="{{ route('shop.community.save', $post->id) }}" data-toggle-json data-loai="luu">
                                @csrf
                                <button type="submit" class="post-menu__item" data-luu="{{ $post->id }}">
                                    <x-site.icon :name="$daLuu ? 'bookmark-fill' : 'bookmark'" data-icon-luu />
                                    <span data-nhan-luu>{{ $daLuu ? 'Bỏ lưu bài' : 'Lưu bài' }}</span>
                                </button>
                            </form>

                            @if(auth()->id() === $post->user_id)
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
                            @else
                                <button type="button" class="post-menu__item" data-bao-cao data-loai="post" data-id="{{ $post->id }}"
                                        data-bs-toggle="modal" data-bs-target="#hop-bao-cao">
                                    <x-site.icon name="flag" /> Báo cáo bài
                                </button>
                            @endif
                        @endauth
                    </div>
                </details>
            </header>

            {{-- Chữ người lạ gửi lên: luôn escape, cùng luật với bảng tin. --}}
            @if(trim((string) $post->body) !== '')
                <p class="gc-bai__text">{{ $post->body }}</p>
            @endif

            {{-- Trang một bài xem ĐỦ tệp, cỡ lớn, video có nút điều khiển. --}}
            @if($post->media->isNotEmpty())
                <div class="media-full">
                    @foreach($post->media as $m)
                        @if($m->laVideo())
                            <video class="media-full__item" controls preload="metadata" playsinline
                                   src="{{ $m->url() }}"
                                   aria-label="Video do {{ $post->user?->name ?? 'khách' }} chia sẻ"></video>
                        @else
                            <x-site.image :path="$m->path"
                                          :alt="'Ảnh do ' . ($post->user?->name ?? 'khách') . ' chia sẻ'"
                                          class="media-full__item" />
                        @endif
                    @endforeach
                </div>
            @endif

            @if($post->product)
                <a href="{{ route('shop.products.show', $post->product) }}" class="gc-bai__product">
                    <x-site.icon name="flower2" /> Cây trong bài: {{ $post->product->name }}
                </a>
            @endif

            <div class="gc-bai__stats">
                <x-community.reaction-summary :tom-tat="$tomTatCamXuc[$post->id] ?? []" :count="$post->likers_count" />
                <span>{{ $post->so_binh_luan }} bình luận</span>
            </div>

            <div class="gc-bai__actions">
                <x-community.like-button :post="$post" :cam-xuc="$camXucCuaToi[$post->id] ?? null" :count="$post->likers_count" />

                <a href="#binh-luan" class="post-action">
                    <x-site.icon name="chat" />
                    <span class="post-action__nhan">Bình luận</span>
                </a>

                <x-community.save-button :post="$post" :saved="$daLuu" />
            </div>
        </article>

        <div class="surface-card p-4 mt-3" id="binh-luan">
            <h2 class="text-h4 mb-3">Bình luận ({{ $post->so_binh_luan }})</h2>

            @auth
                @if($coTheBinhLuan)
                    <form method="POST" action="{{ route('shop.community.comment', $post->id) }}" class="comment-form comment-form--nhanh mb-4">
                        @csrf
                        <label class="visually-hidden" for="binh-luan-moi">Viết bình luận</label>
                        <textarea id="binh-luan-moi" name="body" rows="2" required
                                  maxlength="{{ \App\Services\Community\CommunityInteraction::DO_DAI_BINH_LUAN }}"
                                  class="form-control @error('body') is-invalid @enderror"
                                  placeholder="Hỏi cách chăm, khen cây, hoặc kể chuyện của bạn…">{{ old('body') }}</textarea>
                        <x-community.emoji-picker target="#binh-luan-moi" />
                        <button type="submit" class="btn btn-primary-brand btn-sm">Gửi</button>
                    </form>
                    <x-form-error name="body" />
                @else
                    {{-- Nói luật ngay tại chỗ, không để khách tìm ô bình luận không có. --}}
                    <p class="text-caption mb-4" data-khong-binh-luan>
                        Xác thực email của tài khoản để bình luận.
                        <a href="{{ route('verification.notice') }}">Gửi lại thư xác thực</a>.
                    </p>
                @endif
            @else
                <p class="text-caption mb-4">
                    <a href="{{ route('login', ['redirect' => route('shop.community.show', $post->id, false)]) }}">Đăng nhập</a>
                    để bình luận — ai có tài khoản cũng hỏi đáp được.
                </p>
            @endauth

            @forelse($comments as $bl)
                @include('shop.community.partials.comment', [
                    'bl' => $bl,
                    'traLoi' => $bl->replies,
                    'post' => $post,
                    'coTheBinhLuan' => $coTheBinhLuan,
                    'camXucBL' => $camXucBL,
                ])
            @empty
                <p class="text-caption mb-0">Chưa có bình luận nào. Bạn mở lời trước nhé.</p>
            @endforelse
        </div>

    </div>
</section>

@include('shop.community.partials.report-modal')

@endsection
