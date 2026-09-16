<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một dòng nhật ký thao tác quản trị. */
class ActivityLog extends Model
{
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

    public function actorLabel(): string
    {
        if ($this->user) {
            return $this->user->name;
        }

        return $this->actor_name
            ? $this->actor_name.' (tài khoản đã xoá)'
            : 'Hệ thống';
    }

    public function scopeOfGroup(Builder $query, string $group): Builder
    {
        return $query->where('action', 'like', $group.'.%');
    }
}
