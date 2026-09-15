<?php

namespace App\Http\Controllers\Shop;

use App\Enums\MomoFlow;
use App\Enums\PaymentMethod;
use App\Enums\UserEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\CheckoutDetailsRequest;
use App\Models\Address;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\UserEvent;
use App\Services\Coupon\CouponException;
use App\Services\Coupon\CouponWallet;
use App\Services\Coupon\CouponService;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutBasket;
use App\Services\Checkout\CheckoutGuard;
use App\Services\Checkout\CheckoutSource;
use App\Services\Order\OrderException;
use App\Services\Order\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Thanh toán 2 bước.
 * ============================================================
 *   1. Thông tin & thanh toán  ->  2. Xác nhận
 *
 * TRƯỚC ĐÂY LÀ BA BƯỚC, và bước 1-2 vừa được gộp làm một.
 *
 * Lý do gộp: phí giao phụ thuộc TỈNH, mà tỉnh nhập ở bước 1 — nên tổng
 * tiền chỉ đúng từ bước 2 trở đi. Khách điền xong bước 1 vẫn chưa biết
 * mình phải trả bao nhiêu, và đó chính là lúc nhiều người bỏ giỏ hàng.
 * Nay mọi thứ quyết định số tiền nằm trên CÙNG một màn hình, tóm tắt đơn
 * bên cạnh luôn đúng.
 *
 * Dữ liệu bước 1 giữ trong SESSION, chỉ ghi vào cơ sở dữ liệu ở bước 2.
 * Nhờ vậy khách bỏ ngang giữa chừng không để lại đơn rác, và quay lại
 * sửa không mất những gì đã điền.
 *
 * Bước xác nhận tự kiểm tra bước trước đã xong chưa, nên gõ thẳng URL
 * cũng không nhảy cóc được.
 */
class CheckoutController extends Controller
{
    /** Khoá session giữ dữ liệu đang điền dở. */
    /*
     * KHOÁ NÀY PHẢI LÀ MỘT NHÁNH CON, KHÔNG ĐƯỢC LÀ 'checkout'.
     *
     * Laravel hiểu dấu chấm trong khoá phiên là mảng lồng nhau. Trước
     * đây khoá là 'checkout' — tức nhánh CHA của checkout.placed,
     * checkout.direct, checkout.coupon và checkout.idempotency. Lệnh
     * session()->forget('checkout') sau khi đặt hàng vì thế xoá sạch cả
     * nhánh, kéo theo những dữ liệu không liên quan gì tới biểu mẫu.
     *
     * Hậu quả đo được: khoá chống đặt trùng vừa ghi xong đã bị xoá ngay
     * trong cùng một request, nên lần bấm thứ hai không tra ra đơn đã
     * đặt và khách bị đẩy về giỏ hàng kèm lỗi.
     *
     * Để ở 'checkout.form' thì forget() chỉ dọn đúng dữ liệu biểu mẫu.
     */
    /**
     * Khoá session giữ dữ liệu biểu mẫu đang nhập dở.
     *
     * Giá trị thật nằm ở CheckoutSource::FORM_KEY — lớp đó cũng phải đọc
     * cùng khoá này để lấy tỉnh tính phí giao. Trỏ sang thay vì gõ lại
     * chuỗi: hai bản chép tay sẽ lệch nhau vào đúng ngày ai đó đổi tên
     * khoá, và lệch kiểu đó không sinh lỗi, chỉ làm phí giao âm thầm sai.
     */
    private const SESSION_KEY = CheckoutSource::FORM_KEY;

    /** Danh sách mã đơn khách này đã đặt, để cho phép xem lại đơn. */
    public const PLACED_KEY = 'checkout.placed';

    public function __construct(
        private readonly CartService $cart,
        private readonly CheckoutSource $source,
        private readonly CouponService $coupons,
        private readonly OrderService $orders,
        private readonly CheckoutGuard $guard,
        private readonly CouponWallet $wallet,
    ) {
    }

    /* ============ BƯỚC 1: THÔNG TIN & THANH TOÁN ============ */

