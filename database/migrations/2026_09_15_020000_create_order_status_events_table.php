<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Dòng thời gian của đơn hàng — đơn đã đi qua những bước nào. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->string('status', 30);

            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('note', 500)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
        });

        $donCu = DB::table('orders')->select(
            'id', 'status', 'created_at', 'confirmed_at', 'completed_at',
            'cancelled_at', 'cancel_reason',
        )->get();

        $moc = [];

        foreach ($donCu as $don) {
            $moc[] = [
                'order_id' => $don->id,
                'status' => 'pending',
                'changed_by' => null,
                'note' => null,
                'created_at' => $don->created_at,
            ];

            foreach ([
                ['confirmed', $don->confirmed_at, null],
                ['completed', $don->completed_at, null],
                ['cancelled', $don->cancelled_at, $don->cancel_reason],
            ] as [$trangThai, $luc, $ghiChu]) {
                if ($luc) {
                    $moc[] = [
                        'order_id' => $don->id,
                        'status' => $trangThai,
                        'changed_by' => null,
                        'note' => $ghiChu,
                        'created_at' => $luc,
                    ];
                }
            }
        }

        foreach (array_chunk($moc, 200) as $lo) {
            DB::table('order_status_events')->insert($lo);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_events');
    }
};
