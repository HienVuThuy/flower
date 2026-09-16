@props([
    'slices' => [],
    'title' => '',
    'note' => null,
    'unit' => 'đơn',
])

@php
    $tatCa = collect($slices)->values();
    $veDuoc = $tatCa->filter(fn ($s) => (float) $s['value'] > 0)->values();
    $tong = (float) $tatCa->sum('value');

    $R = 46;
    $Rin = 28;
    $C = 56;
@endphp

<div class="viz viz-card">

    <div class="viz-card__head">
        <h3 class="viz-card__title">{{ $title }}</h3>
        @if($note)
            <p class="viz-card__note">{{ $note }}</p>
        @endif
    </div>

    @if($tong <= 0)
        <p class="analytics-empty mb-0">Chưa có dữ liệu trong kỳ này.</p>
    @else
        <div class="viz-donut">

            <svg viewBox="0 0 {{ $C * 2 }} {{ $C * 2 }}" role="img"
                 aria-label="{{ $title }}: tổng {{ number_format($tong, 0, ',', '.') }} {{ $unit }}">

                @php $goc = -90; @endphp

                @foreach($veDuoc as $s)
                    @php
                        $phan = (float) $s['value'] / $tong;
                        $quet = $phan * 360;
                        $tu = $goc;
                        $den = $goc + $quet;
                        $goc = $den;

                        $rad = fn ($d) => deg2rad($d);
                        $x1 = round($C + $R * cos($rad($tu)), 2);
                        $y1 = round($C + $R * sin($rad($tu)), 2);
                        $x2 = round($C + $R * cos($rad($den)), 2);
                        $y2 = round($C + $R * sin($rad($den)), 2);
                        $lon = $quet > 180 ? 1 : 0;
                    @endphp

                    @if($phan >= 0.999)
                        <circle class="viz-donut__seg" cx="{{ $C }}" cy="{{ $C }}" r="{{ $R }}"
                                fill="{{ $s['color'] }}">
                            <title>{{ $s['label'] }}: {{ number_format($s['value'], 0, ',', '.') }} {{ $unit }} (100%)</title>
                        </circle>
                    @else
                        <path class="viz-donut__seg"
                              d="M {{ $C }} {{ $C }} L {{ $x1 }} {{ $y1 }} A {{ $R }} {{ $R }} 0 {{ $lon }} 1 {{ $x2 }} {{ $y2 }} Z"
                              fill="{{ $s['color'] }}">
                            <title>{{ $s['label'] }}: {{ number_format($s['value'], 0, ',', '.') }} {{ $unit }} ({{ round($phan * 100) }}%)</title>
                        </path>
                    @endif
                @endforeach

                <circle class="viz-donut__hole" cx="{{ $C }}" cy="{{ $C }}" r="{{ $Rin }}" />
                <text class="viz-donut__total" x="{{ $C }}" y="{{ $C + 1 }}">
                    {{ number_format($tong, 0, ',', '.') }}
                </text>
                <text class="viz-donut__total-label" x="{{ $C }}" y="{{ $C + 12 }}">{{ $unit }}</text>
            </svg>

            <dl class="viz-legend">
                @foreach($veDuoc as $s)
                    <div class="viz-legend__row">
                        <span class="viz-legend__swatch" style="background: {{ $s['color'] }}"></span>
                        <dt class="viz-legend__name">{{ $s['label'] }}</dt>
                        <dd class="viz-legend__value m-0">
                            {{ number_format($s['value'], 0, ',', '.') }}
                            &middot; {{ round((float) $s['value'] / $tong * 100) }}%
                        </dd>
                    </div>
                @endforeach
            </dl>

        </div>

        <details class="viz-details">
            <summary>Xem bảng số</summary>

            <table class="viz-table">
                <thead>
                    <tr><th>Mục</th><th>{{ ucfirst($unit) }}</th><th>Tỉ lệ</th></tr>
                </thead>
                <tbody>
                    @foreach($tatCa as $s)
                        <tr>
                            <td>{{ $s['label'] }}</td>
                            <td>{{ number_format($s['value'], 0, ',', '.') }}</td>
                            <td>{{ $tong > 0 ? round((float) $s['value'] / $tong * 100) . '%' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    @endif

</div>
