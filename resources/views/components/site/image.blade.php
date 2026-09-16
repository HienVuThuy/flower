@props([
    'path',

    'alt' => '',

    'eager' => false,

    'sizes' => '(max-width: 575px) 45vw, (max-width: 991px) 30vw, 300px',
])

@php
    $anh = app(\App\Services\Media\ResponsiveImage::class);
    $info = $anh->info($path);
    $srcset = $anh->webpSrcset($path);
    $goc = $path ? \Illuminate\Support\Facades\Storage::url($path) : null;
@endphp

@if($goc)
    <picture>
        @if($srcset)
            <source type="image/webp" srcset="{{ $srcset }}" sizes="{{ $sizes }}">
        @endif

        <img
            src="{{ $goc }}"
            alt="{{ $alt }}"

            @if($info)
                width="{{ $info['width'] }}"
                height="{{ $info['height'] }}"
            @endif

            @if($eager)
                loading="eager"
                fetchpriority="high"
            @else
                loading="lazy"
                decoding="async"
            @endif

            {{ $attributes }}
        >
    </picture>
@endif
