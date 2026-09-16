@props(['journal', 'metricNames', 'chartMetric', 'series'])

@if($metricNames->isNotEmpty())
    <div class="journal-panel">
        @if($metricNames->count() > 1)
            {{-- ĐỔI CHỈ SỐ BẰNG ĐƯỜNG DẪN, không bằng JavaScript. --}}
            <div class="filter-chip-group mb-3">
                @foreach($metricNames as $ten)
                    <a href="{{ route('shop.journals.show', ['journal' => $journal, 'chi-so' => $ten]) }}"
                       class="filter-chip {{ $chartMetric === $ten ? 'is-active' : '' }}">{{ $ten }}</a>
                @endforeach
            </div>
        @endif

        <x-journal.metric-chart :series="$series" :metric="$chartMetric" />
    </div>
@endif
