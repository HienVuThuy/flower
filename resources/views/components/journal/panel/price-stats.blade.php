@props(['journal'])

@php
    /*
     * THỐNG KÊ GIÁ — khối làm nên sổ Theo dõi giá.
     * ============================================================
     * Câu hỏi thật của người mở sổ này ra là *"bây giờ có phải lúc mua
     * không"*. Trả lời được câu đó cần ba con số đặt cạnh nhau: giá đang
     * thấy, giá thấp nhất từng thấy, và khoảng dao động.
     *
     * `priceStats()` trả về null khi mới có DƯỚI HAI lần khảo — lúc đó
     * "thấp nhất", "cao nhất" và "trung bình" đều là chính con số đó, ba
     * ô hiện cùng một số và trông như một bảng thống kê mà không thống kê
     * gì cả.
     */
    $tk = $journal->priceStats();
@endphp

<div class="journal-panel">
    <div class="journal-panel__head">
        <h2 class="text-h3 mb-0">Giá đang ở đâu</h2>

        @if($tk)
            <span class="journal-panel__meta">{{ $tk['count'] }} lần khảo</span>
        @endif
    </div>

    @if(! $tk)
        <p class="text-body-sm mb-0">
            Cần ít nhất <strong>hai lần khảo giá</strong> mới so sánh được.
            Ghi thêm một lần nữa là có ngay khoảng dao động và mức thấp nhất.
        </p>
    @else
        <div class="price-stats">
            <div class="price-stat">
                <span class="price-stat__label">Lần gần nhất</span>
                <span class="price-stat__value"><x-site.money :amount="$tk['latest']" /></span>
            </div>

            <div class="price-stat price-stat--good">
                <span class="price-stat__label">Thấp nhất từng thấy</span>
                <span class="price-stat__value"><x-site.money :amount="$tk['low']" /></span>
                @if($tk['place_low'])
                    <span class="price-stat__note">tại {{ $tk['place_low'] }}</span>
                @endif
            </div>

            <div class="price-stat">
                <span class="price-stat__label">Cao nhất</span>
                <span class="price-stat__value"><x-site.money :amount="$tk['high']" /></span>
            </div>

            <div class="price-stat">
                <span class="price-stat__label">Trung bình</span>
                <span class="price-stat__value"><x-site.money :amount="$tk['avg']" /></span>
            </div>
        </div>

        {{--
            SO LẦN GẦN NHẤT VỚI MỨC THẤP NHẤT — đó mới là câu trả lời.

            Bốn con số đứng cạnh nhau vẫn bắt người đọc tự trừ trong đầu.
            Nói thẳng ra "đang cao hơn mức thấp nhất 120.000₫" thì họ
            quyết được ngay.
        --}}
        <p class="mt-3 mb-0">
            @php $chenh = $tk['latest'] - $tk['low']; @endphp

            @if($chenh <= 0)
                <strong>Lần gần nhất đang là mức thấp nhất bạn từng ghi.</strong>
            @else
                Lần gần nhất đang cao hơn mức thấp nhất
                <strong><x-site.money :amount="$chenh" /></strong>.
                Khoảng dao động bạn từng thấy: <x-site.money :amount="$tk['spread']" />.
            @endif
        </p>

        {{--
            KHÔNG KHUYÊN "NÊN MUA" HAY "NÊN ĐỢI".

            Sổ này là ghi chép của khách, không phải lời tư vấn của cửa
            hàng — và cửa hàng thì có lợi ích trong việc họ mua sớm. Đưa
            ra số liệu của chính họ rồi để họ tự quyết là ranh giới đúng.
        --}}
    @endif
</div>
