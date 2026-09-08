<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderStatusEvent;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Checkout\CheckoutBasket;
use App\Services\Checkout\CheckoutLine;
use App\Services\Audit\ActivityLogger;
use App\Services\Care\CareScheduler;
use App\Services\Coupon\CouponService;
use App\Services\Invoice\InvoiceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Đặt hàng và đổi trạng thái đơn.
 * ============================================================
 * Hai điều bắt buộc phải đúng, và cả hai đều nằm trong transaction:
 *
 *  1. CHỤP GIÁ. Đơn hàng lưu tên và giá tại thời điểm đặt. Sau này
 *     đổi giá hay kết thúc khuyến mại cũng không được viết lại đơn cũ.
 *
 *  2. TRỪ KHO AN TOÀN. Đọc tồn kho bằng lockForUpdate() rồi mới trừ,
 *     nếu không hai khách bấm mua cùng lúc sẽ cùng đọc thấy "còn 1" và
 *     cùng đặt được, tức là bán quá số hàng đang có.
 */
class OrderService
{
    public function __construct(
        private readonly OrderNumberGenerator $numbers,
        private readonly CouponService $coupons,
        private readonly OrderMailer $mailer,
        private readonly CareScheduler $care,
        private readonly OrderRiskScorer $risk,
        private readonly ActivityLogger $audit,
        private readonly InvoiceService $invoices,
    ) {
    }

    /**
     * Đặt hàng từ giỏ hàng thanh toán.
     *
     * Nguồn hàng (giỏ hàng hay "mua ngay") do CheckoutSource quyết định;
     * ở đây chỉ quan tâm tới các dòng hàng.
     *
     * @param  array<string, mixed>  $checkout  dữ liệu đã validate từ 2 bước đầu
     *
     * @throws OrderException
     */
    public function place(CheckoutBasket $basket, array $checkout, ?string $idempotencyKey = null): Order
    {
        if ($basket->isEmpty()) {
            throw new OrderException('Giỏ hàng đang trống.');
        }

        try {
            return DB::transaction(fn () => $this->createOrder($basket, $checkout, $idempotencyKey));
        } catch (QueryException $e) {
            /*
             * TRÙNG KHOÁ CHỐNG ĐẶT LẠI — KHÔNG PHẢI LỖI.
             *
             * Tới đây nghĩa là một request song song đã tạo xong đơn với
             * đúng khoá này trong lúc ta đang chạy. Cơ sở dữ liệu từ chối
             * bản ghi thứ hai, và đó chính là điều ta muốn: trả về đơn đã
             * có, coi như lần bấm này thành công.
             *
             * Transaction đã bị cuộn lại nên kho KHÔNG bị trừ hai lần.
             *
             * Chỉ nuốt đúng lỗi trùng khoá (SQLSTATE 23000); mọi lỗi cơ
             * sở dữ liệu khác vẫn phải ném ra để không giấu sự cố thật.
             */
            if ($idempotencyKey && $this->isDuplicateKeyError($e)) {
                $existing = Order::where('idempotency_key', $idempotencyKey)->first();

                if ($existing) {
                    return $existing->load('items');
                }
            }

            throw $e;
        }
    }

    /**
     * Lỗi này có phải do vi phạm ràng buộc duy nhất không.
     *
     * Kiểm tra cả mã SQLSTATE lẫn tên cột: bảng orders còn một ràng buộc
     * UNIQUE nữa là order_number, mà trùng order_number là chuyện khác
     * hẳn — không được coi là đặt lại và không được nuốt.
     */
    private function isDuplicateKeyError(QueryException $e): bool
    {
        return $e->getCode() === '23000'
            && str_contains($e->getMessage(), 'idempotency_key');
    }

