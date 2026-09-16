<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Quà tặng — hai nhánh trong MỘT mô hình. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('kind', 20);

            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedInteger('stock_quantity')->nullable();

            $table->decimal('value', 12, 2)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

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
            $table->boolean('is_gift')->default(false)->after('line_total');
            $table->foreignId('gift_campaign_id')->nullable()->after('is_gift')->constrained()->nullOnDelete();
            $table->foreignId('gift_item_id')->nullable()->after('gift_campaign_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gift_item_id');
            $table->dropConstrainedForeignId('gift_campaign_id');
            $table->dropColumn('is_gift');
        });

        Schema::dropIfExists('gift_campaigns');
        Schema::dropIfExists('gift_items');
    }
};
