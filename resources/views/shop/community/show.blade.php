@extends('layouts.app')

@section('title', 'Góc cây của ' . ($post->user?->name ?? 'Khách'))

@section('meta_description', \Illuminate\Support\Str::limit($post->body, 150))

@section('content')

<section class="section-sm">
    <div class="container-shop community-post">

        <x-site.breadcrumb :items="[['label' => 'Góc cây của bạn']]" />

        <p class="mb-3"><a href="{{ route('shop.community.index') }}">&larr; Quay lại Góc cây</a></p>

        <article class="community-card">
            @if($post->photo)
                <x-site.image :path="$post->photo" :alt="'Ảnh do ' . $post->user?->name . ' chia sẻ'" class="community-post__img" />
            @endif

            <div class="community-card__body">
                {{-- Chữ người lạ gửi lên: luôn escape, cùng luật với trang danh sách. --}}
                <p class="community-card__text">{{ $post->body }}</p>

                @if($post->product)
                    <a href="{{ route('shop.products.show', $post->product) }}" class="community-card__product">
                        Cây trong ảnh: {{ $post->product->name }}
                    </a>
                @endif

                <p class="community-card__meta">
                    {{ $post->user?->name ?? 'Khách' }} &middot; <x-site.time :at="$post->approved_at" relative />
                </p>

                <div class="community-card__actions">
                    <x-community.like-button :post="$post" :liked="$daThich" :count="$post->likers_count" />
                </div>
            </div>
        </article>

        <div class="surface-card p-4 mt-3" id="binh-luan">
            <h2 class="text-h4 mb-3">Bình luận ({{ $comments->count() }})</h2>

            @forelse($comments as $bl)
                <div class="community-comment" data-binh-luan="{{ $bl->id }}">
                    <p class="community-comment__meta mb-1">
                        <strong>{{ $bl->user?->name ?? 'Khách' }}</strong>
                        &middot; <x-site.time :at="$bl->created_at" relative />
                    </p>
                    <p class="community-comment__text mb-0">{{ $bl->body }}</p>

                    @if(auth()->id() === $bl->user_id)
                        <form method="POST" action="{{ route('shop.community.comment.destroy', $bl->id) }}"
                              onsubmit="return confirm('Gỡ bình luận này?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-sm">Gỡ</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="text-caption">Chưa có bình luận nào.</p>
            @endforelse

            @auth
                @if($coTheBinhLuan)
                    <form method="POST" action="{{ route('shop.community.comment', $post->id) }}" class="mt-3">
                        @csrf
                        <label class="form-label" for="binh-luan-body">Viết bình luận</label>
                        <textarea name="body" id="binh-luan-body" rows="2" maxlength="500" required
                                  class="form-control @error('body') is-invalid @enderror">{{ old('body') }}</textarea>
                        <x-form-error name="body" />
                        <button type="submit" class="btn btn-primary-brand btn-sm mt-2">Gửi</button>
                    </form>
                @else
                    {{-- Nói luật ngay tại chỗ, không để khách tìm ô bình luận không có. --}}
                    <p class="text-caption mt-3 mb-0" data-khong-binh-luan>
                        Bình luận dành cho khách đã nhận ít nhất một đơn hàng ở cửa hàng.
                    </p>
                @endif
            @else
                <p class="text-caption mt-3 mb-0">
                    <a href="{{ route('login') }}">Đăng nhập</a> để bình luận.
                </p>
            @endauth
        </div>

    </div>
</section>

@endsection
