<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulk_order_inquiries', function (Blueprint $table) {
            $table->string('company_name', 200)->nullable()->after('contact_email');

            $table->date('event_date')->nullable()->after('occasion');
            $table->string('event_location', 255)->nullable()->after('event_date');

            $table->unsignedBigInteger('budget_min')->nullable()->after('quantity_estimate');
            $table->unsignedBigInteger('budget_max')->nullable()->after('budget_min');

            $table->string('color_preference', 150)->nullable()->after('budget_max');
            $table->string('flower_preference', 200)->nullable()->after('color_preference');

            $table->string('preferred_contact', 20)->nullable()->after('flower_preference');

            $table->index('event_date', 'bulk_inquiries_event_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('bulk_order_inquiries', function (Blueprint $table) {
            $table->dropIndex('bulk_inquiries_event_date_index');
            $table->dropColumn([
                'company_name', 'event_date', 'event_location',
                'budget_min', 'budget_max',
                'color_preference', 'flower_preference', 'preferred_contact',
            ]);
        });
    }
};
