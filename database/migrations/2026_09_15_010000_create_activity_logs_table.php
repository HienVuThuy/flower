<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhật ký thao tác quản trị — AI ĐÃ LÀM GÌ, LÚC NÀO.
 * ============================================================
 * NGHIỆP VỤ CỤ THỂ, không phải "web nào cũng có bảng này":
 *
 * Cửa hàng có nhiều hơn một người vào trang quản trị. Khi một đơn bị huỷ
 * nhầm, một sản phẩm đổi giá, hay một đánh giá xấu biến mất khỏi trang,
 * câu hỏi đầu tiên luôn là "ai làm?" — và hiện tại không có chỗ nào trả
 * lời được. Bản thân bản ghi chỉ giữ trạng thái CUỐI CÙNG: nhìn một sản
 * phẩm giá 300k không biết được hôm qua nó là 500k và ai hạ xuống.
 *
 * KHÁC GÌ `user_events`: bảng kia ghi hành vi KHÁCH trên trang bán hàng
 * để phân tích (xem gì, thêm giỏ gì). Bảng này ghi thao tác GHI DỮ LIỆU
 * ở khu quản trị để truy trách nhiệm. Hai mục đích khác nhau, hai vòng
 * đời khác nhau, nên là hai bảng.
 *
 * KHÔNG CÓ ĐƯỜNG XOÁ, KHÔNG CÓ ĐƯỜNG SỬA. Một nhật ký mà người bị ghi
 * có thể xoá thì không dùng để truy trách nhiệm được — đó là toàn bộ lý
 * do nó tồn tại. Trang quản trị chỉ có xem và lọc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            /*
             * NGƯỜI THỰC HIỆN. nullOnDelete chứ không cascade: xoá tài
             * khoản không được xoá dấu vết việc họ đã làm — nếu không
             * thì cách xoá sạch nhật ký của mình là tự xoá tài khoản.
             *
             * Vẫn nullable vì có việc do hệ thống tự làm (lệnh chạy nền),
             * không có người nào đứng sau.
             */
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
             * CHỤP LẠI TÊN NGƯỜI LÀM ngay lúc ghi.
             *
             * user_id thành NULL khi tài khoản bị xoá, và khi đó dòng
             * nhật ký mất hết ý nghĩa. Tên chụp sẵn giữ cho nó vẫn đọc
             * được: "Nguyễn Văn A (tài khoản đã xoá) đã huỷ đơn ...".
             * Cùng lý do với việc order_items chụp tên sản phẩm.
             */
            $table->string('actor_name')->nullable();

            /*
             * MÃ VIỆC, dạng `đối-tượng.việc` — 'order.status_changed',
             * 'product.deleted'. Dùng để LỌC, nên là chuỗi ngắn cố định
             * chứ không phải câu tiếng Việt.
             */
            $table->string('action', 60)->index();

            /*
             * ĐỐI TƯỢNG BỊ TÁC ĐỘNG, lưu kiểu đa hình (đơn hàng, sản
             * phẩm, mã giảm giá...). Nullable vì có việc không gắn với
             * bản ghi nào — ví dụ đổi cài đặt cửa hàng.
             *
             * KHÔNG đặt khoá ngoại: đối tượng có thể bị xoá hẳn, mà dòng
             * nhật ký về việc xoá nó thì phải ở lại. Đây đúng là chỗ
             * khoá ngoại làm hỏng việc.
             */
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            /*
             * CÂU MÔ TẢ ĐỌC ĐƯỢC, viết sẵn lúc ghi.
             *
             * Không dựng lại từ `action` + `properties` lúc hiển thị:
             * cách diễn đạt sẽ đổi theo thời gian, và khi đó nhật ký cũ
             * bị đọc bằng luật mới. Câu chữ phải đóng băng cùng sự việc.
             */
            $table->string('description', 500);

            /*
             * Chi tiết máy đọc: giá trị trước và sau, mã đơn, ...
             * Dùng khi cần soi kỹ một dòng, không dùng để lọc.
             */
            $table->json('properties')->nullable();

            // Địa chỉ IP — phân biệt "đăng nhập từ máy ở cửa hàng" với
            // "đăng nhập từ nơi khác" khi có nghi vấn.
            $table->string('ip_address', 45)->nullable();

            /*
             * CHỈ CÓ created_at.
             *
             * Không có updated_at vì một dòng nhật ký KHÔNG BAO GIỜ được
             * sửa. Thêm cột đó là mở cửa cho việc sửa, và một nhật ký sửa
             * được thì không còn là nhật ký.
             */
            $table->timestamp('created_at')->useCurrent();

            // Hai đường tra thường dùng: theo thời gian (mặc định) và
            // theo đối tượng ("đơn này đã qua tay những ai").
            $table->index('created_at');
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