    /**
     * @param  array<string, mixed>  $checkout
     */
    private function createOrder(CheckoutBasket $basket, array $checkout, ?string $idempotencyKey): Order
    {
        /*
         * THUẾ TÍNH THEO TỪNG DÒNG, không tính trên tổng đơn.
         *
         * Mỗi sản phẩm có thể thuộc một nhóm thuế riêng (bó hoa không
         * chịu VAT, chậu sứ chịu 10%), nên một phép tính trên tổng là
         * sai ngay ở đơn hỗn hợp. Xem App\Services\Tax\BasketTax.
         */
        $tax = $basket->tax();

        $lines = [];

        foreach ($basket->lines->values() as $i => $line) {
            $lines[] = $this->buildLine($line, $tax->lines[$i] ?? null);
        }

        /*
         * Tiền do CheckoutBasket tính — cùng một nơi mà màn hình thanh
         * toán đang hiển thị, nên con số khách thấy và con số ghi vào
         * đơn không thể lệch nhau.
         */
        $subtotal = $basket->baseTotal();
        $discountTotal = $basket->discountTotal();
        $shippingFee = $basket->shippingFee();

        $grandTotal = $basket->grandTotal();

        $order = Order::create([
            'order_number' => $this->numbers->generate(),
            'idempotency_key' => $idempotencyKey,
            'user_id' => Auth::id(),
            'recipient_name' => $checkout['recipient_name'],
            'recipient_phone' => $checkout['recipient_phone'],
            'recipient_email' => $checkout['recipient_email'] ?? null,
            'shipping_address' => $checkout['shipping_address'],
            'shipping_ward' => $checkout['shipping_ward'] ?? null,
            'shipping_district' => $checkout['shipping_district'] ?? null,
            'shipping_province' => $checkout['shipping_province'],
            'delivery_date' => $checkout['delivery_date'] ?? null,
            'delivery_note' => $checkout['delivery_note'] ?? null,
            'payment_method' => $checkout['payment_method'],
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            // Chụp mã giảm giá vào đơn: xoá coupon khỏi hệ thống sau này
            // cũng không làm đơn cũ mất dấu đã dùng mã gì, giảm bao nhiêu.
            'coupon_id' => $basket->coupon?->id,
            'coupon_code' => $basket->coupon?->code,
            'coupon_discount' => $basket->couponDiscount(),
            'shipping_fee' => $shippingFee,

            /*
             * CHỤP MÃ ĐỊA GIỚI GHN VÀO ĐƠN.
             *
             * Không có hai mã này thì sau đó không tạo được vận đơn: GHN
             * cần đúng `to_district_id` và `to_ward_code`, và tên chữ
             * "Quận Bắc Từ Liêm" không suy ngược ra mã được.
             *
             * Lưu cả cước GHN thật, TÁCH RIÊNG khỏi `shipping_fee`.
             * `shipping_fee` là tiền cửa hàng THU của khách;
             * `ghn_total_fee` là tiền cửa hàng TRẢ cho GHN. Hai con số
             * lệch nhau mỗi khi miễn phí giao cho đơn lớn, và chỉ giữ cả
             * hai mới biết tháng này bù lỗ bao nhiêu tiền ship.
             */
            'to_district_id' => $basket->toDistrictId,
            'to_ward_code' => $basket->toWardCode,
            'ghn_total_fee' => app(\App\Services\Shipping\ShippingQuote::class)
                ->ghnFee($basket, $basket->toDistrictId, $basket->toWardCode) ?? 0,
            'grand_total' => $grandTotal,

            /*
             * THUẾ — TÁCH RA từ tổng, KHÔNG cộng thêm vào.
             *
             * Giá niêm yết đã bao gồm VAT (xem config/tax.php), nên
             * `grand_total` ở trên KHÔNG đổi một đồng nào vì mấy dòng
             * này. Chúng chỉ ghi lại: trong số tiền khách trả, bao nhiêu
             * là thuế.
             *
             * NULL khi tính thuế đang tắt — KHÔNG phải 0.
             *
             * NULL đọc ra là "không có số liệu", 0 đọc ra là "thuế bằng
             * không". Hai điều khác hẳn nhau khi đối chiếu sổ sách, và
             * gộp chúng lại là làm mất khả năng phân biệt "chưa cấu hình"
             * với "hàng miễn thuế".
             *
             * `tax_rate` ở ĐẦU ĐƠN nay có nghĩa hẹp hơn trước: nó là MỨC
             * MẶC ĐỊNH CỦA CỬA HÀNG lúc đặt — mức áp cho phí vận chuyển
             * và cho sản phẩm chưa phân loại. Mức thật của từng mặt hàng
             * nằm ở `order_items.tax_rate`, vì một đơn có thể mang nhiều
             * mức cùng lúc.
             *
             * Đẳng thức đối soát luôn đúng:
             *     tax_amount = SUM(items.tax_amount) + shipping_tax_amount
             */
            'tax_rate' => $tax->shippingRate,
            'tax_amount' => $tax->total(),
            'shipping_tax_amount' => $tax->shippingTax,
        ]);

        $order->items()->createMany($lines);

        /*
         * DỮ LIỆU HOÁ ĐƠN — TRONG CÙNG TRANSACTION VỚI ĐƠN.
         *
         * Cùng lý do với việc ghi nhận lượt dùng mã giảm giá ngay bên
         * dưới: nếu bước này chạy sau khi transaction đã commit và nó
         * ngã, đơn ĐÃ ghi xong và không cuộn lại được. Kết quả là một
         * đơn nói "khách có yêu cầu xuất hoá đơn" mà không có dữ liệu
         * hoá đơn nào — và chỉ phát hiện khi khách gọi điện hỏi.
         *
         * `load('items')` trước khi lập: hoá đơn cần bảng tách theo mức
         * thuế, mà bảng đó dựng từ các dòng vừa ghi. Không nạp lại thì
         * quan hệ `items` vẫn rỗng và bảng tách ra rỗng theo — âm thầm,
         * không lỗi nào.
         *
         * Khách không yêu cầu hoá đơn thì hàm trả về null và không có
         * bản ghi nào được tạo. Đó là trường hợp thường gặp nhất.
         */
        $order->load('items');
        $this->invoices->taoTuDon($order, $checkout);

        /*
         * CHẤM ĐIỂM RỦI RO — sau khi đơn đã có id và đã có dòng hàng.
         *
         * Phải nằm TRONG transaction tạo đơn: điểm là một phần của bản
         * ghi đơn, không phải thông tin thêm. Đơn ghi thành công mà điểm
         * hỏng thì trang quản trị hiện 0 điểm cho một đơn đáng ngờ — tệ
         * hơn hẳn việc không có tính năng này.
         *
         * Lớp chấm điểm KHÔNG BAO GIỜ chặn đơn, chỉ ghi số và liệt kê
         * dấu hiệu. Xem App\Services\Order\OrderRiskScorer.
         */
        $this->risk->apply($order);

        /*
         * GHI NHẬN LƯỢT DÙNG MÃ — TRONG CÙNG TRANSACTION VỚI ĐƠN.
         *
         * LỖI TRƯỚC KHI SỬA: chỗ này nằm ở CheckoutController, chạy SAU
         * khi transaction tạo đơn đã commit. Hai hỏng hóc từ đó:
         *
         *  1. MÃ DÙNG MIỄN PHÍ. Nếu redeem() ngã — mất kết nối, hết
         *     lượt, lỗi bất kỳ — thì đơn ĐÃ ghi xong và không cuộn lại
         *     được. Khách nhận hàng đã giảm giá, còn bộ đếm mã đứng yên.
         *     Một mã "50 lượt" có thể dùng vô hạn theo đúng cách này.
         *
         *  2. ĐẶT LẠI THÌ ĐẾM HAI LẦN. place() có chống đặt trùng: bấm
         *     hai lần thì lần sau trả về ĐƠN CŨ chứ không tạo đơn mới.
         *     Nhưng controller không phân biệt được, nên vẫn gọi
         *     redeem() lần nữa cho cùng một đơn.
         *
         * Đặt vào createOrder() giải quyết cả hai: cùng transaction nên
         * hỏng là cuộn lại cùng nhau, và nó chỉ chạy trên đường THẬT SỰ
         * tạo đơn mới — đường trả về đơn cũ không đi qua đây.
         *
         * CouponException ném ra từ đây sẽ cuộn cả đơn — đúng ý: thà
         * không có đơn còn hơn có đơn giảm giá bằng lượt không tồn tại.
         */
        if ($basket->coupon) {
            $this->coupons->redeem($basket->coupon, $order);
        }

        /*
         * BƯỚC ĐẦU TIÊN CỦA DÒNG THỜI GIAN, ghi ngay khi đơn ra đời.
         *
         * Trong cùng transaction: một đơn tồn tại mà không có bước nào
         * trong lịch sử là một đơn mà trang tra cứu hiện ra trống trơn —
         * khách hiểu là cửa hàng chưa nhận được gì.
         *
         * Không ghi người thực hiện: đơn do chính khách tạo, và trên
         * dòng thời gian thì "bạn đã đặt đơn" không cần ai đứng tên.
         */
        OrderStatusEvent::create([
            'order_id' => $order->id,
            'status' => $order->status->value,
            'changed_by' => null,
            'note' => null,
        ]);

        return $order->load('items');
    }

