@props([
    // [['label' => '01/09', 'value' => 120000], ...]
    'points' => [],
    'title' => '',
    'note' => null,
    // 'tien' để định dạng theo tiền tệ, 'so' cho số đếm.
    'format' => 'so',
    // 1 hoặc 2 — chọn màu chuỗi. Thứ tự này là cơ chế an toàn mù màu.
    'slot' => 1,
])

@php
    $points = collect($points)->values();
    $max = (float) $points->max('value');
    $co = $points->isNotEmpty() && $max > 0;

    // Khung vẽ. Toạ độ trong viewBox, không phải pixel màn hình — SVG tự
    // co giãn theo bề ngang thật.
    $W = 640;
    $H = 180;
    $L = 8;        // lề trái
    $R = 8;        // lề phải
    $T = 10;       // lề trên
    $B = 22;       // chừa chỗ cho nhãn trục ngang

    $vungRong = $W - $L - $R;
    $vungCao = $H - $T - $B;

    $n = max(1, $points->count() - 1);

    $toaDoX = fn (int $i) => $L + ($n === 0 ? $vungRong / 2 : $i * $vungRong / $n);
    $toaDoY = fn (float $v) => $T + $vungCao - ($max > 0 ? $v / $max * $vungCao : 0);

    $tien = fn ($v) => \App\Services\Shop\Money::format((string) round($v));
    $soChu = fn ($v) => $format === 'tien' ? $tien($v) : number_format($v, 0, ',', '.');

    /*
     * NHÃN TRỤC NGANG THƯA RA.
     *
     * 90 ngày là 90 nhãn chồng lên nhau thành một vệt đen. Chỉ in
     * khoảng 6 mốc, luôn có mốc đầu và mốc cuối — hai mốc đó trả lời
     * "biểu đồ này từ ngày nào tới ngày nào".
     */
    $buoc = max(1, (int) ceil($points->count() / 6));
@endphp

<div class="viz viz-card">

    <div class="viz-card__head">
        <h3 class="viz-card__title">{{ $title }}</h3>
        @if($note)
            <p class="viz-card__note">{{ $note }}</p>
        @endif
    </div>

    @if(! $co)
        {{-- Chưa có số thì NÓI THẲNG. Vẽ một đường phẳng ở đáy đọc ra
             như "doanh thu bằng 0 suốt kỳ" — một câu khác hẳn. --}}
        <p class="analytics-empty mb-0">Chưa có dữ liệu trong kỳ này.</p>
    @else
        @php
            $duong = $points->map(fn ($p, $i) => $toaDoX($i) . ',' . round($toaDoY((float) $p['value']), 2))->implode(' ');
            $mau = 'var(--viz-' . ($slot === 2 ? '2' : '1') . ')';
        @endphp

        <svg viewBox="0 0 {{ $W }} {{ $H }}" role="img"
             aria-label="{{ $title }}: {{ $points->count() }} mốc, cao nhất {{ $soChu($max) }}">

            {{-- Lưới ngang: 4 vạch, đường tóc, nét liền. --}}
            @foreach([0, 0.25, 0.5, 0.75, 1] as $ti)
                <line class="viz__grid"
                      x1="{{ $L }}" x2="{{ $W - $R }}"
                      y1="{{ round($T + $vungCao * $ti, 2) }}"
                      y2="{{ round($T + $vungCao * $ti, 2) }}" />
            @endforeach

            {{-- Mảng nền dưới đường: một chuỗi duy nhất thì mảng giúp mắt
                 bám theo hình dáng. Rất nhạt để không thành khối màu. --}}
            <polygon class="viz__area" fill="{{ $mau }}"
                     points="{{ $toaDoX(0) }},{{ $T + $vungCao }} {{ $duong }} {{ $toaDoX($points->count() - 1) }},{{ $T + $vungCao }}" />

            <polyline class="viz__line" stroke="{{ $mau }}" points="{{ $duong }}" />

            @foreach($points as $i => $p)
                @php
                    $x = $toaDoX($i);
                    $y = round($toaDoY((float) $p['value']), 2);
                    $rongCot = $vungRong / max(1, $points->count());
                @endphp

                {{-- Ô chạm rộng cả cột, trong suốt: dấu tròn 3px thì gần
                     như không rê trúng. tabindex để bàn phím cũng tới được. --}}
                <rect class="viz__hit"
                      x="{{ round($x - $rongCot / 2, 2) }}" y="{{ $T }}"
                      width="{{ round($rongCot, 2) }}" height="{{ $vungCao }}"
                      tabindex="0" role="img"
                      aria-label="{{ $p['label'] }}: {{ $soChu((float) $p['value']) }}">
                    <title>{{ $p['label'] }}: {{ $soChu((float) $p['value']) }}</title>
                </rect>

                <circle class="viz__dot" cx="{{ round($x, 2) }}" cy="{{ $y }}" r="4"
                        fill="{{ $mau }}" stroke="var(--viz-surface)" stroke-width="2" />
            @endforeach

            @foreach($points as $i => $p)
                @if($i % $buoc === 0 || $i === $points->count() - 1)
                    <text class="viz__axis-label" x="{{ round($toaDoX($i), 2) }}" y="{{ $H - 6 }}"
                          text-anchor="{{ $i === 0 ? 'start' : ($i === $points->count() - 1 ? 'end' : 'middle') }}">
                        {{ $p['label'] }}
                    </text>
                @endif
            @endforeach
        </svg>

        {{--
            NHÃN TRỰC TIẾP CÓ CHỌN LỌC: chỉ mốc cao nhất và mốc cuối.
            In số trên mọi điểm là một mớ hỗn độn không ai đọc.
        --}}
        @php
            $dinh = $points->sortByDesc('value')->first();
            $cuoi = $points->last();
        @endphp

        <p class="viz-card__note">
            Cao nhất {{ $dinh['label'] }}: <strong>{{ $soChu((float) $dinh['value']) }}</strong>
            &middot; gần nhất {{ $cuoi['label'] }}: <strong>{{ $soChu((float) $cuoi['value']) }}</strong>
        </p>
    @endif

</div>