    public function details(): View|RedirectResponse
    {
        if ($redirect = $this->requireItems()) {
            return $redirect;
        }

        /*
         * TỰ ÁP MÃ GIẢM GIÁ TỐT NHẤT.
         *
         * Chạy ở ĐÂY, mỗi lần mở trang: giỏ hàng có thể vừa đổi (thêm
         * hàng, bỏ tích một món), và mã tốt nhất đổi theo. Chỉ áp một lần
         * lúc vào giỏ thì mã sẽ lạc hậu ngay khi khách sửa số lượng.
         *
         * Hàm này KHÔNG bao giờ đè lên mã khách tự chọn — xem
         * CheckoutSource::autoApplyBestCoupon().
         */
        $this->source->autoApplyBestCoupon();

        $basket = $this->source->basket();

        $saved = session(self::SESSION_KEY, []);
        $user = Auth::user();
        $addresses = $user?->addresses ?? collect();

        /*
         * Thứ tự ưu tiên khi điền sẵn:
         *   1. Thứ khách vừa nhập lần trước (quay lại sửa)
         *   2. Địa chỉ mặc định trong sổ
         *   3. Tên và email của tài khoản
         *
         * Nhờ vậy quay lại không mất thứ vừa gõ, còn người đã có sổ địa
         * chỉ thì không phải nhập lại gì.
         */
        $default = $addresses->firstWhere('is_default', true) ?? $addresses->first();

        $values = $saved
            + ($default?->toCheckoutData() ?? [])
            + [
                'recipient_name' => $user->name ?? '',
                'recipient_email' => $user->email ?? '',
            ];

        return view('shop.checkout.details', [
            'step' => 1,
            'basket' => $basket,
            'values' => $values,
            'addresses' => $addresses,
            'selectedAddressId' => $saved['address_id'] ?? $default?->id,
            /*
             * available() chứ KHÔNG phải cases().
             *
             * cases() là mọi hình thức hệ thống BIẾT; available() là
             * những hình thức hệ thống LÀM ĐƯỢC lúc này. Một cổng đã
             * thêm case nhưng chưa điền khoá trong .env sẽ hiện ra ở
             * đây nếu dùng cases() — và khách bấm vào sẽ hỏng giữa
             * đường, sau khi đã điền hết địa chỉ.
             */
            'paymentMethods' => PaymentMethod::available(),

            // Trả góp được hay không, vì sao — cùng luật với lúc tạo đơn (InstallmentPolicy).
            'traGop' => app(\App\Services\Installment\InstallmentPolicy::class)->xet(Auth::user(), $basket->grandTotal()),

            // Để giao diện nói rõ mã này do hệ thống tự chọn hay khách chọn.
            'couponIsAuto' => $this->source->couponIsAuto(),

            // Khách đã tự bỏ mã cho đơn này chưa — để giao diện mời họ
            // bật lại thay vì im lặng không chọn gì nữa.
            'autoDeclined' => $this->source->autoCouponDeclined(),

            'couponChoices' => $this->couponChoices($basket),
        ]);
    }

    /**
     * Mã TRONG VÍ, kèm lý do nếu chưa dùng được cho đơn này.
     *
     * ĐÚNG BẰNG bộ mã mà BestCouponFinder xét. Hai chỗ lệch nhau là
     * khách thấy một mã lạ trong tổng tiền mà không tìm được ở đâu —
     * đã xảy ra thật, xem QĐ-47.
     *
     * HIỆN CẢ MÃ CHƯA DÙNG ĐƯỢC, không lọc bỏ: "cần đơn từ 500.000đ" vừa
     * trung thực vừa có ích — khách biết mua thêm chút nữa là được giảm.
     * Lọc sạch cho gọn thì khách lưu mã xong tới đây không thấy nó đâu và
     * kết luận là hệ thống nuốt mất mã của mình.
     *
     * LÝ DO LẤY TỪ CouponService::reasonUnusable(), không tự so sánh lại
     * ở đây — cùng một luật với lúc bấm Áp dụng, nên danh sách không bao
     * giờ nói khác nút bấm.
     *
     * @return Collection<int, array{coupon: \App\Models\Coupon, reason: string|null}>
     */
    private function couponChoices(CheckoutBasket $basket): Collection
    {
        $user = Auth::user();

        if ($user === null) {
            return collect();
        }

        $total = $basket->itemsTotal();

        return $this->wallet->forUser($user)
            ->map(fn (array $row) => [
                'coupon' => $row['coupon'],
                'reason' => $row['exhaustedForUser']
                    ? 'Bạn đã dùng hết số lần cho phép của mã này.'
                    : $this->coupons->reasonUnusable($row['coupon'], $total),
            ])
            // Mã dùng được lên trước, giữ nguyên thứ tự trong từng nhóm.
            ->sortBy(fn (array $row) => $row['reason'] === null ? 0 : 1)
            ->values();
    }

