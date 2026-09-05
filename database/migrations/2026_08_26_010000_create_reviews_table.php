<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Đánh giá sản phẩm.
     *
     * VÌ SAO CÓ order_id:
     * Đánh giá chỉ có giá trị khi người viết đã thật sự mua và nhận hàng.
     * Lưu đơn hàng làm bằng chứng mua ngay trong bản ghi cho phép:
     *   - hiển thị nhãn "Đã mua hàng" mà không phải dò lại toàn bộ đơn cũ;
     *   - chặn đánh giá trùng bằng ràng buộc của cơ sở dữ liệu chứ không
     *     chỉ bằng câu lệnh if trong PHP.
     *
     * VÌ SAO user_id KHÔNG cho null:
     * Khách vãng lai có thể đặt hàng, nhưng không đánh giá được. Nếu cho
     * phép, bất cứ ai biết mã đơn cũng viết được đánh giá đứng tên người
     * mua, và không có cách nào sửa hay gỡ bài của chính mình.
     *
     * VÌ SAO KHÔNG lưu sẵn điểm trung bình vào bảng products:
     * Số sản phẩm ở đây nhỏ; withAvg()/withCount() của Eloquent đủ nhanh.
     * Thêm cột tổng hợp là thêm một chỗ có thể sai lệch so với dữ liệu gốc,
     * chỉ nên làm khi đo được là chậm thật.
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
             * Đơn hàng làm bằng chứng đã mua.
             * nullOnDelete: xoá đơn thì đánh giá vẫn còn — nội dung khách
             * viết ra là của họ, không biến mất theo hồ sơ đơn hàng.
             */
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();

            // 1..5 sao. Ràng buộc khoảng giá trị do FormRequest lo; ở đây
            // chỉ cần kiểu số nhỏ nhất chứa đủ.
            $table->unsignedTinyInteger('rating');

            $table->text('comment')->nullable();

            /*
             * Cửa hàng phải có cách gỡ một bài viết bậy xuống. Mặc định
             * hiện ngay: bắt duyệt từng bài sẽ khiến mục đánh giá trống
             * trơn khi chủ cửa hàng bận, mà đánh giá trống thì vô dụng.
             */
            $table->boolean('is_visible')->default(true);

            $table->timestamps();

            /*
             * Mỗi người, mỗi sản phẩm, mỗi đơn: một đánh giá.
             * Mua lại sản phẩm đó ở đơn khác thì được viết bài mới —
             * lần mua sau có thể là trải nghiệm khác hẳn.
             */
            $table->unique(['user_id', 'product_id', 'order_id']);

            // Trang sản phẩm luôn lọc theo product_id + is_visible.
            $table->index(['product_id', 'is_visible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