    /**
     * Dựng một dòng đơn hàng, đồng thời trừ kho.
     *
     * Giá lấy từ CheckoutLine (tính lại từ Product/Variant), không bao
     * giờ từ dữ liệu client gửi lên.
     *
     * @return array<string, mixed>
     */
    /**
     * @param  array{line: CheckoutLine, discount: string, taxable: string, rate: ?string, tax: ?string}|null  $thue
     *   Số liệu thuế của chính dòng này, do BasketTax tính. null khi
     *   tính thuế đang tắt — khi đó ba cột thuế của dòng để trống, đúng
     *   nghĩa "không có số liệu".
     */
    private function buildLine(CheckoutLine $line, ?array $thue = null): array
    {
        $product = $line->product;
        $variant = $line->variant;

        $this->consumeStock($product, $variant, $line->quantity);

        return [
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'product_name' => $product->name,
            'product_sku' => $product->product_code,
            'variant_name' => $variant?->name,
            'promotion_name' => $line->promotionName(),
            'unit_base_price' => $line->unitBasePrice(),
            'unit_price' => $line->unitPrice(),
            'quantity' => $line->quantity,
            'line_total' => $line->lineTotal(),

            /*
             * CHỤP THUẾ VÀO DÒNG HÀNG.
             *
             * Admin đổi phân loại thuế của sản phẩm, hay Nhà nước đổi
             * mức, thì đơn cũ vẫn phải giữ nguyên con số đã áp lúc đặt.
             * Không chụp thì báo cáo quý trước tự đổi mỗi lần ai đó sửa
             * một dòng cấu hình.
             *
             * `discount_amount` là phần mã giảm giá PHÂN BỔ cho dòng
             * này — cần để giải thích vì sao tiền chịu thuế của dòng nhỏ
             * hơn `line_total`.
             */
            'discount_amount' => $thue['discount'] ?? '0.00',
            'tax_rate' => $thue['rate'] ?? null,
            'tax_amount' => $thue['tax'] ?? null,
        ];
    }

