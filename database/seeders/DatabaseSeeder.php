<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with complete sample data.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Khách hàng thử nghiệm',
                'password' => 'password',
            ]
        );

        $this->call([
            AdminUserSeeder::class,
            CatalogSeeder::class,
            ExtraCatalogSeeder::class,
            SupplyCatalogSeeder::class,
            PlantAdvisorSeeder::class,
            PlantTaxonomySeeder::class,
            PlantTraitSeeder::class,
            GiftOccasionSeeder::class,
            TaxClassSeeder::class,
            BlogSeeder::class,
            ReviewSampleSeeder::class,
            CommunityPostSampleSeeder::class,
            BoardingSampleSeeder::class,
            ChuongTrinhKhuyenMaiMauSeeder::class,
            DuLieuMauKhoSeeder::class,
        ]);
    }
}
