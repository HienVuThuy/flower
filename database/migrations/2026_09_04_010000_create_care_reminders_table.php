<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_reminders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('kind', 16);

            $table->unsignedSmallInteger('interval_days');

            $table->timestamp('next_due_at');
            $table->timestamp('last_sent_at')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['user_id', 'product_id', 'kind'], 'care_reminders_unique');

            $table->index(['is_active', 'next_due_at'], 'care_reminders_due');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_care_reminders')->default(true)->after('notify_order_updates');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_reminders');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notify_care_reminders');
        });
    }
};
