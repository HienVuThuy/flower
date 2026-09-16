@extends('layouts.app')

@section('title', $post->metaTitle())
@section('meta_description', $post->metaDescription())

@section('content')

<article class="section-sm">
    <div class="container-shop" style="max-width: 48rem;">

        <x-site.breadcrumb :items="array_filter([
            ['label' => 'Cẩm nang', 'url' => route('shop.blog.index')],
            $post->category ? ['label' => $post->category->name,
                'url' => route('shop.blog.index', ['chuyen-muc' => $post->category->slug])] : null,
            ['label' => $post->title],
        ])" />

        <header class="post-header">
            @if($post->category)
                <a href="{{ route('shop.blog.index', ['chuyen-muc' => $post->category->slug]) }}"
                   class="text-label post-header__cat">{{ $post->category->name }}</a>
            @endif

            <h1 class="text-h1 post-header__title">{{ $post->title }}</h1>

            <p class="post-header__meta">
                @if($post->author)
                    {{ $post->author->name }} &middot;
                @endif
                <x-site.time :at="$post->published_at" format="d/m/Y" />
                &middot; khoảng {{ $post->readingMinutes() }} phút đọc
            </p>
        </header>

        @if($post->cover_image)
            <x-site.image :path="$post->cover_image" :alt="$post->title" class="post-cover" />
        @endif

        <div class="post-body">{!! $post->body !!}</div>

        @if($post->products->isNotEmpty())
            <section class="post-products">
                <h2 class="text-h3 mb-3">Cây nhắc trong bài</h2>

                <div class="row g-3">
                    @foreach($post->products as $product)
                        <div class="col-sm-6 col-lg-4">
                            <x-product.card :product="$product" :ref="'cam-nang:' . $post->slug" />

                            @if($product->pivot->note)
                                <p class="post-products__note">{{ $product->pivot->note }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if($lienQuan->isNotEmpty())
            <section class="post-related">
                <h2 class="text-h3 mb-3">Đọc tiếp</h2>

                <div class="row g-3">
                    @foreach($lienQuan as $khac)
                        <div class="col-md-4">
                            <a href="{{ route('shop.blog.show', $khac) }}" class="post-card post-card--compact">
                                <span class="post-card__body">
                                    @if($khac->category)
                                        <span class="text-label post-card__cat">{{ $khac->category->name }}</span>
                                    @endif
                                    <span class="post-card__title">{{ $khac->title }}</span>
                                </span>
                            </a>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

    </div>
</article>

@endsection
