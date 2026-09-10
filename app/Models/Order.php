<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_number',
        /*
         * Khoá chống đặt trùng. Nằm trong $fillable vì OrderService gán
         * nó lúc Order::create() — nhưng KHÔNG BAO GIỜ nhận từ dữ liệu
         * người dùng gửi lên: giá trị do CheckoutGuard sinh ra bằng
         * random_bytes và giữ trong phiên máy chủ.
         */
        'idempotency_key',
        'user_id',
        'recipient_name',
        'recipient_phone',
        'recipient_email',
        'shipping_address',
        'shipping_ward',
        'shipping_district',
        'shipping_province',
        'delivery_date',
        'delivery_note',
        'payment_method',
        'subtotal',
        'discount_total',
        'shipping_fee',
        // Bốn trường cho Giao Hàng Nhanh — xem migration
        // 2026_09_16_010000_add_ghn_shipping_to_orders_table.
        'ghn_order_code',
        'ghn_total_fee',
        'to_district_id',
        'to_ward_code',
        'shipping_status',
        'coupon_id',
        'coupon_code',
        'coupon_discount',
        'grand_total',
        'tax_rate',
        'tax_amount',
        'shipping_tax_amount',
    ];

    /*
     * status / payment_status KHÔNG nằm trong $fillable.
     * Đổi trạng thái đơn là hành vi nghiệp vụ có quy tắc (xem
     * OrderStatus::canTransitionTo và OrderService), không phải một
     * trường form bình thường — để ngoài $fillable thì một request
     * bịa thêm `status=completed` cũng không đổi được gì.
     */

    /*
     * Giá trị mặc định khai ở ĐÂY chứ không chỉ ở migration.
     * Cột status/payment_status nằm ngoài $fillable (không cho request
     * đặt), nên nếu chỉ dựa vào default của MySQL thì model vừa tạo
     * xong sẽ có status = null trong bộ nhớ — mọi lời gọi
     * $order->status->... ngay sau khi tạo đều nổ.
     */
    protected $attributes = [
        'status' => 'pending',
        'payment_status' => 'unpaid',
    ];

    protected function casts(): array
    {
        return [
            'risk_score' => 'integer',
            'risk_flags' => 'array',
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'delivery_date' => 'date',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'coupon_discount' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'grand_total' => 'decimal:2',

            /*
             * CAST CHO CỘT THUẾ — cùng lý do với các cột tiền khác.
             *
             * LỖI ĐÃ SỬA: không khai cast thì MySQL trả về chuỗi
             * ('0.08000') còn SQLite trả về số (0.08). Bài kiểm thử chạy
             * trên SQLite nên nó xanh, còn máy chủ thật chạy MySQL —
             * kiểu dữ liệu khác nhau giữa nơi kiểm và nơi chạy là cách
             * chắc chắn nhất để một lỗi so sánh nghiêm ngặt lọt qua.
             *
             * `decimal:5` cho thuế suất: đủ cho những mức lẻ như 8,25%.
             */
            'tax_rate' => 'decimal:5',
            'tax_amount' => 'decimal:2',
            'shipping_tax_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mã đã dùng, nếu còn tồn tại.
     * Chỉ để dẫn link ở admin — tên mã và số tiền giảm đọc từ
     * coupon_code/coupon_discount của chính đơn (bản chụp).
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /**
     * Dữ liệu hoá đơn, nếu khách có yêu cầu xuất.
     *
     * PHẦN LỚN ĐƠN KHÔNG CÓ — khách lẻ mua bó hoa thường không lấy hoá
     * đơn. Vì thế nó là quan hệ rời chứ không phải mấy cột thêm vào
     * `orders`: xem chú thích ở migration create_invoices_table.
     */
    /** Từng lượt thử thanh toán, mới nhất trước. */
    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class)->latest('id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * Tổng thuế các dòng hàng, KHÔNG gồm thuế phí vận chuyển.
     *
     * Đọc từ bản chụp ở `order_items`, không tính lại. Đẳng thức đối
     * soát: itemsTax() + shipping_tax_amount = tax_amount.
     */
    public function itemsTax(): ?string
    {
        if ($this->tax_amount === null) {
            return null;
        }

        return bcsub((string) $this->tax_amount, (string) ($this->shipping_tax_amount ?? '0.00'), 2);
    }

    /**
     * Tiền hàng + phí CHƯA thuế.
     *
     * Giá niêm yết đã gồm VAT nên `grand_total` là số ĐÃ có thuế; con số
     * hoá đơn cần là số chưa thuế, và nó chỉ là một phép trừ.
     */
    public function netTotal(): ?string
    {
        if ($this->tax_amount === null) {
            return null;
        }

        return bcsub((string) $this->grand_total, (string) $this->tax_amount, 2);
    }

    /**
     * Tách thuế theo từng MỨC THUẾ SUẤT — dạng hoá đơn GTGT phải ghi.
     *
     * Dựng từ BẢN CHỤP ở `order_items` chứ không hỏi lại nhóm thuế hiện
     * tại của sản phẩm: phân loại có thể đã đổi từ lâu, và chứng từ thì
     * phải giữ nguyên mức đã áp lúc bán.
     *
     * Phí vận chuyển gộp vào đúng mức của nó — hoá đơn tách theo thuế
     * suất, không tách theo "hàng" và "phí".
     *
     * @return list<array{rate: ?string, net: string, tax: string}>
     */
    public function taxByRate(): array
    {
        if ($this->tax_amount === null) {
            return [];
        }

        $nhom = [];

        $gop = function (?string $rate, string $net, string $tax) use (&$nhom): void {
            // Mảng PHP không nhận khoá null; '' là nhóm "không chịu VAT".
            $khoa = $rate === null ? '' : (string) (float) $rate;

            $nhom[$khoa] ??= ['rate' => $rate, 'net' => '0.00', 'tax' => '0.00'];
            $nhom[$khoa]['net'] = bcadd($nhom[$khoa]['net'], $net, 2);
            $nhom[$khoa]['tax'] = bcadd($nhom[$khoa]['tax'], $tax, 2);
        };

        foreach ($this->items as $item) {
            $gop(
                $item->tax_rate === null ? null : (string) $item->tax_rate,
                $item->netTotal(),
                (string) ($item->tax_amount ?? '0.00'),
            );
        }

        $phi = (string) $this->shipping_fee;

        if (bccomp($phi, '0', 2) > 0) {
            $thuePhi = (string) ($this->shipping_tax_amount ?? '0.00');
            $gop(
                $this->tax_rate === null ? null : (string) $this->tax_rate,
                bcsub($phi, $thuePhi, 2),
                $thuePhi,
            );
        }

        $rows = array_values($nhom);

        // Mức cao xuống thấp, phần không chịu thuế xuống cuối — bảng
        // phải có thứ tự cố định, không theo thứ tự sản phẩm trong giỏ.
        usort($rows, function (array $a, array $b): int {
            if ($a['rate'] === null) {
                return $b['rate'] === null ? 0 : 1;
            }

            if ($b['rate'] === null) {
                return -1;
            }

            return bccomp($b['rate'], $a['rate'], 6);
        });

        return $rows;
    }

    /** Đơn chưa kết thúc — hàng chờ xử lý ở admin. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            OrderStatus::Completed->value,
            OrderStatus::Cancelled->value,
        ]);
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function totalQuantity(): int
    {
        return (int) $this->items->sum('quantity');
    }

    /** Máy trạng thái có cho phép huỷ hay không — dùng cho admin. */
    public function isCancellable(): bool
    {
        return $this->status->canTransitionTo(OrderStatus::Cancelled);
    }

    /**
     * Khách CÓ ĐƯỢC TỰ HUỶ đơn này không.
     *
     * Hẹp hơn isCancellable() một cách cố ý. Máy trạng thái cho phép huỷ
     * tới tận "Đang giao" vì admin cần quyền đó khi khách gọi điện. Nhưng
     * khách bấm nút thì khác:
     *
     *  - "Đang chuẩn bị": bó hoa đang được cắt và gói. Hoa đã cắt không
     *    ghép lại được, cửa hàng mất trắng nguyên liệu.
     *  - "Đang giao": hàng đang trên đường, shipper đã xuất phát.
     *
     * Từ hai trạng thái đó trở đi khách phải gọi cửa hàng để thoả thuận —
     * đó là chuyện thương lượng giữa người với người, không phải một nút
     * bấm. Quyết định này ghi ở docs/DOMAIN-DECISIONS.md (QĐ-05).
     */
    public function isCancellableByCustomer(): bool
    {
        return in_array($this->status, [
            OrderStatus::Pending,
            OrderStatus::Confirmed,
        ], strict: true);
    }

    /** Route model binding dùng mã đơn thay vì id tự tăng. */
    /**
     * Dòng thời gian của đơn, cũ trước mới sau.
     *
     * Sắp xếp ngay trong quan hệ: mọi nơi hiển thị đều cần đúng thứ tự
     * đó, và để từng nơi tự sắp là mở đường cho một trang nào đó hiện
     * lịch sử ngược — thứ trông như đơn đang đi lùi.
     */
    public function statusEvents(): HasMany
    {
        return $this->hasMany(OrderStatusEvent::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    /**
     * Đơn này có đáng để nhân viên xem lại trước khi làm không.
     *
     * NƠI DUY NHẤT so điểm với ngưỡng. Nếu mỗi Blade tự viết
     * `$order->risk_score >= 40` thì đổi ngưỡng trong config sẽ chỉ có
     * tác dụng ở một nửa số màn hình.
     */
    public function needsRiskReview(): bool
    {
        return $this->risk_score >= (int) config('risk.review_from', 40);
    }

    /**
     * Các dấu hiệu rủi ro, đã chuẩn hoá.
     *
     * @return list<array{code: string, label: string, points: int}>
     */
    public function riskFlags(): array
    {
        return is_array($this->risk_flags) ? $this->risk_flags : [];
    }
}
