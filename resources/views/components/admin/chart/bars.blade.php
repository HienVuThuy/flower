@props([
    'rows' => [],
    'title' => '',
    'note' => null,
    'format' => 'so',
    'empty' => 'Chưa có dữ liệu trong kỳ này.',
])

@php
    $rows = collect($rows)->values();
    $max = (float) $rows->max('value');

    $tien = fn ($v) => \App\Services\Shop\Money::format((string) round($v));
    $soChu = fn ($v) => $format === 'tien' ? $tien($v) : number_format($v, 0, ',', '.');
@endphp

<div class="viz viz-card">

    <div class="viz-card__head">
        <h3 class="viz-card__title">{{ $title }}</h3>
        @if($note)
            <p class="viz-card__note">{{ $note }}</p>
        @endif
    </div>

    @if($rows->isEmpty() || $max <= 0)
        <p class="analytics-empty mb-0">{{ $empty }}</p>
    @else
        <div class="viz-bars">
            @foreach($rows as $r)
                @php $phan = $max > 0 ? (float) $r['value'] / $max * 100 : 0; @endphp

                <div class="viz-bars__row">
                    <span class="viz-bars__name" title="{{ $r['label'] }}">{{ $r['label'] }}</span>

                    <span class="viz-bars__track">
                        <span class="viz-bars__fill" style="width: {{ round($phan, 2) }}%"></span>
                    </span>

                    <span class="viz-bars__value">
                        {{ $soChu((float) $r['value']) }}
                        @if(! empty($r['meta']))
                            <span class="text-muted">&middot; {{ $r['meta'] }}</span>
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
    @endif

</div>
