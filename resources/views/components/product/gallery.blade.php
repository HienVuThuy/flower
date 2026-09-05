@props(['product'])

@php
    $paths = $product->galleryPaths();
@endphp

<div class="product-gallery" data-gallery>

    <div class="product-gallery__main">
        @if($paths)
            {{--
                ẢNH CHÍNH — thứ quyết định mốc LCP của trang này.

                Viết tay <picture> thay vì dùng x-site.image vì JavaScript
                phải ĐỔI được ảnh khi khách bấm ảnh nhỏ, và nó cần đổi cả
                <source> lẫn <img>. Component dùng chung không có chỗ bám
                cho việc đó, mà thêm vào chỉ để phục vụ đúng một nơi thì
                component thành khó hiểu cho tám nơi còn lại.

                KHÔNG lazy: ảnh này nằm ngay đầu trang. Đặt lazy cho nó là
                tự làm chậm chính con số đo "trang đã dùng được chưa".
            --}}
            @php
                $anh = app(\App\Services\Media\ResponsiveImage::class);
                $srcsetChinh = $anh->webpSrcset($paths[0]);
                $coChinh = $anh->info($paths[0]);
            @endphp

            <picture>
                @if($srcsetChinh)
                    <source type="image/webp" srcset="{{ $srcsetChinh }}"
                            sizes="(max-width: 991px) 100vw, 560px"
                            data-gallery-source>
                @endif

                <img
                    src="{{ asset('storage/' . $paths[0]) }}"
                    alt="{{ $product->name }}"
                    data-gallery-main
                    @if($coChinh)
                        width="{{ $coChinh['width'] }}"
                        height="{{ $coChinh['height'] }}"
                    @endif
                    loading="eager"
                    fetchpriority="high"
                >
            </picture>
        @else
            <div class="product-gallery__placeholder">
                <x-site.leaf-placeholder />
            </div>
        @endif
    </div>

    @if(count($paths) > 1)
        <div class="product-gallery__thumbs" role="group" aria-label="Ảnh sản phẩm">
            @foreach($paths as $i => $path)
                <button
                    type="button"
                    class="product-gallery__thumb {{ $i === 0 ? 'is-active' : '' }}"
                    data-gallery-thumb
                    data-src="{{ asset('storage/' . $path) }}"
                    {{-- JavaScript cần cả bộ srcset để đổi luôn <source>. --}}
                    data-srcset="{{ app(\App\Services\Media\ResponsiveImage::class)->webpSrcset($path) }}"
                    aria-label="Xem ảnh {{ $i + 1 }}"
                    @if($i === 0) aria-current="true" @endif
                >
                    <x-site.image :path="$path" alt=""
                                  sizes="72px" />
                </button>
            @endforeach
        </div>
    @endif

</div>