    /**
     * Món này còn bán được không — KIỂM LẠI NGAY TRƯỚC KHI TRỪ KHO.
     *
     * LỖI TRƯỚC KHI SỬA: CartService::assertPurchasable() kiểm đủ (còn
     * bán, còn hoạt động, biến thể đúng sản phẩm) nhưng nó chạy lúc
     * THÊM VÀO GIỎ. Giữa lúc đó và lúc đặt hàng có thể là hàng giờ:
     *
     *   10:00  khách thêm vào giỏ
     *   10:05  admin chuyển sản phẩm sang "nháp" hoặc tắt biến thể
     *   10:06  khách bấm đặt hàng  ->  đơn vẫn được tạo
     *
     * Cửa hàng nhận đơn cho thứ vừa ngừng bán, và chỉ phát hiện khi
     * chuẩn bị hàng.
     *
     * ĐỌC LẠI TỪ CƠ SỞ DỮ LIỆU trong cùng transaction, không tin đối
     * tượng đã nạp từ trước — chính đối tượng đó mới là thứ đã cũ.
     */
    private function assertStillSellable(Product $product, ?ProductVariant $variant): void
    {
        $fresh = Product::whereKey($product->id)->first();

        if (! $fresh || $fresh->status !== 'active') {
            throw new OrderException(sprintf(
                'Sản phẩm "%s" vừa ngừng bán. Vui lòng bỏ khỏi giỏ hàng rồi đặt lại.',
                $product->name,
            ));
        }

        // Sản phẩm chuyển sang "liên hệ báo giá" sau khi đã vào giỏ.
        if ($fresh->base_price === null) {
            throw new OrderException(sprintf(
                'Sản phẩm "%s" nay chỉ nhận báo giá, không bán trực tiếp.',
                $product->name,
            ));
        }

        if (! $variant) {
            /*
             * SẢN PHẨM CÓ QUY CÁCH THÌ DÒNG ĐƠN PHẢI CÓ QUY CÁCH.
             *
             * CartService đã chặn chuyện này ở cửa vào giỏ, nhưng giỏ có
             * thể mang sẵn dòng cũ được thêm TRƯỚC khi có phép kiểm đó —
             * và cửa hàng có thể mới thêm quy cách cho một sản phẩm mà
             * trước đây bán trơn.
             *
             * Đây là cổng CUỐI trước khi dòng hàng thành đơn thật, nên
             * nó phải tự kiểm chứ không dựa vào việc cửa trước đã kiểm.
             * Lọt qua đây là cửa hàng nhận một đơn không nói rõ giao
             * chậu nào, và tính theo giá quy cách rẻ nhất.
             */
            $coQuyCach = $fresh->variants()->where('is_active', true)->exists();

            if ($coQuyCach) {
                throw new OrderException(sprintf(
                    'Sản phẩm "%s" nay bán theo quy cách. Vui lòng bỏ khỏi giỏ hàng '
                    .'rồi chọn lại quy cách bạn muốn.',
                    $product->name,
                ));
            }

            return;
        }

        $freshVariant = ProductVariant::whereKey($variant->id)->first();

        /*
         * Kiểm cả product_id: biến thể có thể đã được chuyển sang sản
         * phẩm khác. Không kiểm thì đơn ghi tên sản phẩm này nhưng trừ
         * kho của sản phẩm kia.
         */
        if (! $freshVariant
            || ! $freshVariant->is_active
            || $freshVariant->product_id !== $fresh->id) {
            throw new OrderException(sprintf(
                'Quy cách "%s" của sản phẩm "%s" vừa ngừng bán.',
                $variant->name,
                $product->name,
            ));
        }
    }

