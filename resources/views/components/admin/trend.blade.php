@props(['now', 'before' => null, 'invert' => false, 'format' => 'so'])

{{-- So sánh một chỉ số với kỳ trước. --}}

@php
    $change = $before === null
        ? null
        : \App\Services\Analytics\AnalyticsService::change((float) $now, (float) $before);

    $good = $change === null ? null : ($invert ? $change < 0 : $change > 0);

    $moc = $before === null ? null : ($format === 'tien'
        ? \App\Services\Shop\Money::format((string) round((float) $before))
        : number_format((float) $before, 0, ',', '.'));
@endphp

@if($before !== null)
    <span class="admin-trend {{ $change === null ? 'admin-trend--flat' : ($good ? 'admin-trend--up' : 'admin-trend--down') }}">
        @if($change === null)
            kỳ trước chưa có dữ liệu
        @elseif($change == 0)
            không đổi so với kỳ trước
        @else
            {{ $change > 0 ? '▲' : '▼' }}
            {{ number_format(abs($change), 1, ',', '.') }}%
            <span class="admin-trend__base">so với {{ $moc }} kỳ trước</span>
        @endif
    </span>
@endif
