<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Tách "lúc gửi thư" ra khỏi updated_at. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_verification_codes', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable()->after('expires_at');
        });

        DB::table('email_verification_codes')
            ->whereNull('sent_at')
            ->update(['sent_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('email_verification_codes', function (Blueprint $table) {
            $table->dropColumn('sent_at');
        });
    }
};
