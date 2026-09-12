<?php

namespace App\Models;

use App\Enums\ExchangeReason;
use App\Enums\ExchangeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Một lần đổi hàng: khách trả món này, nhận món khác.
 *
 * `$fillable` CỐ Ý HẸP. `status`, `code`, ba con số tiền, `da_thu`,
 * `refund_id`, `created_by` và mọi mốc thời gian chỉ được ghi bởi
 * ExchangeService, đúng lúc việc tương ứng xảy ra — không có biểu mẫu
 * nào đặt được "hoàn tất" cho một phiếu mà hàng chưa về.
 */
class Exchange extends Model
{
    protected $fillable = [
        'order_id',
        'reason',
        'note',
    ];

    protected $attributes = [
        'status' => 'cho_nhan',
    ];

    protected function casts(): array
    {
        return [
            'reason' => ExchangeReason::class,
            'status' => ExchangeStatus::class,
            'tien_hang_tra' => 'decimal:2',
            'tien_hang_moi' => 'decimal:2',
            'phi_ship' => 'decimal:2',
            'chenh_lech' => 'decimal:2',
            'da_thu' => 'decimal:2',
            'nhan_hang_at' => 'datetime',
            'hoan_tat_at' => 'datetime',
            'huy_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExchangeItem::class);
    }

    public function hangTra(): HasMany
    {
        return $this->items()->where('chieu', ExchangeItem::TRA_VE);
    }

    public function hangMoi(): HasMany
    {
        return $this->items()->where('chieu', ExchangeItem::GUI_DI);
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Khách còn phải bù bao nhiêu (0 nếu cửa hàng nợ lại hoặc đã thu xong). */
    public function conPhaiThu(): string
    {
        if (bccomp((string) $this->chenh_lech, '0', 2) <= 0) {
            return '0.00';
        }

        $con = bcsub((string) $this->chenh_lech, (string) $this->da_thu, 2);

        return bccomp($con, '0', 2) > 0 ? $con : '0.00';
    }

    /** Cửa hàng có nợ lại tiền khách không. */
    public function cuaHangNoLai(): bool
    {
        return bccomp((string) $this->chenh_lech, '0', 2) < 0;
    }
}