    public function storeDetails(CheckoutDetailsRequest $request): RedirectResponse
    {
        if ($redirect = $this->requireItems()) {
            return $redirect;
        }

        /*
         * Mỗi lần khách bắt đầu điền lại thông tin người nhận là một lượt
         * thanh toán MỚI, nên cấp khoá chống trùng mới. Không cấp lại thì
         * đơn thứ hai trong cùng phiên sẽ mang khoá của đơn thứ nhất và
         * bị đá về đơn cũ thay vì đặt được đơn mới.
         */
        $this->guard->reset();

        $data = $request->validated();

        /*
         * Khách chọn một địa chỉ trong sổ: lấy dữ liệu từ CƠ SỞ DỮ LIỆU,
         * không tin các ô ẩn gửi lên, và phải xác nhận địa chỉ đó đúng
         * là của người đang đăng nhập.
         */
        if ($id = $request->integer('address_id')) {
            $address = Address::where('user_id', Auth::id())->find($id);

            if (! $address) {
                return back()->with('error', 'Địa chỉ không hợp lệ.');
            }

            /*
             * GIỮ LẠI NHỮNG Ô KHÔNG THUỘC VỀ ĐỊA CHỈ.
             *
             * `toCheckoutData()` chỉ trả về thông tin người nhận. Gán đè
             * cả `$data` thì mọi thứ khách vừa điền ở các bước khác —
             * hình thức thanh toán, ngày giao, ghi chú, và cả khối hoá
             * đơn GTGT — biến mất không dấu vết. Khách tích "cần hoá đơn",
             * chọn một địa chỉ trong sổ, rồi đặt hàng xong mới phát hiện
             * không có hoá đơn nào.
             *
             * `+` giữ giá trị của vế TRÁI khi trùng khoá, nên địa chỉ
             * trong sổ vẫn thắng ở phần thông tin người nhận — đúng như
             * trước.
             */
            $data = $address->toCheckoutData()
                + ['address_id' => $address->id]
                + $data;
        } elseif (Auth::check() && $request->boolean('save_address')) {
            $this->saveAddress($data);
        }

        session([
            self::SESSION_KEY => array_merge(session(self::SESSION_KEY, []), $data),
        ]);

        /*
         * MÃ ĐÃ GÕ NHƯNG CHƯA BẤM "Áp dụng".
         *
         * Ô nhập mã nằm trong chính biểu mẫu này, nên gõ mã rồi nhấn
         * Enter là trình duyệt bấm hộ nút mặc định — tức "Xem lại đơn
         * hàng". Bỏ qua ô đó thì khách sang trang xác nhận và thấy mình
         * không được giảm gì, không hiểu vì sao.
         *
         * Áp luôn cho họ. Mã sai thì quay lại NÓI RÕ là sai, chứ không
         * lặng lẽ đi tiếp — im lặng ở đây đắt hơn một lần quay lại.
         */
        if ($code = trim((string) $request->input('coupon_code'))) {
            try {
                $coupon = $this->coupons->resolve($code, $this->source->basket()->itemsTotal());
                $this->source->setCoupon($coupon->code, auto: false);
            } catch (CouponException $e) {
                return back()
                    ->withErrors(['coupon_code' => $e->getMessage()])
                    ->withInput($this->draft($request));
            }
        }

        return redirect()->route('shop.checkout.confirm');
    }

    /**
     * Lưu địa chỉ vừa nhập vào sổ của khách.
     *
     * Bỏ qua nếu sổ đã có địa chỉ y hệt — đặt hàng nhiều lần cùng một
     * nơi không được sinh ra một sổ đầy bản sao.
     *
     * @param  array<string, mixed>  $data
     */
    private function saveAddress(array $data): void
    {
        $user = Auth::user();

        $attributes = [
            'recipient_name' => $data['recipient_name'],
            'recipient_phone' => $data['recipient_phone'],
            'recipient_email' => $data['recipient_email'] ?? null,
            'address_line' => $data['shipping_address'],
            'ward' => $data['shipping_ward'] ?? null,
            'district' => $data['shipping_district'] ?? null,
            'province' => $data['shipping_province'],
        ];

        $trung = $user->addresses()
            ->where('recipient_phone', $attributes['recipient_phone'])
            ->where('address_line', $attributes['address_line'])
            ->exists();

        if ($trung) {
            return;
        }

        $address = $user->addresses()->create($attributes + ['label' => 'home']);

        // Địa chỉ đầu tiên trong sổ luôn là mặc định.
        if ($user->addresses()->count() === 1) {
            $address->makeDefault();
        }
    }

