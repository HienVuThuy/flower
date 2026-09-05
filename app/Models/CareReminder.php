<?php

namespace App\Models;

use App\Enums\CareTask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một lịch nhắc chăm sóc: khách X, cây Y, việc Z.
 *
 * Xem migration 2026_09_04_010000 để biết vì sao lịch sinh ra lúc đơn
 * "đã giao" chứ không phải lúc đặt hàng.
 */
class CareReminder extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'kind',
        'interval_days',
        'next_due_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'kind' => CareTask::class,
            'interval_days' => 'integer',
            'next_due_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Đang bật và đã tới hạn. Dùng bởi lệnh `care:remind`. */
    public function scopeDue(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('next_due_at', '<=', now());
    }

    /**
     * Còn bao nhiêu ngày nữa tới hạn. Âm nghĩa là đã quá hạn.
     *
     * Tính theo NGÀY LỊCH, không theo số giờ chia 24: hạn lúc 23h hôm nay
     * và bây giờ là 1h sáng mai thì phải là "quá hạn 1 ngày", chứ không
     * phải "còn 0 ngày" — người ta nghĩ theo ngày, không theo giờ.
     */
    public function daysUntilDue(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->next_due_at->startOfDay(), false);
    }

    public function isOverdue(): bool
    {
        return $this->daysUntilDue() < 0;
    }

    /**
     * Dời sang kỳ tiếp theo sau khi đã gửi thư.
     *
     * Mốc tính là HÔM NAY, không phải next_due_at cũ.
     *
     * Vì sao quan trọng: nếu máy chủ nghỉ một tuần rồi chạy lại, cộng dồn
     * từ mốc cũ sẽ cho ra một ngày vẫn nằm trong quá khứ — và lệnh sẽ gửi
     * thư lại ở lần chạy kế tiếp, rồi lại lần nữa, cho tới khi đuổi kịp
     * hiện tại. Khách nhận một loạt thư nhắc tưới cùng một cây.
     */
    public function advance(): void
    {
        $this->forceFill([
            'last_sent_at' => now(),
            'next_due_at' => now()->addDays($this->interval_days),
        ])->save();
    }
}
