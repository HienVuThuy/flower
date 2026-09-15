{{-- Một dòng quà trong đơn: nhãn "Quà miễn phí", giá 0đ. --}}
<li class="checkout-items__row checkout-items__row--gift" data-dong-qua="{{ $qua->id }}">
    <span>
        <span class="order-item__promo">Quà miễn phí</span>
        {{ $qua->product_name }}
        @if($qua->variant_name)
            <span class="text-muted">({{ $qua->variant_name }})</span>
        @endif
        <span class="text-muted">&times; {{ $qua->quantity }}</span>

        {{-- Quà theo chương trình thì nói tên chương trình; quà kèm sản phẩm đã nằm dưới món của nó. --}}
        @if($qua->gift_campaign_id && $qua->promotion_name)
            <span class="text-muted d-block small">{{ $qua->promotion_name }}</span>
        @endif
    </span>
    <span><x-site.money :amount="0" /></span>
</li>
