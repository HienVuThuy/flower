<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(AdminUserSeeder::class);

        // Danh mục + sản phẩm mẫu thể hiện đặc thù hoa - cây cảnh.
        // An toàn khi chạy lại: bỏ qua slug đã tồn tại.
        $this->call(CatalogSeeder::class);

        /*
         * HÀNG PHỤ TRỢ — chậu, vật tư, đồ phủ gốc, đồ trang trí.
         *
         * Tách khỏi PlantAdvisorSeeder: một seeder tên "tư vấn chọn cây"
         * mà lại quyết định danh mục phụ kiện thì không ai tìm ra.
         */
        $this->call(SupplyCatalogSeeder::class);

        // Cây bổ sung + nhãn tư vấn (vị trí đặt, hợp mệnh, độ khó chăm).
        $this->call(PlantAdvisorSeeder::class);

        /*
         * PHÂN LOẠI SINH HỌC VÀ NHÃN SINH THÁI — CHẠY SAU CÙNG.
         *
         * Cả hai đều tra sản phẩm theo TÊN, nên mọi sản phẩm phải tồn tại
         * trước — kể cả những cây do PlantAdvisorSeeder thêm. Đảo thứ tự
         * thì chúng chạy xong mà không gắn được gì, và không có lỗi nào —
         * chỉ có một cửa hàng không lọc được theo tiêu chí nào cả.
         */
        $this->call(PlantTaxonomySeeder::class);
        $this->call(PlantTraitSeeder::class);
    }
}
