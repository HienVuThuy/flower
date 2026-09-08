<?php

namespace App\Models;

use App\Enums\InvoiceBuyerType;
use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dữ liệu hoá đơn của một đơn hàng.
 * ============================================================
 * ⚠️ ĐÂY LÀ DỮ LIỆU HOÁ ĐƠN, KHÔNG PHẢI HOÁ ĐƠN ĐIỆN TỬ ĐÃ PHÁT HÀNH.
 * Xem chú thích dài ở migration create_invoices_table.
 *
 * ============================================================
 * BẪY TÊN CỘT: `subtotal` ở đây KHÁC `subtotal` của Order.
 *
 *     Order::subtotal    — tiền hàng theo giá niêm yết, ĐÃ GỒM VAT
 *                          (vì giá niêm yết của cửa hàng đã gồm VAT)
 *     Invoice::subtotal  — tiền hàng CHƯA VAT
 *
 * Hoá đơn bắt buộc phải ghi giá chưa thuế, thuế suất, tiền thuế và tổng
 * tiền thanh toán — nên con số ở đây phải là con số chưa thuế. Cùng tên
 * mà khác nghĩa là bẫy thật sự, và cách duy nhất để nó không cắn là ghi
 * ra ở cả hai nơi.
 *
 * Luôn đúng: subtotal + tax_total = grand_total = Order::grand_total.
 */
class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'invoice_number',
        'buyer_type',
        'buyer_name',
        'buyer_tax_code',
        'buyer_address',
        'buyer_email',
        'subtotal',
        'tax_total',
        'grand_total',
        'rate_breakdown',
        'status',
    ];

    /*
     * `issued_at` CỐ Ý KHÔNG nằm trong $fillable.
     *
     * Cùng lý do với `orders.done_at`: một mốc thời gian chỉ được đặt
     * bởi hành động thật đã xảy ra (phát hành hoá đơn qua nhà cung cấp),
     * không bao giờ bởi một mảng dữ liệu từ biểu mẫu. Hiện chưa có hành
     * động nào đặt nó — và đó đúng là sự thật hiện tại.
     */

    protected function casts(): array
    {
        return [
            'buyer_type' => InvoiceBuyerType::class,
            'status' => InvoiceStatus::class,
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'rate_breakdown' => 'array',
            'issued_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Bảng tách theo từng mức thuế suất, đã chuẩn hoá cho giao diện.
     *
     * Đọc từ cột đã chụp chứ KHÔNG tính lại từ đơn: dòng hàng của đơn
     * còn sửa được, con số trên chứng từ thì không.
     *
     * @return list<array{rate: ?string, net: string, tax: string}>
     */
    public function rateRows(): array
    {
        $rows = [];

        foreach ($this->rate_breakdown ?? [] as $row) {
            $rows[] = [
                'rate' => isset($row['rate']) ? (string) $row['rate'] : null,
                'net' => (string) ($row['net'] ?? '0.00'),
                'tax' => (string) ($row['tax'] ?? '0.00'),
            ];
        }

        return $rows;
    }
}
