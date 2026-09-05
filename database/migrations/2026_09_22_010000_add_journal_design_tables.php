<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhật ký: trang trí sổ, và cấu trúc riêng cho từng loại sổ.
 * ============================================================
 * ⚠️ VẪN LÀ DỮ LIỆU RIÊNG TƯ. Ba bảng nhật ký cũ và bảng mới ở đây đều
 * nằm trong phạm vi cấm của QĐ-123: không bộ máy gợi ý, phân tích hay
 * định giá nào được đọc. `JournalPrivacyTest` canh ở tầng SQL.
 *
 * ============================================================
 * VÌ SAO THÊM `data` (JSON) THAY VÌ THÊM CỘT CHO TỪNG LOẠI SỔ
 *
 * Mỗi loại sổ cần những trường khác hẳn nhau:
 *
 *   - Sinh trưởng: hôm nay đã tưới chưa, đã bón chưa, có thay chậu không
 *   - Bảng giá:    khảo ở đâu, giá bao nhiêu
 *   - Phân tích:   chấm mấy điểm, ưu gì nhược gì
 *
 * Làm thành cột riêng thì `journal_entries` có thêm chín cột mà mỗi
 * trang nhật ký chỉ dùng hai ba cột, bảy cột còn lại NULL vĩnh viễn. Và
 * mỗi lần thêm một loại sổ mới là một migration nữa.
 *
 * ĐIỀU KIỆN ĐỂ CỘT JSON NÀY KHÔNG THÀNH THÙNG RÁC — giống hệt điều kiện
 * đã đặt cho `product_traits` (xem App\Enums\TraitType):
 *
 *   1. Khoá hợp lệ do `JournalKind::dataFields()` khai báo, đóng.
 *   2. Controller chỉ ghi những khoá loại sổ đó khai.
 *   3. Không khoá nào ở đây được dùng để truy vấn, lọc hay thống kê
 *      chéo — chúng chỉ để hiển thị lại đúng trang đó.
 *
 * Mất điều kiện 3 thì phải tách cột thật, vì JSON không đánh chỉ mục
 * được theo cách này.
 *
 * ============================================================
 * VÌ SAO `journal_milestones` LÀ BẢNG RIÊNG, KHÔNG NHÉT VÀO `data`
 *
 * Một mốc mục tiêu KHÔNG thuộc về một trang nhật ký nào cả. Nó thuộc về
 * cả quyển sổ, có thứ tự riêng, được đánh dấu hoàn thành ở một thời điểm
 * khác với lúc tạo, và người dùng sắp xếp lại được.
 *
 * Đó là một thực thể, không phải một thuộc tính — nên nó là bảng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            /*
             * GIAO DIỆN SỔ — màu giấy, màu nhấn, hoa văn.
             *
             * Là một khoá chuỗi chứ không phải mã màu: cho người dùng
             * chọn mã màu tự do thì sẽ có những quyển sổ chữ xám trên nền
             * xám không đọc nổi, và không có gì trong hệ thống ngăn được.
             * Một bộ giao diện đã chọn sẵn thì cái nào cũng đọc được ở cả
             * nền sáng lẫn nền tối.
             */
            $table->string('theme_key', 30)->nullable()->after('cover_image');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            /*
             * NHÃN DÁN — một hình vẽ nhỏ gắn vào trang nhật ký.
             *
             * Lưu KHOÁ, không lưu ảnh: nhãn dán là hình vẽ SVG nằm trong
             * mã nguồn, không phải tệp người dùng tải lên. Nhờ vậy đổi
             * nét vẽ về sau thì mọi trang cũ đổi theo, và không có tệp
             * rác nào tích lại trong ổ đĩa.
             */
            $table->string('sticker', 30)->nullable()->after('photo');

            /** Trường riêng của từng loại sổ — xem chú thích đầu tệp. */
            $table->json('data')->nullable()->after('sticker');
        });

        Schema::create('journal_milestones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('journal_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('title', 150);

            /*
             * `done_at` thay cho một cột boolean `is_done`.
             *
             * Biết một mốc đã xong thì hữu ích; biết nó xong NGÀY NÀO thì
             * hữu ích hơn nhiều — đó là thứ dựng được dòng thời gian "mất
             * bao lâu để đi từ mốc này sang mốc kia".
             *
             * NULL = chưa xong. Một cột trả lời được hai câu hỏi.
             */
            $table->timestamp('done_at')->nullable();

            /*
             * Hạn riêng của từng mốc, KHÁC với hạn chung của cả sổ
             * (`journals.target_date`). Một mục tiêu sáu tháng có thể có
             * bốn mốc, mỗi mốc một hạn.
             */
            $table->date('due_date')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['journal_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_milestones');

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropColumn(['sticker', 'data']);
        });

        Schema::table('journals', function (Blueprint $table) {
            $table->dropColumn('theme_key');
        });
    }
};
