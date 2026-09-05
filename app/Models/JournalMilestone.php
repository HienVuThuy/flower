<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một mốc cần đạt trong sổ Mục tiêu.
 * ============================================================
 * ⚠️ DỮ LIỆU RIÊNG TƯ — xem QĐ-123.
 *
 * `done_at` thay cho một cột boolean: biết mốc đã xong thì hữu ích, biết
 * nó xong NGÀY NÀO thì hữu ích hơn — đó là thứ dựng được câu "mất ba
 * tuần để đi từ mốc này sang mốc kia". NULL nghĩa là chưa xong.
 */
class JournalMilestone extends Model
{
    protected $fillable = ['title', 'due_date', 'sort_order'];

    protected function casts(): array
    {
        return [
            'done_at' => 'datetime',
            'due_date' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function isDone(): bool
    {
        return $this->done_at !== null;
    }

    /**
     * Đã quá hạn mà chưa xong.
     *
     * Mốc ĐÃ XONG thì không bao giờ là quá hạn, kể cả khi xong muộn. Đánh
     * dấu đỏ một việc người ta đã làm xong là trách móc chuyện đã qua —
     * và nó đẩy sự chú ý ra khỏi những mốc còn đang dở.
     */
    public function isOverdue(): bool
    {
        return ! $this->isDone()
            && $this->due_date !== null
            && $this->due_date->isPast();
    }
}
