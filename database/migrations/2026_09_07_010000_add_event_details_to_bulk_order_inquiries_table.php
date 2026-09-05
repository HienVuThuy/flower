<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm chi tiết sự kiện cho phiếu yêu cầu số lượng lớn.
     * ============================================================
     * VẤN ĐỀ CỦA BIỂU MẪU CŨ: chỉ có tên, số điện thoại, dịp, số lượng
     * ước tính và một ô nội dung tự do. Nhân viên nhận phiếu xong vẫn
     * phải gọi lại để hỏi đủ thứ cơ bản — ngày nào, giao ở đâu, ngân sách
     * bao nhiêu, thích màu gì. Mỗi cuộc gọi đó là một lần khách phải nhớ
     * lại và một lần chậm báo giá.
     *
     * TẤT CẢ ĐỀU KHÔNG BẮT BUỘC, và đó là điều kiện tiên quyết.
     * Biểu mẫu hỏi nhiều mà bắt buộc hết thì khách bỏ ngang — mà một
     * phiếu thiếu thông tin vẫn tốt hơn hẳn không có phiếu nào. Khách
     * điền được bao nhiêu thì cửa hàng đỡ phải hỏi bấy nhiêu.
     *
     * VÌ SAO KHÔNG NHÉT HẾT VÀO Ô `message` TỰ DO:
     * Chữ tự do thì không lọc được, không thống kê được, và mỗi khách
     * viết một kiểu. Có cột `event_date` riêng thì admin sắp phiếu theo
     * ngày sự kiện — thứ tự duy nhất có nghĩa với công việc này, vì
     * phiếu cho đám cưới tuần sau gấp hơn phiếu cho hội nghị tháng sau.
     *
     * `budget_*` LÀ MỘT KHOẢNG, không phải một con số: khách sự kiện
     * hiếm khi biết chính xác, nhưng gần như luôn biết "khoảng 5 đến 10
     * triệu". Hỏi một con số duy nhất là hỏi thứ họ không có.
     */
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

            /*
             * Cách khách muốn được liên hệ lại.
             *
             * Nghe nhỏ nhưng là chỗ hỏng thật: nhân viên gọi điện cho một
             * khách đang họp cả ngày thì không ai nghe máy, phiếu treo ba
             * hôm. Hỏi một câu ở đây tiết kiệm được đúng ba hôm đó.
             */
            $table->string('preferred_contact', 20)->nullable()->after('flower_preference');

            /*
             * Chỉ mục theo ngày sự kiện — cách sắp xếp DUY NHẤT có nghĩa
             * với danh sách phiếu: việc gấp là việc có ngày gần nhất.
             */
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
