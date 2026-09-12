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

        {{--
            NỘI DUNG BÀI — CHỖ DUY NHẤT TRONG DỰ ÁN IN HTML THÔ.
            ============================================================
            Bài viết cần đoạn văn, tiêu đề phụ, danh sách, chữ đậm. Escape
            hết thì admin nhìn thấy `<p>` hiện ra thành chữ.

            AN TOÀN ĐƯỢC vì hai điều kiện, và cả hai phải giữ:

              1. CHỈ ADMIN viết được. Route ghi nằm sau `role:admin`.
                 Đây không phải nội dung người lạ gửi lên — mục đó là
                 "Góc cây của bạn", và ở đó nội dung được escape.

              2. Nội dung đã đi qua `HtmlSanitizer` lúc lưu, nên thẻ
                 `<script>`, `on*=` và `javascript:` bị gỡ ngay khi ghi
                 vào cơ sở dữ liệu — không phải lúc hiển thị.

            Làm sạch LÚC LƯU chứ không lúc hiện: nếu để lúc hiện thì mỗi
            chỗ in bài ra phải nhớ gọi, và chỗ thứ ba sẽ quên.
        --}}
        <div class="post-body">{!! $post->body !!}</div>

        {{-- ---------- SẢN PHẨM NHẮC TRONG BÀI ---------- --}}
        @if($post->products->isNotEmpty())
            {{--
                THỨ BIẾN BÀI VIẾT THÀNH DOANH THU.

                Khách đọc xong "7 loại cây để bàn ít cần ánh sáng" mà phải
                tự đi tìm từng cây trong danh mục thì phần lớn sẽ không
                tìm. Nút ngay dưới bài là quãng đường ngắn nhất từ "à ra
                thế" tới "mua cái này".

                `note` là lý do RIÊNG của bài này khi nhắc cây đó — không
                lấy mô tả chung của sản phẩm, vì cùng một cây ở ba bài
                khác nhau có ba lý do khác nhau.
            --}}
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

        {{-- ---------- BÀI LIÊN QUAN ---------- --}}
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
