<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Gộp "Quà theo chương trình" vào Chương trình khuyến mại (hình thức Tặng quà) và thêm cờ sản phẩm nổi bật. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->foreignId('gift_item_id')->nullable()->after('discount_value')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('gift_quantity')->default(1)->after('gift_item_id');
            $table->decimal('min_order_amount', 12, 2)->nullable()->after('gift_quantity');
            $table->foreignId('min_member_tier_id')->nullable()->after('min_order_amount')->constrained('member_tiers')->nullOnDelete();
            $table->boolean('first_order_only')->default(false)->after('min_member_tier_id');
            $table->unsignedSmallInteger('per_user_limit')->nullable()->after('first_order_only');
            $table->unsignedInteger('total_limit')->nullable()->after('per_user_limit');
            $table->unsignedInteger('used_count')->default(0)->after('total_limit');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('gift_promotion_id')->nullable()->after('is_gift')->constrained('promotions')->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('status');
        });

        $bayGio = now();

        foreach (DB::table('gift_campaigns')->orderBy('id')->get() as $cu) {
            $slug = Str::slug($cu->name) ?: 'qua-tang';
            $goc = $slug;
            $i = 2;

            while (DB::table('promotions')->where('slug', $slug)->exists()) {
                $slug = $goc . '-' . $i++;
            }

            $moi = DB::table('promotions')->insertGetId([
                'name' => $cu->name,
                'slug' => $slug,
                'type' => 'tang_qua',
                'discount_value' => 0,
                'gift_item_id' => $cu->gift_item_id,
                'gift_quantity' => $cu->gift_quantity,
                'min_order_amount' => $cu->min_order_amount,
                'min_member_tier_id' => $cu->min_member_tier_id,
                'first_order_only' => $cu->first_order_only,
                'per_user_limit' => $cu->per_user_limit,
                'total_limit' => $cu->total_limit,
                'used_count' => $cu->used_count,
                'starts_at' => $cu->starts_at,
                'ends_at' => $cu->ends_at,
                'status' => $cu->status,
                'priority' => 0,
                'created_at' => $cu->created_at ?? $bayGio,
                'updated_at' => $bayGio,
            ]);

            if ($cu->trigger_product_id !== null) {
                DB::table('promotion_product')->insert([
                    'promotion_id' => $moi,
                    'product_id' => $cu->trigger_product_id,
                    'created_at' => $bayGio,
                    'updated_at' => $bayGio,
                ]);
            }

            DB::table('order_items')->where('gift_campaign_id', $cu->id)->update(['gift_promotion_id' => $moi]);
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gift_campaign_id');
        });

        Schema::dropIfExists('gift_campaigns');
    }

    public function down(): void
    {
        Schema::create('gift_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('kind', 20);
            $table->foreignId('gift_item_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('gift_quantity')->default(1);
            $table->foreignId('trigger_product_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->unsignedSmallInteger('trigger_min_quantity')->default(1);
            $table->decimal('min_order_amount', 12, 2)->nullable();
            $table->foreignId('min_member_tier_id')->nullable()->constrained('member_tiers')->nullOnDelete();
            $table->boolean('first_order_only')->default(false);
            $table->unsignedSmallInteger('per_user_limit')->nullable();
            $table->unsignedInteger('total_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->index(['status', 'kind']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('gift_campaign_id')->nullable()->after('is_gift')->constrained()->nullOnDelete();
            $table->dropConstrainedForeignId('gift_promotion_id');
        });

        DB::table('promotions')->where('type', 'tang_qua')->delete();

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_featured');
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('min_member_tier_id');
            $table->dropConstrainedForeignId('gift_item_id');
            $table->dropColumn(['gift_quantity', 'min_order_amount', 'first_order_only', 'per_user_limit', 'total_limit', 'used_count']);
        });
    }
};
