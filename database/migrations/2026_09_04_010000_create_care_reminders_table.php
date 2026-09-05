<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * NHẮC LỊCH CHĂM CÂY cho cây khách đã mua.
     * ============================================================
     * NGHIỆP VỤ — và là thứ chỉ ngành cây cảnh mới có:
     * Bán một cái áo là xong. Bán một cái cây thì mới bắt đầu: khách mang
     * về, quên tưới hai tuần, cây chết, và họ kết luận "tôi không trồng
     * được cây" chứ không mua lại. Nhắc đúng lúc là giữ cả cái cây lẫn
     * người khách.
     *
     * SINH RA KHI ĐƠN CHUYỂN SANG "ĐÃ GIAO", không phải khi đặt hàng:
     * lúc đặt thì cây còn ở cửa hàng. Nhắc tưới một cái cây chưa tới tay
     * là thông báo rác.
     *
     * MỘT HÀNG = MỘT VIỆC CHĂM CHO MỘT CÂY CỦA MỘT KHÁCH.
     * `kind` tách tưới nước khỏi bón phân vì hai việc có chu kỳ rất khác
     * nhau (4 ngày và 30 ngày) và khách có thể muốn tắt riêng từng loại.
     *
     * KHÔNG khoá ngoại tới `orders`: đơn hàng có thể bị xoá mềm hoặc xoá
     * hẳn, còn cái cây thì vẫn ở nhà khách. Lịch chăm không được biến mất
     * theo hồ sơ bán hàng.
     */
    public function up(): void
    {
        Schema::create('care_reminders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // 'water' | 'fertilizer' — xem App\Enums\CareTask.
            $table->string('kind', 16);

            /** Cứ bao nhiêu ngày thì nhắc. Chụp lại từ care_info lúc tạo. */
            $table->unsignedSmallInteger('interval_days');

            $table->timestamp('next_due_at');
            $table->timestamp('last_sent_at')->nullable();

            /*
             * Khách tự tắt riêng một lịch (cây đã chết, đã tặng đi).
             * TẮT chứ không XOÁ: xoá thì lần sau mua lại đúng cây đó,
             * lịch được tạo mới và họ phải tắt lại lần nữa.
             */
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            /*
             * UNIQUE — một khách, một cây, một loại việc thì chỉ một lịch.
             *
             * Khách mua cùng một cây ba lần thì vẫn là một lịch tưới, không
             * phải ba thư nhắc cùng lúc mỗi bốn ngày.
             */
            $table->unique(['user_id', 'product_id', 'kind'], 'care_reminders_unique');

            /*
             * Chỉ mục cho câu truy vấn DUY NHẤT mà lệnh gửi thư dùng:
             * "lịch nào đang bật và đã tới hạn". Cột so bằng (is_active)
             * đứng trước cột so khoảng (next_due_at).
             */
            $table->index(['is_active', 'next_due_at'], 'care_reminders_due');
        });

        Schema::table('users', function (Blueprint $table) {
            /*
             * Công tắc TỔNG cho toàn bộ thư nhắc chăm cây.
             *
             * Tách khỏi notify_order_updates vì hai loại thư khác hẳn
             * nhau: thư đơn hàng là việc mua bán, thư nhắc chăm là dịch
             * vụ sau bán. Có người muốn cái này mà không muốn cái kia.
             *
             * Mặc định TRUE nhưng chỉ có tác dụng khi khách thật sự mua
             * cây có khai chu kỳ — không mua gì thì không có lịch nào,
             * nên bật sẵn cũng không sinh thư rác.
             */
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
