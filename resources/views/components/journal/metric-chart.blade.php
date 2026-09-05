@props(['series', 'metric' => null])

@php
    /*
     * BIỂU ĐỒ ĐƯỜNG — MỘT chỉ số theo thời gian.
     * ============================================================
     * VÌ SAO LÀ ĐƯỜNG, KHÔNG PHẢI CỘT: câu hỏi ở đây là "cây lớn thế nào
     * theo thời gian" — tức là HÌNH DẠNG của thay đổi, không phải so
     * sánh từng mốc với nhau. Cột trả lời câu hỏi thứ hai.
     *
     * MỘT CHUỖI DUY NHẤT nên KHÔNG CÓ CHÚ GIẢI: tiêu đề ngay trên biểu
     * đồ đã nói nó là chỉ số gì. Một hộp chú giải cho đúng một đường là
     * thêm một thứ để đọc mà không thêm thông tin nào.
     *
     * KHÔNG DÙNG THƯ VIỆN BIỂU ĐỒ. Một đường gấp khúc là mấy phép tính
     * tỉ lệ; kéo về 60KB JavaScript cho việc đó là đổi tốc độ tải trang
     * lấy thứ không cần. SVG dựng sẵn ở máy chủ cũng có nghĩa là biểu đồ
     * hiện ra ngay cả khi JavaScript hỏng.
     *
     * BẢNG SỐ LIỆU ĐI KÈM: dòng thời gian ngay bên dưới liệt kê đủ mọi
     * lần ghi kèm chỉ số. Đó là "bản dạng bảng" cho người dùng trình đọc
     * màn hình và cho ai muốn đọc con số chính xác — biểu đồ không phải
     * nơi duy nhất chứa dữ liệu này.
     */
    $diem = collect($series);

    // Cần ít nhất hai điểm mới vẽ được một đường. Một điểm thì không có
    // "thay đổi theo thời gian" nào để nhìn.
    $veDuoc = $diem->count() >= 2;

    if ($veDuoc) {
        $W = 720;
        $H = 220;
        $L = 44;   // chừa chỗ cho nhãn trục dọc
        $R = 16;
        $T = 18;
        $B = 30;   // chừa chỗ cho nhãn ngày

        $giaTri = $diem->pluck('value');
        $min = (float) $giaTri->min();
        $max = (float) $giaTri->max();

        /*
         * MỌI GIÁ TRỊ BẰNG NHAU thì khoảng dao động bằng 0 và phép chia
         * bên dưới vỡ. Nới ra một chút để đường nằm giữa khung thay vì
         * dính đáy.
         */
        if ($max - $min < 0.0001) {
            $min -= 1;
            $max += 1;
        }

        $x = fn (int $i) => $L + ($W - $L - $R) * ($diem->count() === 1 ? 0 : $i / ($diem->count() - 1));
        $y = fn (float $v) => $T + ($H - $T - $B) * (1 - ($v - $min) / ($max - $min));

        $duong = $diem->values()->map(fn ($d, $i) => round($x($i), 1) . ',' . round($y($d['value']), 1))->implode(' ');

        $donVi = $diem->first()['unit'] ?? null;

        /*
         * NHÃN GIÁ TRỊ CHỌN LỌC: điểm đầu, điểm cuối, và điểm cao nhất.
         *
         * Ghi số lên mọi điểm thì biểu đồ thành một bảng số xếp cong —
         * mất luôn thứ biểu đồ giỏi hơn bảng. Ba mốc này là ba câu hỏi
         * người ta thật sự hỏi: bắt đầu từ đâu, giờ tới đâu, đỉnh là bao
         * nhiêu.
         */
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

            {{--
                LƯỚI CHỈ BA ĐƯỜNG NGANG: đáy, giữa, đỉnh.
                Lưới dày đặc cạnh tranh chú ý với chính dữ liệu — nó là
                nền để ước lượng, không phải nội dung.
            --}}
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
                    {{--
                        <title> = tooltip GỐC của trình duyệt.

                        Rê chuột vào chấm là hiện ngày và giá trị chính
                        xác, không cần một dòng JavaScript nào, và trình
                        đọc màn hình cũng đọc được. Dựng tooltip riêng
                        bằng JavaScript thì đẹp hơn một chút nhưng hỏng
                        ngay khi script chưa tải xong — đúng lúc trang vừa
                        mở ra.
                    --}}
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

            {{-- Ngày chỉ ở hai đầu: nhãn ngày trên mọi điểm sẽ chồng lên nhau. --}}
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
