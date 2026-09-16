@props(['journal'])

@php
    $tk = $journal->ratingSummary();
@endphp

@if($tk)
    <div class="journal-panel">
        <div class="journal-panel__head">
            <h2 class="text-h3 mb-0">Đánh giá của bạn</h2>
            <span class="journal-panel__meta">{{ $tk['count'] }} lần chấm</span>
        </div>

        <div class="rating-summary">
            <span class="rating-summary__value">{{ number_format($tk['avg'], 1, ',', '.') }}</span>
            <span class="rating-summary__max">/ 5</span>

            <span class="rating-summary__stars" role="img"
                  aria-label="Trung bình {{ number_format($tk['avg'], 1, ',', '.') }} trên 5">
                @for($i = 1; $i <= 5; $i++)
                    <x-site.icon :name="$i <= round($tk['avg']) ? 'star-fill' : 'star'"
                                 class="rating-summary__star" />
                @endfor
            </span>
        </div>

        <p class="text-body-sm mb-0">
            Đây là điểm bạn tự chấm qua các lần quan sát — chỉ mình bạn thấy,
            không liên quan tới đánh giá công khai trên trang sản phẩm.
        </p>
    </div>
@endif
