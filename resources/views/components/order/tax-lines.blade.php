@props([
    // Tổng tiền thuế, dạng chuỗi bcmath. null = "không có số liệu thuế".
    'total' => null,
    // Tách theo từng mức: [['rate' => ?string, 'net' => string, 'tax' => string], ...]
    'rows' => [],
])

{{--
    ============ THUẾ GTGT — DÒNG THÔNG TIN, KHÔNG PHẢI DÒNG CỘNG ============

    MỘT BẢN DUY NHẤT, dùng ở ba nơi: tóm tắt giỏ hàng, màn hình thanh
    toán và trang đơn hàng của khách. Ba bản chép tay sẽ lệch nhau ngay
    lần sửa đầu tiên, và lệch ở đây nghĩa là ba màn hình nói ba con số
    thuế khác nhau cho cùng một đơn.

    LUÔN ĐẶT DƯỚI DÒNG TỔNG. Giá niêm yết của cửa hàng đã bao gồm VAT,
    nên đây là phần NẰM TRONG con số ngay trên, không phải khoản cộng
    thêm. Đặt lên trên dòng tổng là mời khách cộng nhầm — và nỗi sợ "phát
    sinh phút chót" là lý do hàng đầu khiến người ta bỏ giỏ ở bước cuối.

    KHÔNG CÓ SỐ LIỆU THÌ KHÔNG HIỆN GÌ. `total` null nghĩa là cửa hàng
    chưa ghi nhận thuế cho đơn này (tính thuế đang tắt, hoặc đơn đặt
    trước khi có tính năng). In "0₫" là nói rằng đơn được miễn thuế —
    một câu hoàn toàn khác, và sai.
--}}

@php
    $co = $total !== null && bccomp((string) $total, '0', 2) > 0;
    $mucChu = fn (?string $r) => \App\Services\Tax\TaxCalculator::formatRate($r);
    $tien = fn (string $v) => \App\Services\Shop\Money::format($v);
@endphp

@if($co)
    <div class="order-summary__tax">
        <span class="order-summary__tax-label">Trong đó thuế GTGT</span>
        <span class="order-summary__tax-value">{{ $tien((string) $total) }}</span>

        {{--
            TÁCH THEO TỪNG MỨC chỉ khi đơn có nhiều hơn một mức.

            Giỏ có bó hoa (không chịu VAT) và chậu sứ (10%) thì một con
            số gộp không giải thích được gì. Còn khi cả đơn cùng một mức
            thì bảng tách chỉ là tiếng ồn — lúc đó một dòng chữ nhỏ ghi
            thuế suất là đủ.
        --}}
        @if(count($rows) > 1)
            <ul class="order-summary__tax-rates">
                @foreach($rows as $muc)
                    <li>
                        <span>
                            @if($muc['rate'] === null)
                                {{ $mucChu(null) }}
                            @else
                                VAT {{ $mucChu($muc['rate']) }}
                            @endif
                            trên {{ $tien($muc['net']) }}
                        </span>
                        <span>{{ $tien($muc['tax']) }}</span>
                    </li>
                @endforeach
            </ul>
        @elseif(count($rows) === 1 && $rows[0]['rate'] !== null)
            <span class="order-summary__tax-note">thuế suất {{ $mucChu($rows[0]['rate']) }}</span>
        @endif
    </div>
@endif
