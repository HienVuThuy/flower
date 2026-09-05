<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chỉnh chỉ mục cho đúng cách dữ liệu thật được truy vấn.
     * ============================================================
     * HAI VIỆC NGƯỢC NHAU, cùng một lý do: chỉ mục phải khớp câu truy vấn
     * có thật, không phải đặt cho "đủ bộ".
     *
     * 1. THÊM chỉ mục ghép (event_type, created_at) cho user_events.
     *    Trang Phân tích luôn lọc CẢ HAI cột cùng lúc ("sự kiện loại X
     *    trong 30 ngày qua"). Chỉ mục đơn trên event_type buộc cơ sở dữ
     *    liệu lọc thô theo loại rồi quét từng dòng để so ngày. Hiện mới
     *    ~200 dòng nên không thấy gì, nhưng nhật ký hành vi là bảng lớn
     *    nhanh nhất trong mọi hệ thương mại điện tử.
     *
     *    Thứ tự cột quan trọng: event_type trước vì nó luôn so bằng, còn
     *    created_at so khoảng. Cột so khoảng phải đứng sau, nếu không
     *    phần sau của chỉ mục thành vô dụng.
     *
     * 2. BỎ chỉ mục products.product_type.
     *    Sau QĐ-08 cột này chỉ còn 3 giá trị ('flower','plant','other')
     *    trên 15 dòng, và KHÔNG câu truy vấn nào lọc theo nó — đã rà toàn
     *    bộ mã nguồn. Chỉ mục không ai dùng vẫn tốn chỗ và làm chậm mọi
     *    lần ghi.
     */
    public function up(): void
    {
        Schema::table('user_events', function (Blueprint $table) {
            $table->index(['event_type', 'created_at'], 'user_events_type_time_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_product_type_index');
        });
    }

    public function down(): void
    {
        Schema::table('user_events', function (Blueprint $table) {
            $table->dropIndex('user_events_type_time_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('product_type', 'products_product_type_index');
        });
    }
};
