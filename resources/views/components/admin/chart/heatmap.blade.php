@props([
    'o' => [],
    'hang' => [],
    'cot' => [],
    'title' => '',
    'note' => null,
    'unit' => 'đơn',
])

@php
    $max = 0;
    foreach ($o as $dong) {
        $max = max($max, ...array_values($dong ?: [0]));
    }

    $bac = fn (int $v) => $v <= 0 || $max <= 0 ? 0 : min(5, (int) ceil($v / $max * 5));

    $khoangBac = [];
    for ($b = 1; $b <= 5; $b++) {
        $tu = (int) floor(($b - 1) * $max / 5) + 1;
        $den = (int) floor($b * $max / 5);
        if ($den >= $tu) {
            $khoangBac[$b] = $tu === $den ? (string) $tu : $tu . '–' . $den;
        }
    }
@endphp

<div class="viz viz-card">

    <div class="viz-card__head">
        <h3 class="viz-card__title">{{ $title }}</h3>
        @if($note)
            <p class="viz-card__note">{{ $note }}</p>
        @endif
    </div>

    @if($max <= 0)
        <p class="analytics-empty mb-0">Chưa có dữ liệu trong kỳ này.</p>
    @else
        <div class="viz-heat" role="img" aria-label="{{ $title }}. Xem bảng số bên dưới.">
            <div class="viz-heat__grid" style="--so-cot: {{ count($cot) }}">
                <span></span>
                @foreach($cot as $j => $nhan)
                    <span class="viz-heat__col-label">{{ $j % 3 === 0 ? $nhan : '' }}</span>
                @endforeach

                @foreach($hang as $i => $nhanHang)
                    <span class="viz-heat__row-label">{{ $nhanHang }}</span>
                    @foreach($cot as $j => $nhanCot)
                        @php $v = (int) ($o[$i][$j] ?? 0); @endphp
                        <span class="viz-heat__cell viz-heat__cell--{{ $bac($v) }}"
                              title="{{ $nhanHang }}, {{ $nhanCot }}: {{ $v }} {{ $unit }}"></span>
                    @endforeach
                @endforeach
            </div>
        </div>

        <div class="viz-heat__legend" aria-hidden="true">
            <span class="viz-heat__legend-item"><span class="viz-heat__cell viz-heat__cell--0"></span> 0</span>
            @foreach($khoangBac as $b => $nhan)
                <span class="viz-heat__legend-item"><span class="viz-heat__cell viz-heat__cell--{{ $b }}"></span> {{ $nhan }}</span>
            @endforeach
            <span class="text-muted">{{ $unit }}</span>
        </div>

        <details class="viz-details">
            <summary>Xem bảng số</summary>
            <div class="table-responsive">
                <table class="viz-table">
                    <thead>
                        <tr>
                            <th></th>
                            @foreach($cot as $nhan)<th>{{ $nhan }}</th>@endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($hang as $i => $nhanHang)
                            <tr>
                                <th>{{ $nhanHang }}</th>
                                @foreach($cot as $j => $nhan)<td>{{ (int) ($o[$i][$j] ?? 0) }}</td>@endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif

</div>
