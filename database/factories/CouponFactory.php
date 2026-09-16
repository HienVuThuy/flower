<?php

namespace Database\Factories;

use App\Enums\CouponType;
use App\Enums\PromotionStatus;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(10)),
            'name' => 'Mã kiểm thử',
            'type' => CouponType::FixedAmount,
            'value' => '50000.00',
            'status' => PromotionStatus::Active,
            'is_public' => true,

            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
        ];
    }

    public function fixed(string $amount): static
    {
        return $this->state(fn () => [
            'type' => CouponType::FixedAmount,
            'value' => $amount,
        ]);
    }

    public function percent(string $percent, ?string $maxDiscount = null): static
    {
        return $this->state(fn () => [
            'type' => CouponType::Percent,
            'value' => $percent,
            'max_discount_amount' => $maxDiscount,
        ]);
    }

    public function minOrder(string $amount): static
    {
        return $this->state(fn () => ['min_order_amount' => $amount]);
    }

    public function private(): static
    {
        return $this->state(fn () => ['is_public' => false]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subDay(),
        ]);
    }
}
