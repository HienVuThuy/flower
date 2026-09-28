<?php

namespace App\Http\Controllers\Shop;

use App\Enums\MomoFlow;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
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

/** Thanh toán 2 bước. */
class CheckoutController extends Controller
{
    private const SESSION_KEY = CheckoutSource::FORM_KEY;

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

    public function details(): View|RedirectResponse
    {
        if ($redirect = $this->requireItems()) {
            return $redirect;
        }

        $this->source->autoApplyBestCoupon();

        $basket = $this->source->basket();

        $saved = session(self::SESSION_KEY, []);
        $user = Auth::user();
        $addresses = $user?->addresses ?? collect();

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
            'paymentMethods' => PaymentMethod::available(),

            'traGop' => app(\App\Services\Installment\InstallmentPolicy::class)->xet(Auth::user(), $basket->grandTotal()),

            'couponIsAuto' => $this->source->couponIsAuto(),

            'autoDeclined' => $this->source->autoCouponDeclined(),

            'couponChoices' => $this->couponChoices($basket),
        ]);
    }

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
            ->sortBy(fn (array $row) => $row['reason'] === null ? 0 : 1)
            ->values();
    }

    public function storeDetails(CheckoutDetailsRequest $request): RedirectResponse
    {
        if ($redirect = $this->requireItems()) {
            return $redirect;
        }

        $this->guard->reset();

        $data = $request->validated();

        if ($id = $request->integer('address_id')) {
            $address = Address::where('user_id', Auth::id())->find($id);

            if (! $address) {
                return back()->with('error', 'Địa chỉ không hợp lệ.');
            }

            $data = $address->toCheckoutData()
                + ['address_id' => $address->id]
                + $data;
        } elseif (Auth::check() && $request->boolean('save_address')) {
            $this->saveAddress($data);
        }

        session([
            self::SESSION_KEY => array_merge(session(self::SESSION_KEY, []), $data),
        ]);

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

        if ($user->addresses()->count() === 1) {
            $address->makeDefault();
        }
    }

    public function shipping(): RedirectResponse
    {
        return redirect()->route('shop.checkout.details', status: 301);
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireItems()) {
            return $redirect;
        }

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

        $this->source->setCoupon($coupon->code, auto: false);

        return back()
            ->with('success', 'Đã áp dụng mã ' . $coupon->code . '.')
            ->withInput($this->draft($request, except: ['coupon_code']));
    }

    public function removeCoupon(Request $request): RedirectResponse
    {
        $this->source->declineAutoCoupon();

        return back()
            ->with('info', 'Đã bỏ mã giảm giá. Cửa hàng sẽ không tự chọn mã cho đơn này nữa.')
            ->withInput($this->draft($request));
    }

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

    private function draft(Request $request, array $except = []): array
    {
        return $request->except(array_merge(['_token', '_method', 'wallet_code'], $except));
    }

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
                    . \App\Services\Points\PointRedemption::toiThieu() . ' điểm, trong số dư và trong mức '
                    . \App\Services\Points\PointRedemption::phanTramToiDa() . '% tiền hàng.')
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

    public function confirm(): View|RedirectResponse
    {
        if ($redirect = $this->requireStep(2)) {
            return $redirect;
        }

        $basket = $this->source->basket();
        $values = session(self::SESSION_KEY, []);

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
        if ($existing = $this->guard->existingOrder()) {
            /* Bấm "Đặt hàng" hai lần: đơn chỉ tạo một lần. Nếu đơn trả trước mà chưa trả tiền
               thì đưa khách đi tiếp tới cổng, đừng bỏ khách lại ở trang đơn rồi bắt bấm lại. */
            if ($existing->payment_method === PaymentMethod::Momo
                && $existing->payment_status === PaymentStatus::Unpaid
                && ! $existing->status->isFinal()
            ) {
                return redirect()->route('shop.payment.momo.start', [
                    $existing,
                    'cach' => $this->momoFlow(),
                ]);
            }

            return redirect()
                ->route('shop.orders.show', $existing)
                ->with('success', 'Đơn hàng của bạn đã được ghi nhận trước đó.');
        }

        if ($redirect = $this->requireStep(2)) {
            return $redirect;
        }

        try {
            $basket = $this->source->basket();

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

            $duLieuThanhToan = session(self::SESSION_KEY, []);

            $order = $this->orders->place(
                $basket,
                $duLieuThanhToan,
                $this->guard->key(),
            );
        } catch (CouponException $e) {
            return redirect()
                ->route('shop.checkout.shipping')
                ->with('error', $e->getMessage());
        } catch (\App\Services\Points\PointException $e) {
            $this->source->clearPoints();

            return redirect()
                ->route('shop.checkout.details')
                ->with('error', $e->getMessage());
        } catch (\App\Services\Installment\InstallmentException $e) {
            return redirect()
                ->route('shop.checkout.details')
                ->with('error', $e->getMessage());
        } catch (OrderException $e) {
            return redirect()
                ->route('shop.cart.index')
                ->with('error', $e->getMessage());
        }

        session()->forget(self::SESSION_KEY);

        if ($basket->source === 'direct') {
            $this->source->clearDirect();
        } else {
            $this->cart->clearSelected();
        }

        session()->push(self::PLACED_KEY, $order->order_number);

        if ($basket->coupon) {
            $this->source->clearCoupon();
        }

        $this->source->allowAutoCoupon();

        $this->source->clearPoints();

        $this->logPurchase($request, $order);

        if ($order->payment_method === PaymentMethod::Momo) {
            return redirect()->route('shop.payment.momo.start', [
                $order,
                'cach' => $this->momoFlow($duLieuThanhToan),
            ]);
        }

        return redirect()->route('shop.orders.show', $order);
    }

    /**
     * Cách trả tiền MoMo khách đã chọn ở bước 1: quét mã QR hay thẻ quốc tế.
     * Đặt hàng xong thì phiên thanh toán bị dọn, nên nơi gọi truyền vào dữ liệu đã chụp
     * trước đó; không có thì lui về mức mặc định.
     */
    private function momoFlow(?array $duLieuThanhToan = null): string
    {
        $duLieuThanhToan ??= session(self::SESSION_KEY, []);

        $flow = MomoFlow::tryFrom((string) ($duLieuThanhToan['momo_flow'] ?? ''))
            ?? MomoFlow::macDinh();

        return $flow->value;
    }

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

    private function requireItems(): ?RedirectResponse
    {
        if ($this->source->basket()->isEmpty()) {
            $this->source->clearDirect();

            $chuaChon = ! $this->source->hasDirect() && $this->cart->count() > 0;

            return redirect()
                ->route('shop.cart.index')
                ->with('error', $chuaChon
                    ? 'Bạn chưa tích món nào để thanh toán. Hãy chọn ít nhất một sản phẩm trong giỏ.'
                    : 'Không có sản phẩm nào để thanh toán.');
        }

        return null;
    }

    private function requireStep(int $completed): ?RedirectResponse
    {
        if ($redirect = $this->requireItems()) {
            return $redirect;
        }

        $data = session(self::SESSION_KEY, []);

        $thieu = empty($data['recipient_name'])
            || empty($data['shipping_address'])
            || empty($data['payment_method']);

        if ($thieu) {
            return redirect()->route('shop.checkout.details');
        }

        return null;
    }
}
