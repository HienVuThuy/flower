@extends('layouts.app')

@section('title', $dangLoc ? 'Cẩm nang · ' . $dangLoc->name : 'Cẩm nang chăm cây')

@section('meta_description', $dangLoc?->description
    ?: 'Hướng dẫn chăm cây, gợi ý chọn cây theo không gian, ý nghĩa các loài hoa — viết từ kinh nghiệm bán hàng thật tại Angevil.')

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="array_filter([
            ['label' => 'Cẩm nang', 'url' => $dangLoc ? route('shop.blog.index') : null],
            $dangLoc ? ['label' => $dangLoc->name] : null,
        ])" />

        <div class="section-header">
            <div>
                <h1 class="text-h1 section-header__title">
                    {{ $dangLoc?->name ?? 'Cẩm nang chăm cây' }}
                </h1>
                <p class="text-body-sm mt-2 mb-0" style="max-width: 62ch;">
                    {{ $dangLoc?->description
                        ?? 'Cây nào hợp bàn làm việc, bao lâu tưới một lần, hoa nào tặng dịp nào —
                            những câu hỏi khách hay hỏi trước khi mua, trả lời ở đây.' }}
                </p>
            </div>
        </div>

        @if($categories->isNotEmpty())
            <div class="filter-chip-group mb-4">
                <a href="{{ route('shop.blog.index') }}"
                   class="filter-chip {{ $dangLoc ? '' : 'is-active' }}">Tất cả</a>

                @foreach($categories as $c)
                    @if($c->posts_count > 0)
                        <a href="{{ route('shop.blog.index', ['chuyen-muc' => $c->slug]) }}"
                           class="filter-chip {{ $dangLoc?->id === $c->id ? 'is-active' : '' }}">
                            {{ $c->name }}
                            <span class="filter-chip__count">{{ $c->posts_count }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        @endif

        @if($posts->isEmpty())
            <x-site.empty-state
                title="Chưa có bài nào ở đây"
                text="Cẩm nang đang được viết. Quay lại sau nhé." />
        @else

            @if($noiBat)
                <a href="{{ route('shop.blog.show', $noiBat) }}" class="post-feature">
                    @if($noiBat->cover_image)
                        <x-site.image :path="$noiBat->cover_image" :alt="$noiBat->title"
                                      class="post-feature__img" />
                    @endif

                    <div class="post-feature__body">
                        @if($noiBat->category)
                            <span class="text-label post-feature__cat">{{ $noiBat->category->name }}</span>
                        @endif

                        <h2 class="text-h2 post-feature__title">{{ $noiBat->title }}</h2>

                        @if($noiBat->excerpt)
                            <p class="post-feature__excerpt">{{ $noiBat->excerpt }}</p>
                        @endif

                        <span class="post-card__meta">
                            <x-site.time :at="$noiBat->published_at" format="d/m/Y" />
                            &middot; khoảng {{ $noiBat->readingMinutes() }} phút đọc
                        </span>
                    </div>
                </a>
            @endif

            <div class="row g-4">
                @foreach($posts as $post)
                    <div class="col-md-6 col-lg-4">
                        <a href="{{ route('shop.blog.show', $post) }}" class="post-card">
                            @if($post->cover_image)
                                <x-site.image :path="$post->cover_image" :alt="$post->title"
                                              class="post-card__img" />
                            @else
                                <span class="post-card__img post-card__img--empty">
                                    <x-site.leaf-placeholder />
                                </span>
                            @endif

                            <span class="post-card__body">
                                @if($post->category)
                                    <span class="text-label post-card__cat">{{ $post->category->name }}</span>
                                @endif

                                <span class="post-card__title">{{ $post->title }}</span>

                                @if($post->excerpt)
                                    <span class="post-card__excerpt">{{ $post->excerpt }}</span>
                                @endif

                                <span class="post-card__meta">
                                    <x-site.time :at="$post->published_at" format="d/m/Y" />
                                    &middot; khoảng {{ $post->readingMinutes() }} phút đọc
                                </span>
                            </span>
                        </a>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">{{ $posts->links() }}</div>
        @endif

    </div>
</section>

@endsection
