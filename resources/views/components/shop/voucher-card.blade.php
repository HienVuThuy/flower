@props([
    'coupon',
    'saved' => false,
    'usedCount' => 0,
    'exhaustedForUser' => false,
    // Trang chi tiết đã LÀ điều kiện — không tự trỏ về chính nó.
    'showTermsLink' => true,
])

@php
    use App\Enums\CouponType;

    $amount = $coupon->type === CouponType::Percent
        ? number_format((float) $coupon->value, 0, ',', '.').'%'
        : \App\Services\Shop\Money::format($coupon->value);

    $remaining = $coupon->remainingUses();

    /*
     * "Sắp hết" chỉ hiện khi thật sự sắp hết VÀ có giới hạn.
     * Mã không giới hạn lượt thì remainingUses() trả null — in "còn 0"
     * ở đó là nói ngược hẳn sự thật.
     */
    $lowStock = $remaining !== null && $remaining > 0 && $remaining <= 10;

    /*
     * TIẾN ĐỘ SỬ DỤNG — phần trăm lượt ĐÃ dùng.
     *
     * Chỉ tính được khi mã có giới hạn lượt. Mã không giới hạn thì không
     * có thanh nào: vẽ một thanh đầy hay rỗng đều là bịa một con số.
     */
    $usedPercent = $coupon->usage_limit
        ? min(100, (int) round($coupon->used_count / max(1, $coupon->usage_limit) * 100))
        : null;

    /*
     * Mã hết hiệu lực — hết hạn, hết lượt, hoặc khách đã dùng hết suất.
     * Gộp ba trường hợp vì giao diện xử lý chúng giống hệt nhau: chuyển
     * xám và khoá nút.
     */
    $dead = $exhaustedForUser || ! $coupon->isRunning() || $coupon->isExhausted();
@endphp

{{--
    Thẻ voucher.

    HÌNH DẠNG VÉ XÉ (hai nửa, có răng cưa ở giữa) là quy ước thị giác mà
    ai mua hàng trên mạng cũng đã quen — nhìn là biết đây là phiếu giảm
    giá chứ không phải một quảng cáo. Nửa trái là con số giảm, nửa phải
    là điều kiện và nút.
--}}
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

        <p class="voucher-card__meta">
            Mã <strong>{{ $coupon->code }}</strong>

            @if($coupon->ends_at)
                {{-- Hạn dùng là thứ khách cần biết nhất sau con số giảm.
                     Ghi ngày cụ thể chứ không ghi "còn 3 ngày": khách mở
                     lại trang sau một tuần thì câu đếm ngược đã sai. --}}
                &middot; HSD {{ $coupon->ends_at->format('d/m/Y') }}
            @endif

            @if($lowStock)
                &middot; <span class="voucher-card__low">sắp hết ({{ $remaining }} lượt)</span>
            @endif
        </p>

        @if($usedPercent !== null)
            {{--
                THANH TIẾN ĐỘ — chỉ hiện khi mã CÓ giới hạn lượt.

                role="img" kèm aria-label: thanh này là thông tin, không
                phải trang trí, nên người dùng trình đọc màn hình cũng
                phải nhận được con số.
            --}}
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

                {{-- Đã dùng hết suất: giữ thẻ lại nhưng nói rõ, thay vì
                     ẩn đi. Ẩn thì khách tưởng mình chưa từng có mã đó và
                     đi tìm lại. --}}
                <span class="voucher-card__state">Bạn đã dùng hết lượt</span>

            @elseif($coupon->isExhausted())

                {{-- Hết lượt trên toàn hệ thống. Nút xám và KHOÁ hẳn —
                     một nút bấm được nhưng luôn báo lỗi thì tệ hơn một
                     nút rõ ràng là không bấm được. --}}
                <button type="button" class="btn btn-sm voucher-card__spent" disabled>
                    Đã hết mã
                </button>

            @elseif(! $coupon->isRunning())

                <span class="voucher-card__state">Hết hạn sử dụng</span>

            @elseif($saved)

                {{--
                    KHOẢNG TRẮNG TRƯỚC @if LÀ BẮT BUỘC.

                    Blade chỉ nhận directive khi ký tự ngay trước @ không
                    phải chữ cái. Viết liền "Đã lưu@if(...)" thì @if bị bỏ
                    qua nhưng @endif vẫn được biên dịch — sinh ra một
                    @endif thừa và cả tệp thành lỗi cú pháp PHP. Lỗi này
                    chỉ lộ ra lúc chạy, không phải lúc lưu tệp.
                --}}
                <span class="voucher-card__state">
                    Đã lưu
                    @if($usedCount > 0)
                        &middot; đã dùng {{ $usedCount }} lần
                    @endif
                </span>

                @if($usedCount === 0)
                    <form method="POST" action="{{ route('shop.vouchers.discard', $coupon) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-ghost btn-sm">Bỏ khỏi ví</button>
                    </form>
                @endif

            @elseif(auth()->check())

                <form method="POST" action="{{ route('shop.vouchers.claim', $coupon) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary-brand btn-sm">Lưu mã</button>
                </form>

            @else

                {{--
                    Khách vãng lai: dẫn sang đăng nhập rồi QUAY LẠI ĐÚNG
                    trang này. Không có tham số quay lại thì họ đăng nhập
                    xong bị ném về trang chủ và phải tự đi tìm lại voucher
                    — đủ phiền để bỏ luôn.
                --}}
                {{-- Truyền ĐƯỜNG DẪN TƯƠNG ĐỐI (tham số thứ ba của route()
                     là `absolute`). URL tuyệt đối cũng được chấp nhận,
                     nhưng đường dẫn tương đối thì không có host để mà so,
                     nên không bao giờ vướng chuyện localhost ≠ 127.0.0.1. --}}
                <a href="{{ route('login', ['redirect' => route('shop.vouchers.index', [], false)]) }}"
                   class="btn btn-secondary-brand btn-sm">
                    Đăng nhập để lưu
                </a>

            @endif

            @if($showTermsLink)
                {{--
                    "Điều kiện" — cửa vào trang điều khoản đầy đủ.

                    Thẻ này chỉ đủ chỗ cho một dòng điều kiện, nhưng mã
                    thật luôn có cả danh sách: hạn dùng, giảm tối đa, giới
                    hạn mỗi tài khoản, hình thức thanh toán. Nhét hết vào
                    thẻ thì không đọc được thẻ nào; giấu hết thì khách bị
                    từ chối ở bước thanh toán mà không hiểu vì sao.
                --}}
                <a href="{{ route('shop.vouchers.show', $coupon) }}" class="voucher-card__terms-link">
                    Điều kiện
                </a>
            @endif
        </div>

    </div>

</div>
