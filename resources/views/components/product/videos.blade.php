@props(['product'])

@php $videos = $product->relationLoaded('videos') ? $product->videos : $product->videos()->get(); @endphp

@if($videos->isNotEmpty())
    {{-- VIDEO SẢN PHẨM --}}
    <section class="product-videos mt-4">
        <h2 class="text-h3 mb-3">Video sản phẩm</h2>

        <div class="product-videos__list">
            @foreach($videos as $video)
                @if($video->linkNhung())
                    <a class="product-video product-video--embed"
                       href="{{ $video->linkXem() }}"
                       target="_blank"
                       rel="noopener nofollow"
                       data-video-embed="{{ $video->linkNhung() }}"
                       data-video-title="Video {{ $product->name }}">
                        <span class="product-video__play" aria-hidden="true">▶</span>
                        <span class="product-video__label">
                            Xem video
                            <small>Bấm để mở trình phát ngay tại đây</small>
                        </span>
                    </a>
                @elseif($video->tepVideo())
                    <video class="product-video__file" controls preload="metadata"
                           src="{{ asset('storage/' . $video->tepVideo()) }}">
                        Trình duyệt không mở được video này.
                        <a href="{{ asset('storage/' . $video->tepVideo()) }}">Tải về để xem</a>.
                    </video>
                @endif
            @endforeach
        </div>
    </section>
@endif
