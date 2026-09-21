<?php

namespace App\Models;

use App\Enums\CareDifficulty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/** Một dòng bảng giá chăm cây hộ — admin tự đặt. */
class BoardingRate extends Model
{
    public const CACHE_MO = 'boarding.dang_nhan';

    protected $fillable = ['name', 'description', 'care_difficulty', 'monthly_price', 'yearly_price', 'needs_quote', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'care_difficulty' => CareDifficulty::class,
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'is_active' => 'boolean',
            'needs_quote' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_MO));
        static::deleted(fn () => Cache::forget(self::CACHE_MO));
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(BoardingBooking::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('sort_order')->orderBy('monthly_price');
    }

    /** Giá năm; admin để trống thì bằng 12 tháng. */
    public function giaNam(): string
    {
        return $this->yearly_price !== null
            ? (string) $this->yearly_price
            : bcmul((string) $this->monthly_price, '12', 2);
    }

    /** Cửa hàng có đang nhận gửi không — ẩn mọi lối vào khi chưa có bảng giá. */
    public static function dangNhan(): bool
    {
        return Cache::remember(self::CACHE_MO, 600, fn () => self::query()->where('is_active', true)->exists());
    }
}