    private function consumeStock(Product $product, ?ProductVariant $variant, int $quantity): void
    {
        $this->assertStillSellable($product, $variant);

        if ($variant) {
            if (! $variant->track_inventory) {
                return;
            }

            $fresh = ProductVariant::whereKey($variant->id)->lockForUpdate()->first();

            if (! $fresh || $fresh->stock_quantity < $quantity) {
                throw new OrderException(sprintf(
                    'Sản phẩm "%s - %s" không đủ hàng (còn %d).',
                    $product->name,
                    $variant->name,
                    (int) ($fresh->stock_quantity ?? 0),
                ));
            }

            $fresh->decrement('stock_quantity', $quantity);

            return;
        }

        if (! $product->track_inventory) {
            return;
        }

        $fresh = Product::whereKey($product->id)->lockForUpdate()->first();

        if (! $fresh || $fresh->stock_quantity < $quantity) {
            throw new OrderException(sprintf(
                'Sản phẩm "%s" không đủ hàng (còn %d).',
                $product->name,
                (int) ($fresh->stock_quantity ?? 0),
            ));
        }

        $fresh->decrement('stock_quantity', $quantity);
    }

    /**
     * Đổi trạng thái đơn, có kiểm tra bước chuyển hợp lệ.
     * Huỷ đơn thì HOÀN kho, nếu không huỷ vài đơn là kho hụt dần.
     *
     * @throws OrderException
     */
    public function changeStatus(Order $order, OrderStatus $target, ?string $reason = null): void
    {
        // Giữ lại để nhật ký nói được "từ đâu sang đâu". Đọc sau
        // transaction thì đã là trạng thái mới, và câu nhật ký thành
        // "Đã giao → Đã giao".
        $truocDo = $order->status;

        DB::transaction(function () use ($order, $target, $reason) {
            /*
             * KHOÁ ĐƠN RỒI ĐỌC LẠI, TRƯỚC KHI KIỂM.
             *
             * LỖI ĐUA TRƯỚC KHI SỬA: phép kiểm canTransitionTo() nằm
             * NGOÀI transaction và dựa vào trạng thái đã nạp từ trước.
             * Hai request huỷ cùng một đơn:
             *
             *   A đọc "pending"  -> pending->cancelled: được
             *   B đọc "pending"  -> pending->cancelled: được
             *   A ghi cancelled + HOÀN KHO
             *   B ghi cancelled + HOÀN KHO lần nữa
             *
             * Kho được cộng lại hai lần cho một đơn. Admin bấm hai lần
             * vì trang chậm là đủ để tái hiện — không cần kịch bản hiếm.
             *
             * lockForUpdate() bắt request thứ hai đợi; tới lượt nó thì
             * trạng thái đã là "cancelled" và phép kiểm chặn lại. CƠ SỞ
             * DỮ LIỆU là nơi phân xử, không phải bản sao trong bộ nhớ.
             */
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $locked) {
                throw new OrderException('Không tìm thấy đơn hàng.');
            }

            if (! $locked->status->canTransitionTo($target)) {
                throw new OrderException(sprintf(
                    'Không thể chuyển từ "%s" sang "%s".',
                    $locked->status->label(),
                    $target->label(),
                ));
            }

            // Đối tượng gọi vào có thể đã cũ — dùng bản vừa khoá.
            $order->setRawAttributes($locked->getAttributes(), sync: true);

            $restoreStock = $target === OrderStatus::Cancelled && $order->status->holdsStock();

            $order->status = $target;

            if ($target === OrderStatus::Confirmed) {
                $order->confirmed_at = now();
            }

            if ($target === OrderStatus::Completed) {
                $order->completed_at = now();
            }

            if ($target === OrderStatus::Cancelled) {
                $order->cancelled_at = now();
                $order->cancel_reason = $reason;
            }

            $order->save();

            /*
             * GHI MỐC — TRONG cùng transaction với việc đổi trạng thái.
             *
             * Đặt bên ngoài thì có lúc trạng thái đổi xong mà mốc không
             * ghi được, và dòng thời gian nhảy cóc: khách thấy "đã xác
             * nhận" rồi "đã giao", không có bước chuẩn bị nào ở giữa,
             * dù đơn có đi qua bước đó. Lịch sử thiếu mảnh thì tệ hơn
             * không có lịch sử, vì nó trông vẫn như đầy đủ.
             */
            OrderStatusEvent::create([
                'order_id' => $order->id,
                'status' => $target->value,
                'changed_by' => Auth::id(),
                'note' => $reason,
            ]);

            if ($restoreStock) {
                $this->restoreStock($order);
            }

            /*
             * Huỷ đơn thì trả lại lượt dùng mã giảm giá.
             *
             * Đặt ở ĐÂY chứ không ở controller, để admin huỷ và khách tự
             * huỷ đi qua cùng một đoạn mã — hai bản sao thì sớm muộn cũng
             * lệch nhau.
             *
             * Điều kiện giống hệt hoàn kho: chỉ trả lại khi đơn còn đang
             * "sống". Huỷ một đơn đã huỷ không được trừ tiếp lần nữa.
             */
            if ($restoreStock) {
                $this->coupons->release($order);
            }
        });