    /**
     * Đường dẫn cũ của bước 2, giữ lại để không chết link.
     *
     * Bước 2 đã gộp vào bước 1 (xem chú thích đầu lớp). Nhưng khách có
     * thể còn tab đang mở ở /thanh-toan/van-chuyen, hoặc đã lưu dấu trang
     * — trả 404 ở đó là làm mất một người đang giữa đường mua hàng.
     *
     * 301 chứ không phải 302: địa chỉ này biến mất VĨNH VIỄN, và nói
     * đúng như vậy thì trình duyệt lẫn công cụ tìm kiếm cập nhật theo.
     */
    public function shipping(): RedirectResponse
    {
        return redirect()->route('shop.checkout.details', status: 301);
    }

    /**
     * Áp mã giảm giá.
     *
     * Nằm TRONG bước 2, không phải một bước riêng — thêm bước chỉ để
     * nhập mã là kéo dài quy trình mà không đem lại gì.
     *
     * Chỉ nhận CHUỖI MÃ từ trình duyệt. Số tiền giảm do CouponService
     * tính, không bao giờ tin con số gửi lên.
     */
    public function applyCoupon(Request $request): RedirectResponse
    {
        /*
         * CHỈ CẦN CÓ HÀNG, không cần đã điền xong thông tin.
         *
         * Ô mã giảm giá nằm NGAY TRÊN cùng một trang với ô người nhận và
         * ô thanh toán. Khách áp mã trước khi điền nốt là chuyện bình
         * thường — dùng requireStep(1) ở đây sẽ đá họ về chính trang họ
         * đang đứng, mất luôn cả mã vừa nhập.
         */
        if ($redirect = $this->requireItems()) {
            return $redirect;
        }

        /*
         * Nút trong danh sách "Chọn mã giảm giá" gửi wallet_code, ô nhập
         * tay gửi coupon_code.
         *
         * HAI TÊN KHÁC NHAU, không dùng chung một tên. Cả hai cùng nằm
         * trong một biểu mẫu, nên nếu cùng tên thì PHP lấy ô đứng sau và
         * kết quả phụ thuộc vào thứ tự thẻ trong trang — bấm một mã trong
         * danh sách có thể hoá thành áp cái mã đang gõ dở ở ô trên.
         *
         * Giá trị của nút chỉ được gửi khi chính nút đó được bấm, nên có
         * wallet_code nghĩa là khách đã chọn từ danh sách.
         */
        if ($request->filled('wallet_code')) {
            $request->merge(['coupon_code' => $request->input('wallet_code')]);
        }

        $validated = $request->validate(
            ['coupon_code' => ['required', 'string', 'max:32']],
            [],
            ['coupon_code' => 'mã giảm giá'],
        );

        try {
            $coupon = $this->coupons->resolve(
                $validated['coupon_code'],
                $this->source->basket()->itemsTotal(),
            );
        } catch (CouponException $e) {
            return back()
                ->with('error', $e->getMessage())
                ->withInput($this->draft($request));
        }

        /*
         * auto: false — ĐÂY LÀ LỰA CHỌN CỦA KHÁCH.
         *
         * Cờ này khiến autoApplyBestCoupon() không bao giờ đụng vào mã
         * nữa, kể cả khi có mã giảm nhiều hơn. Có thể họ đang giữ mã kia
         * cho đơn sau, hoặc mã kia sắp hết hạn — hệ thống không biết, và
         * không được đoán.
         */
        $this->source->setCoupon($coupon->code, auto: false);

        /*
         * BỎ coupon_code KHỎI phần giữ lại: mã đã áp xong rồi, để nguyên
         * trong ô nhập thì khách tưởng mình chưa bấm và bấm lại lần nữa.
         */
        return back()
            ->with('success', 'Đã áp dụng mã ' . $coupon->code . '.')
            ->withInput($this->draft($request, except: ['coupon_code']));
    }

