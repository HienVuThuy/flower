<?php

namespace Database\Factories;

use App\Enums\CategoryKind;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = 'Danh mục '.fake()->unique()->numberBetween(1, 99999);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'kind' => CategoryKind::Plant,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /** Nhóm phụ kiện / vật tư — KHÔNG hiện ở các khối gợi ý cây cảnh. */
    public function supply(): static
    {
        return $this->state(fn () => ['kind' => CategoryKind::Supply]);
    }
}
