@props(['series', 'metric' => null])

@php
    $diem = collect($series);

    $veDuoc = $diem->count() >= 2;

    if ($veDuoc) {
        $W = 720;
        $H = 220;
        $L = 44;
        $R = 16;
        $T = 18;
        $B = 30;

        $giaTri = $diem->pluck('value');
        $min = (float) $giaTri->min();
        $max = (float) $giaTri->max();

        if ($max - $min < 0.0001) {
            $min -= 1;
            $max += 1;
        }

        $x = fn (int $i) => $L + ($W - $L - $R) * ($diem->count() === 1 ? 0 : $i / ($diem->count() - 1));
        $y = fn (float $v) => $T + ($H - $T - $B) * (1 - ($v - $min) / ($max - $min));

        $duong = $diem->values()->map(fn ($d, $i) => round($x($i), 1) . ',' . round($y($d['value']), 1))->implode(' ');

        $donVi = $diem->first()['unit'] ?? null;

        $iMax = $giaTri->search($giaTri->max());
        $moc = array_unique([0, $diem->count() - 1, (int) $iMax]);
    }
@endphp

<div class="journal-chart">

    <div class="journal-chart__head">
        <span class="journal-chart__title">{{ $metric ?? 'Chỉ số' }}</span>
        @if($veDuoc)
            <span class="text-body-sm">
                {{ $diem->count() }} lần ghi ·
                {{ $diem->first()['date']->format('d/m/Y') }} → {{ $diem->last()['date']->format('d/m/Y') }}
            </span>
        @endif
    </div>

    @if(! $veDuoc)
        <p class="journal-chart__empty mb-0">
            @if($diem->count() === 1)
                Mới có một lần ghi — thêm một lần nữa là có đường biểu diễn.
            @else
                Chưa có số liệu nào cho chỉ số này.
            @endif
        </p>
    @else
        <svg class="journal-chart__svg"
             viewBox="0 0 {{ $W }} {{ $H }}"
             role="img"
             aria-label="Biểu đồ {{ $metric }} qua {{ $diem->count() }} lần ghi, từ {{ $diem->first()['value'] }} đến {{ $diem->last()['value'] }}{{ $donVi ? ' ' . $donVi : '' }}">

            @foreach([0, 0.5, 1] as $t)
                @php $gy = $T + ($H - $T - $B) * $t; @endphp
                <line class="journal-chart__grid" x1="{{ $L }}" y1="{{ $gy }}" x2="{{ $W - $R }}" y2="{{ $gy }}" />
                <text class="journal-chart__label" x="{{ $L - 8 }}" y="{{ $gy + 4 }}" text-anchor="end">
                    {{ rtrim(rtrim(number_format($max - ($max - $min) * $t, 1, ',', '.'), '0'), ',') }}
                </text>
            @endforeach

            <polyline class="journal-chart__line" points="{{ $duong }}" />

            @foreach($diem as $i => $d)
                <circle class="journal-chart__dot"
                        cx="{{ round($x($i), 1) }}"
                        cy="{{ round($y($d['value']), 1) }}"
                        r="4">
                    <title>{{ $d['date']->format('d/m/Y') }}: {{ rtrim(rtrim(number_format($d['value'], 2, ',', '.'), '0'), ',') }}{{ $d['unit'] ? ' ' . $d['unit'] : '' }}</title>
                </circle>

                @if(in_array($i, $moc, true))
                    <text class="journal-chart__value"
                          x="{{ round($x($i), 1) }}"
                          y="{{ round($y($d['value']), 1) - 10 }}"
                          text-anchor="{{ $i === 0 ? 'start' : ($i === $diem->count() - 1 ? 'end' : 'middle') }}">
                        {{ rtrim(rtrim(number_format($d['value'], 1, ',', '.'), '0'), ',') }}
                    </text>
                @endif
            @endforeach

            <text class="journal-chart__label" x="{{ $L }}" y="{{ $H - 8 }}" text-anchor="start">
                {{ $diem->first()['date']->format('d/m') }}
            </text>
            <text class="journal-chart__label" x="{{ $W - $R }}" y="{{ $H - 8 }}" text-anchor="end">
                {{ $diem->last()['date']->format('d/m') }}
            </text>
        </svg>

        @if($donVi)
            <p class="text-body-sm mb-0">Đơn vị: {{ $donVi }}</p>
        @endif
    @endif

</div>
