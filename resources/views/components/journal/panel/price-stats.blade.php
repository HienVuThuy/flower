@props(['journal'])

@php
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

    @endif
</div>
