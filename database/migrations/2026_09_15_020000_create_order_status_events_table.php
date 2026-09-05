<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dòng thời gian của đơn hàng — đơn đã đi qua những bước nào.
 * ============================================================
 * NGHIỆP VỤ CỤ THỂ: bảng `orders` chỉ giữ trạng thái HIỆN TẠI cộng vài
 * mốc rời rạc (confirmed_at, completed_at, cancelled_at). Nhìn vào đó
 * không trả lời được những câu hỏi xảy ra hằng ngày:
 *
 *   - "Đơn này nằm ở khâu chuẩn bị bao lâu?"  (không có preparing_at)
 *   - "Ai chuyển nó sang Đang giao?"          (không ghi người làm)
 *   - "Khách gọi hỏi sao chưa nhận được hàng" (không có mốc nào để đối)
 *
 * Và với KHÁCH: trang đơn hàng hiện đúng một trạng thái. Mọi nơi bán
 * hàng đều cho khách xem đơn đã đi tới đâu, vì đó là câu hỏi họ hỏi
 * nhiều nhất — không có thì họ gọi điện, và cửa hàng trả lời bằng cách
 * mở đúng cái màn hình cũng chẳng có thông tin gì hơn.
 *
 * KHÁC GÌ `activity_logs`: nhật ký kia là của NỘI BỘ, ghi mọi thao tác
 * ghi dữ liệu, khách không bao giờ thấy. Bảng này là LỊCH SỬ CỦA ĐƠN,
 * hiện cho chính khách hàng xem. Gộp làm một thì hoặc là khách đọc được
 * ghi chú nội bộ, hoặc là nhật ký nội bộ phải tự kiểm duyệt — cả hai
 * đều tệ hơn hai bảng riêng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_events', function (Blueprint $table) {
            $table->id();

            // cascade ở ĐÂY là đúng: dòng thời gian không có nghĩa gì
            // nếu tách khỏi đơn. Xoá đơn thì lịch sử của nó đi theo.
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->string('status', 30);

            /*
             * NGƯỜI THỰC HIỆN — nullable vì có ba nguồn khác nhau:
             * nhân viên đổi trạng thái, chính khách tự huỷ, và hệ thống
             * tự ghi bước đầu tiên lúc đặt hàng.
             */
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
             * GHI CHÚ KHÁCH ĐỌC ĐƯỢC — lý do huỷ chẳng hạn.
             *
             * KHÔNG dùng cho ghi chú nội bộ; đơn đã có cột riêng cho
             * việc đó (orders.admin_note). Trộn hai thứ vào một cột là
             * cách chắc chắn để một hôm nào đó khách đọc được câu bình
             * luận nhân viên viết cho nhau.
             */
            $table->string('note', 500)->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Luôn đọc theo đơn và theo thứ tự thời gian.
            $table->index(['order_id', 'created_at']);
        });

        /*
         * DỰNG LẠI DÒNG THỜI GIAN CHO ĐƠN CŨ, từ những mốc đã có sẵn.
         *
         * Không làm thì mọi đơn trước hôm nay hiện ra một dòng thời gian
         * TRỐNG RỖNG, và khách sẽ hiểu là đơn của họ chưa được xử lý gì.
         * Một tính năng mới không được làm dữ liệu cũ trông như bị hỏng.
         *
         * Chỉ dựng từ mốc CÓ THẬT trong bảng orders — không đoán thêm
         * bước nào. Đơn cũ vì thế có dòng thời gian thưa hơn đơn mới, và
         * đó đúng là những gì hệ thống thật sự biết.
         */
        $donCu = DB::table('orders')->select(
            'id', 'status', 'created_at', 'confirmed_at', 'completed_at',
            'cancelled_at', 'cancel_reason',
        )->get();

        $moc = [];

        foreach ($donCu as $don) {
            // Mọi đơn đều bắt đầu ở "chờ xác nhận" vào lúc được tạo.
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