    /**
     * Bỏ mã đang áp.
     *
     * declineAutoCoupon() chứ KHÔNG phải clearCoupon().
     *
     * clearCoupon() xoá cả mã lẫn cờ `auto`, nên ngay lần tải trang sau
     * autoApplyBestCoupon() thấy session sạch và áp lại đúng cái mã vừa
     * bỏ. Nút "Bỏ mã" khi đó không bao giờ có tác dụng — bấm bao nhiêu
     * lần mã vẫn nằm nguyên đó.
     */
    public function removeCoupon(Request $request): RedirectResponse
    {
        // Cùng lý do với applyCoupon(): bỏ mã là thao tác trên trang, không
        // phải một bước phải hoàn tất.
        $this->source->declineAutoCoupon();

        return back()
            ->with('info', 'Đã bỏ mã giảm giá. Cửa hàng sẽ không tự chọn mã cho đơn này nữa.')
            ->withInput($this->draft($request));
    }

    /**
     * Cho cửa hàng chọn lại mã tốt nhất.
     *
     * Đối trọng của nút "Bỏ mã": đã cho khách tắt thì phải cho bật lại,
     * nếu không họ kẹt với lựa chọn của chính mình cho tới hết phiên.
     */
    public function autoCoupon(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireItems()) {
            return $redirect;
        }

        $this->source->allowAutoCoupon();

        $message = $this->source->autoApplyBestCoupon()
            ? 'Đã chọn lại mã có lợi nhất cho đơn này.'
            : 'Hiện chưa có mã nào dùng được cho đơn này.';

