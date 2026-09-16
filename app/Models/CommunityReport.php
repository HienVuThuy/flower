<?php

namespace App\Models;

use App\Enums\CommunityReportReason;
use App\Enums\CommunityReportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Báo cáo một bài hoặc bình luận Góc cây. Người báo và trạng thái do hệ thống
 * đặt — không nhận từ biểu mẫu.
 */
class CommunityReport extends Model
{
    public const BAI = 'post';

    public const BINH_LUAN = 'comment';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'reason' => CommunityReportReason::class,
            'status' => CommunityReportStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', CommunityReportStatus::ChoXuLy->value);
    }

    /** Bài hoặc bình luận bị báo cáo (null nếu đã bị xoá). */
    public function target(): CommunityPost|CommunityComment|null
    {
        return $this->target_type === self::BAI
            ? CommunityPost::find($this->target_id)
            : CommunityComment::find($this->target_id);
    }
}
