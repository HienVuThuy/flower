@props([
    'value' => null,
    'count' => null,
    'showEmpty' => false,
])

{{--
    Dải sao đánh giá.

    Dùng icon SVG trong sprite (Bootstrap Icons, MIT) chứ không dùng ký tự
    emoji ★ — emoji hiển thị khác nhau trên từng hệ điều hành và không
    nhận màu của giao diện.

    value = null nghĩa là CHƯA CÓ đánh giá nào. Mặc định không hiện gì:
    một dải 5 sao rỗng trông như "sản phẩm bị chấm 0 sao", trong khi sự
    thật chỉ là chưa ai viết.
--}}

@php
    $avg = $value === null ? null : (float) $value;
@endphp

@if($avg === null)

    @if($showEmpty)
        <span class="rating rating--empty text-caption">Chưa có đánh giá</span>
    @endif

@else

    <span {{ $attributes->merge(['class' => 'rating']) }}
          role="img"
          aria-label="{{ number_format($avg, 1, ',', '.') }} trên 5 sao{{ $count !== null ? ', ' . $count . ' đánh giá' : '' }}">

        <span class="rating__stars" aria-hidden="true">
            @for($i = 1; $i <= 5; $i++)
                @if($avg >= $i)
                    <x-site.icon name="star-fill" class="rating__star rating__star--on" />
                @elseif($avg >= $i - 0.5)
                    <x-site.icon name="star-half" class="rating__star rating__star--on" />
                @else
                    <x-site.icon name="star" class="rating__star" />
                @endif
            @endfor
        </span>

        <span class="rating__value" aria-hidden="true">{{ number_format($avg, 1, ',', '.') }}</span>

        @if($count !== null)
            <span class="rating__count" aria-hidden="true">({{ $count }})</span>
        @endif
    </span>

@endif