        /*
         * DỰNG LỊCH NHẮC CHĂM CÂY khi đơn đã giao.
         *
         * ĐẶT SAU transaction, cùng lý do với việc gửi thư: bên trong thì
         * lịch có thể đã ghi trong khi transaction sau đó bị cuộn lại vì
         * một lỗi khác — khách nhận nhắc tưới cho một đơn chưa từng giao.
         *
         * Nuốt lỗi: trạng thái đơn đã đổi thành công rồi. Không được để
         * việc dựng lịch nhắc — một dịch vụ phụ — làm hỏng thao tác chính
         * và bắt admin bấm lại (lần bấm thứ hai sẽ bị máy trạng thái từ
         * chối vì đơn đã ở trạng thái đó).
         */
        /*
         * NHẬT KÝ NỘI BỘ — sau transaction, vì nó không được quyền làm
         * hỏng việc chính. ActivityLogger đã tự nuốt lỗi, chỗ này chỉ
         * cần đặt đúng thứ tự.
         */
        $this->audit->logChange(
            'order.status_changed',
            $order,
            'Trạng thái đơn '.$order->order_number,
            $truocDo->label(),
            $target->label(),
            array_filter([
                'order_number' => $order->order_number,
                'ly_do' => $reason,
            ]),
        );

        if ($target === OrderStatus::Completed) {
            try {
                $this->care->scheduleForOrder($order);
            } catch (\Throwable $e) {
                Log::error('Không dựng được lịch nhắc chăm cây.', [
                    'order_number' => $order->order_number,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        /*
         * BÁO CHO KHÁCH — ĐẶT SAU transaction, KHÔNG ĐẶT BÊN TRONG.
         *
         * Bên trong transaction thì email có thể đã bay đi trong khi
         * transaction sau đó bị cuộn lại vì một lỗi khác — khách nhận thư
         * "đơn đã giao" cho một đơn thật ra vẫn đang chờ. Thư gửi rồi thì
         * không rút lại được, còn dữ liệu thì rollback được; nên thứ
         * không rút lại được phải đi sau cùng.
         *
         * Đặt ở ĐÂY chứ không ở controller vì cả admin đổi trạng thái lẫn
         * khách tự huỷ đều đi qua hàm này. Để ở controller là hai bản sao,
         * và bản thứ hai sẽ quên.
         *
         * OrderMailer tự bỏ qua các trạng thái không đáng báo và tự nuốt
         * lỗi gửi, nên chỗ này không cần rẽ nhánh hay bọc try.
         */
        $this->mailer->sendStatusUpdate($order);
    }

    private function restoreStock(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->product_variant_id) {
                ProductVariant::whereKey($item->product_variant_id)
                    ->where('track_inventory', true)
                    ->increment('stock_quantity', $item->quantity);

                continue;
            }

            if ($item->product_id) {
                Product::whereKey($item->product_id)
                    ->where('track_inventory', true)
                    ->increment('stock_quantity', $item->quantity);
            }
        }
    }

    /**
     * Đổi trạng thái thanh toán của một đơn.
     *
     * NƠI DUY NHẤT ghi cột `payment_status`. Trước đây markPaid() gán
     * thẳng, không kiểm gì — nên đánh dấu được cả một đơn ĐÃ HUỶ là đã
     * thanh toán. Đo được: đơn ở trạng thái `cancelled` vẫn nhận
     * `paid` mà không có lời cảnh báo nào.
     *
     * HAI PHÉP KIỂM, cả hai đều cần:
     *
     *   1. ĐÚNG ĐƯỜNG ĐI (PaymentStatus::canTransitionTo). Chặn nhảy từ
     *      "chưa trả" thẳng sang "đã hoàn tiền" — chưa nhận thì không có
     *      gì để hoàn.
     *
     *   2. ĐÚNG BỐI CẢNH ĐƠN HÀNG. Đơn đã huỷ thì không nhận thêm tiền.
     *      Nếu khách lỡ chuyển rồi thì việc cần làm là HOÀN TIỀN, không
     *      phải đánh dấu đã thu.
     *
     * @throws OrderException với câu nói được cho người dùng
     */
    public function setPaymentStatus(Order $order, PaymentStatus $target): void
    {
        $current = $order->payment_status;

        if ($current === $target) {
            throw new OrderException(sprintf(
                'Đơn này đã ở trạng thái "%s" rồi.',
                $target->label(),
            ));
        }

        if (! $current->canTransitionTo($target)) {
            throw new OrderException(sprintf(
                'Không chuyển được từ "%s" sang "%s".',
                $current->label(),
                $target->label(),
            ));
        }

        if ($target === PaymentStatus::Paid && $order->status === OrderStatus::Cancelled) {
            throw new OrderException(
                'Đơn đã huỷ nên không ghi nhận thanh toán được. '
                .'Nếu khách đã chuyển tiền, hãy hoàn tiền cho khách.'
            );
        }

        /*
         * HOÀN TIỀN CHỈ CHO ĐƠN ĐÃ HUỶ.
         *
         * LỖI TRƯỚC KHI SỬA: luật chuyển trạng thái chỉ nói "đã trả ->
         * hoàn tiền được", không hỏi đơn đang ở đâu. Nên một đơn ĐANG
         * GIAO vẫn đánh dấu "đã hoàn tiền" được — hệ thống ghi cửa hàng
         * đã trả tiền lại trong khi hàng vẫn đang trên đường tới khách.
         *
         * Hoàn tiền cho đơn chưa huỷ là nghiệp vụ TRẢ HÀNG, cần quy
         * trình riêng (nhận hàng về, kiểm tra, rồi mới hoàn). Chưa có
         * quy trình đó thì chặn, chứ không để ghi một trạng thái mà
         * không ai biết nó nghĩa là gì.
         */
        if ($target === PaymentStatus::Refunded && $order->status !== OrderStatus::Cancelled) {
            throw new OrderException(
                'Chỉ hoàn tiền cho đơn đã huỷ. '
                .'Đơn này đang ở trạng thái "'.$order->status->label().'" — '
                .'hãy huỷ đơn trước, rồi mới ghi nhận hoàn tiền.'
            );
        }

        /*
         * KHÔNG GỠ ĐÁNH DẤU THANH TOÁN CỦA ĐƠN ĐÃ GIAO XONG.
         *
         * Đường lui "đã trả -> chưa trả" sinh ra để sửa cú bấm nhầm, và
         * cú bấm nhầm thì phát hiện ngay. Một đơn đã giao xong mà quay
         * về "chưa thanh toán" tạo ra trạng thái không có nghĩa: hàng
         * đã ở nhà khách, tiền thì hệ thống bảo chưa nhận. Nếu thật sự
         * khách chưa trả thì đó là công nợ, cần chỗ ghi riêng.
         */
        if ($target === PaymentStatus::Unpaid && $order->status === OrderStatus::Completed) {
            throw new OrderException(
                'Đơn đã giao xong nên không gỡ đánh dấu thanh toán được. '
                .'Nếu ghi nhận sai, hãy liên hệ người quản trị hệ thống.'
            );
        }

        $order->payment_status = $target;
        $order->save();

        /*
         * VÀO NHẬT KÝ, KHÔNG CHỈ VÀO FILE LOG.
         *
         * Log::info ghi ra file trên máy chủ — hữu ích khi lập trình
         * viên đi tìm nguyên nhân, nhưng người quản lý cửa hàng không
         * mở được, và file log bị xoay vòng nên vài ngày là mất.
         *
         * Đây là thao tác về TIỀN. "Ai đánh dấu đơn này đã thanh toán?"
         * là câu hỏi sẽ được hỏi, và phải trả lời được sau nhiều tháng.
         *
         * KHÔNG ghi vào order_status_events: bảng đó là dòng thời gian
         * KHÁCH ĐỌC ĐƯỢC, còn việc cửa hàng đánh dấu đã nhận tiền là
         * chuyện nội bộ. Khách chỉ cần thấy trạng thái thanh toán hiện
         * tại, không cần thấy nhân viên nào bấm nút lúc mấy giờ.
         */
        $this->audit->logChange(
            'order.payment_changed',
            $order,
            'Thanh toán đơn '.$order->order_number,
            $current->label(),
            $target->label(),
            ['order_number' => $order->order_number],
        );

        Log::info('Đổi trạng thái thanh toán.', [
            'order' => $order->order_number,
            'tu' => $current->value,
            'sang' => $target->value,
        ]);
    }

    /**
     * Đơn này có đang nợ khách một khoản hoàn tiền không.
     *
     * ĐÃ HUỶ MÀ KHÁCH ĐÃ TRẢ TIỀN là tình huống cửa hàng phải xử lý
     * ngay, nhưng hệ thống không thể tự đánh dấu "đã hoàn tiền": nó
     * không biết ai đó có thật sự chuyển khoản trả lại hay chưa. Việc
     * của phần mềm là NHẮC, việc chuyển tiền là của con người.
     */
    public function owesRefund(Order $order): bool
    {
        return $order->status === OrderStatus::Cancelled
            && $order->payment_status === PaymentStatus::Paid;
    }
}
