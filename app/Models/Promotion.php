<?php

namespace App\Models;

use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Promotion extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'short_description',
        'description',
        'banner',
        'theme_key',
        'type',
        'discount_value',
        'starts_at',
        'ends_at',
        'daily_start_time',
        'daily_end_time',
        'weekdays',
        'status',
        'priority',
    ];

    protected $casts = [
        'type' => PromotionType::class,
        'status' => PromotionStatus::class,
        'discount_value' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'weekdays' => 'array',
        'priority' => 'integer',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promotion_product')
            ->withPivot(['discount_type', 'discount_value', 'promotional_price'])
            ->withTimestamps();
    }

    public function scopeActiveNow(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('status', PromotionStatus::Active)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    public function isRunning(): bool
    {
        if ($this->status !== PromotionStatus::Active) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        $diaPhuong = $now->copy()->setTimezone(\App\Services\Time\Gio::mui());

        if (! $this->isOnActiveWeekday($diaPhuong)) {
            return false;
        }

        return $this->isWithinDailyWindow($diaPhuong);
    }

    public function isOnActiveWeekday(?\Illuminate\Support\Carbon $now = null): bool
    {
        if (! is_array($this->weekdays) || $this->weekdays === []) {
            return true;
        }

        return in_array(($now ?? now())->isoWeekday(), array_map('intval', $this->weekdays), true);
    }

    public function isWithinDailyWindow(?\Illuminate\Support\Carbon $now = null): bool
    {
        $from = $this->daily_start_time;
        $to = $this->daily_end_time;

        if (! $from || ! $to) {
            return true;
        }

        $current = ($now ?? now())->format('H:i:s');

        return $from <= $to
            ? ($current >= $from && $current <= $to)
            : ($current >= $from || $current <= $to);
    }

    public function scheduleText(): ?string
    {
        $parts = [];

        if ($this->daily_start_time && $this->daily_end_time) {
            $parts[] = sprintf(
                '%s–%s hằng ngày',
                substr($this->daily_start_time, 0, 5),
                substr($this->daily_end_time, 0, 5),
            );
        }

        if (is_array($this->weekdays) && $this->weekdays !== []) {
            $names = [1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7', 7 => 'CN'];

            $parts[] = 'chỉ '.implode(', ', array_map(
                fn ($d) => $names[(int) $d] ?? (string) $d,
                $this->weekdays,
            ));
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    public function daysRemaining(): ?int
    {
        if (! $this->ends_at) {
            return null;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($this->ends_at->startOfDay(), false));
    }

    public function headlineDiscount(): ?string
    {
        $this->loadMissing('products');

        $percents = [];
        $amounts = [];

        foreach ($this->products as $product) {
            $pivot = $product->pivot;

            $type = $pivot?->discount_type
                ? PromotionType::tryFrom($pivot->discount_type)
                : $this->type;

            $value = $pivot?->discount_value ?? $this->discount_value;

            if ($type === null || $value === null) {
                continue;
            }

            match ($type) {
                PromotionType::Percent => $percents[] = (float) $value,
                PromotionType::FixedAmount => $amounts[] = (float) $value,
                default => null,
            };
        }

        if ($percents !== []) {
            return 'Giảm đến '.rtrim(rtrim(number_format(max($percents), 1, ',', '.'), '0'), ',').'%';
        }

        if ($amounts !== []) {
            return 'Giảm đến '.number_format(max($amounts), 0, ',', '.').'đ';
        }

        return null;
    }

    public function endsInText(): ?string
    {
        if (! $this->ends_at) {
            return null;
        }

        $now = now();

        if ($now->gt($this->ends_at)) {
            return null;
        }

        $hours = $now->diffInHours($this->ends_at);

        if ($hours < 1) {
            return 'sắp kết thúc';
        }

        if ($hours < 24) {
            return 'còn '.(int) $hours.' giờ';
        }

        $days = (int) $now->startOfDay()->diffInDays($this->ends_at->startOfDay(), false);

        return $days <= 1 ? 'hôm nay là ngày cuối' : "còn {$days} ngày";
    }

    public function effectiveStatus(): PromotionStatus
    {
        if ($this->status !== PromotionStatus::Active) {
            return $this->status;
        }

        $now = now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return PromotionStatus::Scheduled;
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return PromotionStatus::Ended;
        }

        return PromotionStatus::Active;
    }
}
