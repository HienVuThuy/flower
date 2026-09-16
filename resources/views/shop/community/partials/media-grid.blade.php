{{-- LƯỚI ẢNH / VIDEO của một bài trên bảng tin. --}}
@php
    $ds = $post->media;
    $tong = $ds->count();
@endphp

@if($tong > 0)
    <div class="media-grid media-grid--{{ min($tong, 4) }}" data-so-media="{{ $tong }}">
        @foreach($ds->take(4) as $i => $m)
            <a href="{{ route('shop.community.show', $post->id) }}" class="media-grid__item">
                @if($m->laVideo())
                    <video class="media-grid__media" preload="metadata" muted playsinline
                           src="{{ $m->url() }}#t=0.1"
                           aria-label="Video do {{ $post->user?->name ?? 'khách' }} chia sẻ"></video>
                    <span class="media-grid__play" aria-hidden="true"><x-site.icon name="camera-video" /></span>
                @else
                    <x-site.image :path="$m->path"
                                  :alt="'Ảnh do ' . ($post->user?->name ?? 'khách') . ' chia sẻ'"
                                  class="media-grid__media" />
                @endif

                @if($i === 3 && $tong > 4)
                    <span class="media-grid__more">+{{ $tong - 4 }}</span>
                @endif
            </a>
        @endforeach
    </div>
@endif
