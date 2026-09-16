@props(['product'])

@php $blocks = $product->relationLoaded('blocks') ? $product->blocks : $product->blocks()->get(); @endphp

@if($blocks->isNotEmpty())
    {{-- MÔ TẢ CHI TIẾT THEO KHỐI — chữ và ảnh xen kẽ theo đúng thứ tự người bán đã xếp ở trang quản trị. --}}
    <section class="product-blocks mt-5">
        @foreach($blocks as $khoi)
            @if($khoi->laAnh())
                <figure class="product-blocks__figure">
                    <x-site.image :path="$khoi->image_path" :alt="$khoi->caption ?? $product->name" />

                    @if($khoi->caption)
                        <figcaption>{{ $khoi->caption }}</figcaption>
                    @endif
                </figure>
            @else
                <div class="product-blocks__text">{!! $khoi->body !!}</div>
            @endif
        @endforeach
    </section>
@endif
