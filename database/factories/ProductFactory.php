<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Enums\SellingForm;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = 'Sản phẩm '.fake()->unique()->numberBetween(1, 99999);

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'product_code' => strtoupper(Str::random(8)),
            'short_description' => 'Mô tả ngắn để kiểm thử.',
            'description' => 'Mô tả dài để kiểm thử.',
            'product_type' => ProductType::Plant,
            'selling_form' => SellingForm::Pot,

            // Giá tròn để phép cộng trong bài kiểm tra đọc ra là hiểu ngay,
            // không phải bấm máy tính mới biết con số kỳ vọng ở đâu ra.
            'base_price' => '100000.00',
            'status' => 'active',
            'track_inventory' => true,
            'stock_quantity' => 100,
        ];
    }

    public function price(string $amount): static
    {
        return $this->state(fn () => ['base_price' => $amount]);
    }

    /** Hàng làm theo đơn: không quản lý tồn kho (khác hẳn "hết hàng"). */
    public function madeToOrder(): static
    {
        return $this->state(fn () => [
            'track_inventory' => false,
            'stock_quantity' => 0,
        ]);
    }

    public function stock(int $quantity): static
    {
        return $this->state(fn () => [
            'track_inventory' => true,
            'stock_quantity' => $quantity,
        ]);
    }
}
