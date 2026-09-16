<?php

namespace App\Models;

use App\Enums\InvoiceBuyerType;
use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dữ liệu hoá đơn của một đơn hàng.
 * ⚠️ ĐÂY LÀ DỮ LIỆU HOÁ ĐƠN, KHÔNG PHẢI HOÁ ĐƠN ĐIỆN TỬ ĐÃ PHÁT HÀNH.
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

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