        return back()->with('info', $message)->withInput($this->draft($request));
    }

    /**
     * Phần biểu mẫu đang gõ dở, để trả lại qua old() sau khi chuyển hướng.
     *
     * VÌ SAO CẦN: ba nút mã giảm giá đều nằm trong biểu mẫu thông tin
     * người nhận (dùng formaction để đổi đích). Không giữ lại thì khách
     * điền xong tên, số điện thoại, địa chỉ rồi bấm "Bỏ mã" là mất sạch
     * — trông đúng như đơn hàng vừa bị huỷ.
     *
     * BỎ _token và _method: đó là thứ của HTTP, không phải của khách, và
     * để chúng lọt vào old() thì lần dựng biểu mẫu sau có thể dùng nhầm
     * một token đã hết hiệu lực.
     *
     * @param  list<string>  $except  ô cần bỏ thêm
     * @return array<string, mixed>
     */
    private function draft(Request $request, array $except = []): array
    {
        return $request->except(array_merge(['_token', '_method', 'wallet_code'], $except));
    }

    /**
     * Dùng điểm thưởng cho đơn này.
     *
     * Chỉ nhận SỐ ĐIỂM muốn dùng. Số tiền giảm, trần 30% và mức tối thiểu
     * do CheckoutBasket / PointRedemption tính — cùng chỗ OrderService đọc
     * khi ghi đơn. Xin nhiều hơn mức được thì kẹp xuống và NÓI RA.
     */
    public function applyPoints(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireItems()) {
            return $redirect;
        }

        $data = $request->validate(
            ['points' => ['required', 'integer', 'min:0', 'max:100000000']],
            [],
            ['points' => 'số điểm'],
        );

        $this->source->setPoints((int) $data['points']);
        $basket = $this->source->basket();
        $dung = $basket->pointsUsed();
        $giu = $this->draft($request, except: ['points']);

        if ($dung === 0) {
            $this->source->clearPoints();

            return back()
                ->with('error', 'Chưa dùng được điểm cho đơn này: cần dùng từ '
                    . \App\Services\Points\PointRedemption::TOI_THIEU . ' điểm, trong số dư và trong mức '
                    . \App\Services\Points\PointRedemption::PHAN_TRAM_TOI_DA . '% tiền hàng.')
                ->withInput($giu);
        }

        $this->source->setPoints($dung);

        $thongBao = 'Đã dùng ' . number_format($dung, 0, ',', '.') . ' điểm, giảm '
            . \App\Services\Shop\Money::format($basket->pointsDiscount()) . '.';

        if ($dung < (int) $data['points']) {
            $thongBao .= ' Đơn này dùng được tối đa ' . number_format($dung, 0, ',', '.') . ' điểm.';
        }

        return back()->with('success', $thongBao)->withInput($giu);
    }

    public function removePoints(Request $request): RedirectResponse
    {
        $this->source->clearPoints();

        return back()
            ->with('info', 'Đã bỏ dùng điểm thưởng cho đơn này.')
            ->withInput($this->draft($request, except: ['points']));
    }

    /* ================= BƯỚC 3: XÁC NHẬN ================= */

    public function confirm(): View|RedirectResponse
    {
        if ($redirect = $this->requireStep(2)) {
            return $redirect;
        }

        $basket = $this->source->basket();
        $values = session(self::SESSION_KEY, []);

        /*
         * LỊCH TRẢ GÓP DỰ KIẾN — cùng hàm với lúc tạo đơn, trên tổng tiền thật.
         * Không còn đủ điều kiện (vừa đổi giỏ, điểm vừa đổi) thì nói ngay ở đây,
         * không đợi bấm đặt hàng mới báo.
         */
        $lichTraGop = null;
        $traGopLoi = null;

        if (($values['payment_method'] ?? null) === PaymentMethod::TraGop->value) {
            try {
                $lichTraGop = app(\App\Services\Installment\InstallmentService::class)
                    ->duKien(Auth::user(), $basket->grandTotal(), (int) ($values['so_ky'] ?? 0))['lich'];
            } catch (\App\Services\Installment\InstallmentException $e) {
                $traGopLoi = $e->getMessage() . ' Hãy quay lại chọn hình thức thanh toán khác.';
            }
        }

        return view('shop.checkout.confirm', [
            'step' => 2,
            'basket' => $basket,
            'values' => $values,
            'lichTraGop' => $lichTraGop,
            'traGopLoi' => $traGopLoi,
        ]);
    }

    public function place(Request $request): RedirectResponse
    {
        /*
         * KIỂM TRA CHỐNG ĐẶT TRÙNG PHẢI ĐỨNG TRƯỚC MỌI THỨ KHÁC.
         *
         * Đặt sau requireStep(2) là hỏng: đơn đầu tiên đã dọn dữ liệu
         * thanh toán khỏi phiên, nên lần bấm thứ hai trượt requireStep và
         * bị đẩy về giỏ hàng kèm thông báo lỗi. Khách tưởng đặt không
         * thành công nên đặt lại lần nữa — đúng cái ta đang muốn tránh.
         *
         * Đứng ở đây thì lần bấm thứ hai được đưa thẳng tới đơn vừa đặt.
         */
        if ($existing = $this->guard->existingOrder()) {
            return redirect()
                ->route('shop.orders.show', $existing)
                ->with('success', 'Đơn hàng của bạn đã được ghi nhận trước đó.');
        }

        if ($redirect = $this->requireStep(2)) {
            return $redirect;
        }

        try {
            $basket = $this->source->basket();

            /*
             * KIỂM LẠI MÃ GIẢM GIÁ VỚI HÌNH THỨC THANH TOÁN ĐÃ CHỌN.
             *
             * Đây là chỗ DUY NHẤT biết đủ cả hai vế: khách áp mã ở bước 2
             * khi chưa chắc đã chọn xong hình thức thanh toán, nên chặn ở
             * đó là chặn oan. Tới đây thì cả mã lẫn hình thức đều đã cố
             * định, và đây cũng là lần cuối trước khi tiền được chốt.
             *
             * Ném CouponException -> khách quay lại bước 2 với lời giải
             * thích cụ thể, giỏ hàng còn nguyên.
             */
            if ($basket->coupon) {
                $method = PaymentMethod::tryFrom(
                    (string) (session(self::SESSION_KEY)['payment_method'] ?? '')
                );

                if ($method !== null) {
                    $this->coupons->resolve(
                        $basket->coupon->code,
                        $basket->itemsTotal(),
                        $method,
                    );
                }
            }

            /*
             * ĐỌC RA BIẾN TRƯỚC KHI DÙNG.
             *
             * LỖI ĐÃ SỬA: khối chuyển hướng sang MoMo ở cuối hàm đọc
             * `$checkout['momo_flow']` — một biến KHÔNG TỒN TẠI trong
             * hàm này. Toán tử `??` nuốt luôn cảnh báo "undefined
             * variable", nên lựa chọn "quét mã QR" của khách lặng lẽ rơi
             * về mức mặc định và MoMo mở ra trang nhập thẻ. Đo được trên
             * MoMo thật: chọn QR, nhận form thẻ.
             */
            $duLieuThanhToan = session(self::SESSION_KEY, []);

            $order = $this->orders->place(
                $basket,
                $duLieuThanhToan,
                $this->guard->key(),
            );
        } catch (CouponException $e) {
            /*
             * Mã không hợp với hình thức thanh toán vừa chọn. Đưa khách
             * về ĐÚNG bước 2 — nơi có cả ô mã lẫn ô chọn thanh toán — chứ
             * không về giỏ hàng: họ chỉ cần đổi một trong hai thứ, không
             * cần làm lại từ đầu.
             */
            return redirect()
                ->route('shop.checkout.shipping')
                ->with('error', $e->getMessage());
        } catch (\App\Services\Points\PointException $e) {
            /*
             * Điểm vừa được dùng ở nơi khác trong lúc đang xem lại đơn. Đơn
             * đã cuộn lại; bỏ số điểm cũ và đưa về bước có ô dùng điểm.
             */
            $this->source->clearPoints();

            return redirect()
                ->route('shop.checkout.details')
                ->with('error', $e->getMessage());
        } catch (\App\Services\Installment\InstallmentException $e) {
            /*
             * Không còn đủ điều kiện trả góp lúc bấm đặt (điểm vừa đổi, tab khác
             * vừa mở một kế hoạch). Đơn đã cuộn lại; về bước chọn thanh toán.
             */
            return redirect()
                ->route('shop.checkout.details')
                ->with('error', $e->getMessage());
        } catch (OrderException $e) {
            // Hết hàng giữa chừng là chuyện có thật khi nhiều người mua
            // cùng lúc — đưa khách về giỏ để chỉnh, không để trang lỗi.
            return redirect()
                ->route('shop.cart.index')
                ->with('error', $e->getMessage());
        }

        session()->forget(self::SESSION_KEY);

        /*
         * Dọn đúng nguồn vừa dùng: mua ngay thì xoá phiên mua ngay và
         * GIỮ NGUYÊN giỏ hàng; thanh toán từ giỏ thì xoá hàng trong giỏ.
         */
        if ($basket->source === 'direct') {
            $this->source->clearDirect();
        } else {
            /*
             * clearSelected() chứ KHÔNG phải clear().
             *
             * Đơn hàng chỉ gồm những món khách đã tích, nên chỉ được xoá
             * đúng những món đó. Gọi clear() ở đây là cuốn sạch cả thứ họ
             * cố ý để lại cho lần mua sau — mất dữ liệu của khách mà
             * không có cách nào lấy lại.
             */
            $this->cart->clearSelected();
        }

        // Ghi nhớ mã đơn để khách vãng lai còn xem lại được đơn của mình.
        session()->push(self::PLACED_KEY, $order->order_number);

        /*
         * Lượt dùng mã do OrderService ghi, TRONG transaction tạo đơn —
         * xem chú thích ở OrderService::createOrder(). Ở đây chỉ còn
         * việc dọn phiên: mã đã thành một phần của đơn, giữ lại trong
         * phiên thì đơn sau tự áp lại một mã khách chưa hề chọn.
         */
        if ($basket->coupon) {
            $this->source->clearCoupon();
        }

        /*
         * Lời từ chối "đừng tự chọn mã" chỉ có giá trị cho ĐƠN VỪA ĐẶT.
         * Không xoá ở đây thì đơn sau trong cùng phiên cũng bị tắt tự
         * chọn mã, mà khách không hề nói vậy.
         */
        $this->source->allowAutoCoupon();

        // Điểm đã thành một phần của đơn; để lại trong phiên thì đơn sau tự dùng điểm khách chưa chọn.
        $this->source->clearPoints();

        /*
         * KHÔNG GỬI EMAIL Ở ĐÂY.
         *
         * Đơn vừa tạo đang ở trạng thái "Chờ xác nhận" — cửa hàng chưa
         * hề nhìn thấy nó. Gửi một lá thư tên là "Xác nhận đơn hàng"
         * vào lúc này là nói với khách rằng cửa hàng đã nhận lời, trong
         * khi thật ra chưa ai kiểm hàng còn hay hết, chưa ai xem địa chỉ
         * giao có ship tới được không.
         *
         * Hậu quả thật khi cửa hàng phải từ chối sau đó: khách đã cầm
         * trong tay một thư "xác nhận" và hiểu là đã chốt xong.
         *
         * "Chờ xác nhận" và "Đã xác nhận" là HAI TRẠNG THÁI KHÁC NHAU,
         * và chỉ trạng thái thứ hai mới đáng một lá thư. Thư đó do
         * OrderService::changeStatus() gửi khi admin chuyển đơn sang "Đã
         * xác nhận" — xem OrderStatus::notifiesCustomer().
         *
         * Khách vẫn thấy ngay mã đơn và toàn bộ chi tiết trên màn hình
         * này; khách vãng lai còn có trang Tra cứu đơn hàng. Không ai
         * mất đường theo dõi đơn của mình.
         */

        $this->logPurchase($request, $order);

        /*
         * ĐƠN MOMO ĐI THẲNG SANG CỔNG.
         *
         * Đơn đã ghi xong và kho đã trừ trước khi rời khỏi đây, nên
         * khách bỏ ngang giữa trang MoMo vẫn còn đơn để trả lại — chứ
         * không mất trắng cả giỏ hàng. Nút "Thanh toán lại" ở trang đơn
         * dùng chính đơn đó, không tạo đơn mới.
         */
        if ($order->payment_method === PaymentMethod::Momo) {
            /*
             * MANG THEO CÁCH TRẢ TIỀN KHÁCH ĐÃ CHỌN ở bước 3 (quét mã QR
             * hay nhập thẻ). Không mang theo thì mọi đơn rơi về mức mặc
             * định, và ô chọn kia thành ô trang trí.
             */
            $flow = MomoFlow::tryFrom((string) ($duLieuThanhToan['momo_flow'] ?? ''))
                ?? MomoFlow::macDinh();

            return redirect()->route('shop.payment.momo.start', [
                $order,
                'cach' => $flow->value,
            ]);
        }

        return redirect()->route('shop.orders.show', $order);
    }

    /**
     * Ghi nhận hành vi mua hàng.
     *
     * Đặt ở ĐÂY, sau khi OrderService::place() trả về thành công và
     * transaction đã commit — không ghi lúc khách bấm nút. Nếu hết hàng
     * giữa chừng thì đơn không được tạo, và cũng không được có event
     * purchase nào (yêu cầu mục XV).
     *
     * Ghi MỘT DÒNG MỖI SẢN PHẨM chứ không phải một dòng mỗi đơn: Guide
     * mục 9 muốn phân tích "sản phẩm được mua nhiều", nên đơn vị phân
     * tích phải là sản phẩm.
     */
    private function logPurchase(Request $request, Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->product_id === null) {
                continue;
            }

            UserEvent::log(UserEventType::Purchase, $request, [
                'product_id' => $item->product_id,
                'meta' => [
                    'order_number' => $order->order_number,
                    'quantity' => $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                ],
            ]);
        }
    }

    /* ===================== KIỂM TRA ===================== */

    /**
     * Bảo đảm có hàng để thanh toán.
     *
     * Kiểm tra GIỎ HÀNG THANH TOÁN chứ không phải giỏ hàng: phiên "mua
     * ngay" có thể có hàng trong khi giỏ hàng trống.
     */
    private function requireItems(): ?RedirectResponse
    {
        if ($this->source->basket()->isEmpty()) {
            // Phiên mua ngay hỏng (sản phẩm bị gỡ bán) cũng rơi vào đây.
            $this->source->clearDirect();

            /*
             * PHÂN BIỆT "GIỎ TRỐNG" VỚI "CHƯA TÍCH MÓN NÀO".
             *
             * Từ khi chọn được từng món, giỏ có hàng mà không tích cái
             * nào cũng cho ra một giỏ thanh toán rỗng. Báo chung một câu
             * "Không có sản phẩm nào để thanh toán" thì khách nhìn giỏ
             * đầy hàng và kết luận website hỏng.
             */
            $chuaChon = ! $this->source->hasDirect() && $this->cart->count() > 0;

            return redirect()
                ->route('shop.cart.index')
                ->with('error', $chuaChon
                    ? 'Bạn chưa tích món nào để thanh toán. Hãy chọn ít nhất một sản phẩm trong giỏ.'
                    : 'Không có sản phẩm nào để thanh toán.');
        }

        return null;
    }

    /**
     * Bảo đảm các bước trước đã hoàn tất trước khi vào bước sau.
     */
    private function requireStep(int $completed): ?RedirectResponse
    {
        if ($redirect = $this->requireItems()) {
            return $redirect;
        }

        $data = session(self::SESSION_KEY, []);

        /*
         * MỘT ĐIỀU KIỆN CHO CẢ BA THỨ, vì nay chúng cùng một biểu mẫu.
         *
         * Trước đây tách làm hai: thiếu người nhận thì về bước 1, thiếu
         * hình thức thanh toán thì về bước 2. Từ khi gộp, cả ba đều điền
         * ở cùng một chỗ nên chỉ còn một đích để quay về — và thiếu bất
         * cứ cái nào cũng là chưa qua bước 1.
         */
        $thieu = empty($data['recipient_name'])
            || empty($data['shipping_address'])
            || empty($data['payment_method']);

        if ($thieu) {
            return redirect()->route('shop.checkout.details');
        }

        return null;
    }
}
