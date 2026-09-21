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

        $this->call(CatalogSeeder::class);

        $this->call(SupplyCatalogSeeder::class);

        $this->call(PlantAdvisorSeeder::class);

        $this->call(PlantTaxonomySeeder::class);
        $this->call(PlantTraitSeeder::class);
        $this->call(GiftOccasionSeeder::class);

        $this->call(TaxClassSeeder::class);
    }
}
