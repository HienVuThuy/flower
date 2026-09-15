<?php

namespace App\Models;

use App\Enums\InstallmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kế hoạch trả góp của một đơn. Chỉ InstallmentService ghi — xem đó.
 */
class InstallmentPlan extends Model
{
    // Không có biểu mẫu nào ghi thẳng: mọi thay đổi đi qua dịch vụ bằng forceFill.
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'down_payment_percent' => 'integer',
            'period_count' => 'integer',
            'period_days' => 'integer',
            'grace_days' => 'integer',
            'credit_score' => 'integer',
            'status' => InstallmentStatus::class,
            'completed_at' => 'datetime',
            'defaulted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InstallmentPayment::class)->orderBy('sequence');
    }

    /** Tổng đã trả (chuỗi bcmath), đọc từ các kỳ đã nạp. */
    public function daTra(): string
    {
        return $this->payments
            ->whereNotNull('paid_at')
            ->reduce(fn (string $tong, InstallmentPayment $k) => bcadd($tong, (string) $k->amount, 2), '0.00');
    }

    public function conLai(): string
    {
        return bcsub((string) $this->total_amount, $this->daTra(), 2);
    }

    /** Kỳ chưa trả sớm nhất — kỳ khách phải trả tiếp. */
    public function kyKeTiep(): ?InstallmentPayment
    {
        return $this->payments->whereNull('paid_at')->sortBy('sequence')->first();
    }

    public function dangTra(): bool
    {
        return $this->status === InstallmentStatus::DangTra;
    }
}
