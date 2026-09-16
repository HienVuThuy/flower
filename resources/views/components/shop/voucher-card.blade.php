@props([
    'coupon',
    'saved' => false,
    'usedCount' => 0,
    'exhaustedForUser' => false,
    'hidden' => false,
    'showTermsLink' => true,
])

@php
    use App\Enums\CouponType;

    $amount = $coupon->type === CouponType::Percent
        ? number_format((float) $coupon->value, 0, ',', '.').'%'
        : \App\Services\Shop\Money::format($coupon->value);

    $remaining = $coupon->remainingUses();

    $lowStock = $remaining !== null && $remaining > 0 && $remaining <= 10;

    $usedPercent = $coupon->usage_limit
        ? min(100, (int) round($coupon->used_count / max(1, $coupon->usage_limit) * 100))
        : null;

    $dead = $exhaustedForUser || ! $coupon->isRunning() || $coupon->isExhausted();

    $sapMat = null;
    if ($saved && ! $dead && $coupon->ends_at && $coupon->ends_at->isFuture()) {
        $conGio = (int) floor(now()->diffInMinutes($coupon->ends_at) / 60);
        if ($conGio < 72) {
            $sapMat = $conGio < 1 ? 'dưới 1 giờ' : ($conGio < 24 ? $conGio . ' giờ' : intdiv($conGio, 24) . ' ngày');
        }
    }
@endphp

<div class="voucher-card {{ $dead ? 'is-spent' : '' }}">

    <div class="voucher-card__amount">
        <span class="voucher-card__value">{{ $amount }}</span>
        <span class="voucher-card__unit">GIẢM</span>
    </div>

    <div class="voucher-card__body">

        <p class="voucher-card__name">{{ $coupon->name }}</p>

        <p class="voucher-card__terms">{{ $coupon->conditionText() }}</p>

        @if($coupon->perUserText())
            <p class="voucher-card__terms">{{ $coupon->perUserText() }}</p>
        @endif
        @if($sapMat)
            <p class="voucher-card__expiring">Hết hạn sau {{ $sapMat }} — dùng trước khi mất mã</p>
        @endif

        <p class="voucher-card__meta">
            Mã <strong>{{ $coupon->code }}</strong>

            @if($coupon->ends_at)
                &middot; HSD <x-site.time :at="$coupon->ends_at" format="d/m/Y" />
            @endif

            @if($lowStock)
                &middot; <span class="voucher-card__low">sắp hết ({{ $remaining }} lượt)</span>
            @endif
        </p>

        @if($usedPercent !== null)
            <div class="voucher-card__progress"
                 role="img"
                 aria-label="Đã dùng {{ $usedPercent }}% số lượt">
                <span class="voucher-card__progress-fill" style="width: {{ $usedPercent }}%"></span>
            </div>

            @if($lowStock)
                <p class="voucher-card__low">Đang hết nhanh &middot; còn {{ $remaining }} lượt</p>
            @endif
        @endif

        <div class="voucher-card__actions">
            @if($exhaustedForUser)

                <span class="voucher-card__state">Bạn đã dùng hết lượt</span>

            @elseif($coupon->isExhausted())

                <button type="button" class="btn btn-sm voucher-card__spent" disabled>
                    Đã hết mã
                </button>

            @elseif(! $coupon->isRunning())

                <span class="voucher-card__state">Hết hạn sử dụng</span>

            @elseif($saved)

                <span class="voucher-card__state">
                    Đã lưu
                    @if($usedCount > 0)
                        &middot; đã dùng {{ $usedCount }} lần
                    @endif
                </span>

            @elseif(auth()->check())

                <form method="POST" action="{{ route('shop.vouchers.claim', $coupon) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary-brand btn-sm">Lưu mã</button>
                </form>

            @else

                <a href="{{ route('login', ['redirect' => route('shop.vouchers.index', [], false)]) }}"
                   class="btn btn-secondary-brand btn-sm">
                    Đăng nhập để lưu
                </a>

            @endif

            @if($saved && ! $hidden)
                <form method="POST" action="{{ route('shop.vouchers.discard', $coupon) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-ghost btn-sm voucher-card__discard">
                        {{ $usedCount > 0 ? 'Ẩn khỏi ví' : 'Bỏ khỏi ví' }}
                    </button>
                </form>
            @endif

            @if($hidden)
                <form method="POST" action="{{ route('shop.vouchers.unhide', $coupon) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-ghost btn-sm">Đưa lại về ví</button>
                </form>
            @endif

            @if($showTermsLink)
                <a href="{{ route('shop.vouchers.show', $coupon) }}" class="voucher-card__terms-link">
                    Điều kiện
                </a>
            @endif
        </div>

    </div>

</div>
