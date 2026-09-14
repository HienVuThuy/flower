<?php

namespace App\Models;

use App\Enums\PointReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một dòng trong sổ điểm của khách.
 * ============================================================
 * SỔ, KHÔNG PHẢI CON SỐ. Số dư là TỔNG các dòng — không có cột "điểm hiện
 * tại" nào để sửa tay. Khách hỏi "sao tôi còn 150 điểm" thì câu trả lời
 * là danh sách dòng cộng lại ra 150, không phải "hệ thống ghi thế".
 *
 * KHÔNG CÓ $fillable: mọi dòng do PointLedger ghi. Không biểu mẫu nào
 * được đổ dữ liệu thẳng vào đây — một ô `amount` lọt qua là tự in điểm.
 */
class PointTransaction extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'reason' => PointReason::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
