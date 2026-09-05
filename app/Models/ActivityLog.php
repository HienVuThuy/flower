<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một dòng nhật ký thao tác quản trị.
 * ============================================================
 * CHỈ ĐỌC sau khi đã ghi. Model này cố ý KHÔNG có $fillable cho phép
 * cập nhật hàng loạt, và không có nơi nào trong ứng dụng gọi update()
 * hay delete() lên nó — xem ActivityLogger.
 */
class ActivityLog extends Model
{
    /**
     * Bảng chỉ có created_at, không có updated_at.
     *
     * Để Eloquent tự quản cả hai thì mọi lần ghi đều lỗi vì thiếu cột.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'actor_name',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'properties',
        'ip_address',
    ];

    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Tên người thực hiện để hiển thị.
     *
     * Ưu tiên tài khoản còn sống (tên có thể đã được đổi), rồi mới tới
     * tên chụp lúc ghi, rồi mới tới "Hệ thống". Ba mức này ứng với ba
     * tình huống có thật, không phải phòng xa.
     */
    public function actorLabel(): string
    {
        if ($this->user) {
            return $this->user->name;
        }

        return $this->actor_name
            ? $this->actor_name.' (tài khoản đã xoá)'
            : 'Hệ thống';
    }

    /** Lọc theo nhóm việc: 'order', 'product'... (phần trước dấu chấm). */
    public function scopeOfGroup(Builder $query, string $group): Builder
    {
        return $query->where('action', 'like', $group.'.%');
    }
}
