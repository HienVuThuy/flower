<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NHẬT KÝ CÁ NHÂN — ba bảng, và mỗi bảng có một lý do riêng.
 * ============================================================
 * ⚠️ ĐÂY LÀ DỮ LIỆU RIÊNG TƯ CỦA TỪNG NGƯỜI.
 *
 * Không nơi nào trong hệ thống được đọc mấy bảng này ngoài chính chủ:
 * KHÔNG gợi ý sản phẩm, KHÔNG phân tích hành vi, KHÔNG thống kê quản
 * trị, KHÔNG cá nhân hoá. Người ta ghi vào đây chuyện cây nhà mình chết
 * vì quên tưới, ghi giá mình định chờ, ghi mục tiêu mình chưa làm được —
 * đó không phải dữ liệu để bán hàng.
 *
 * Ràng buộc này được giữ bằng một bài kiểm thử riêng
 * (`JournalPrivacyTest`) đếm truy vấn: gọi bộ máy gợi ý và khẳng định
 * KHÔNG có câu truy vấn nào chạm vào ba bảng dưới đây. Chỉ ghi vào tài
 * liệu thì lần sửa sau sẽ quên.
 *
 * ============================================================
 * VÌ SAO BA BẢNG CHỨ KHÔNG PHẢI MỘT.
 *
 *   journals         — QUYỂN SỔ. Một người có nhiều sổ cho nhiều cây,
 *                      nhiều mục đích. Không tách thì mọi trang nhật ký
 *                      nằm chung một dòng thời gian và không lọc được.
 *
 *   journal_entries  — TRANG NHẬT KÝ. Một sổ có nhiều trang theo ngày.
 *
 *   journal_metrics  — CHỈ SỐ ĐO ĐƯỢC trong một trang. Tách riêng vì
 *                      NGƯỜI DÙNG TỰ ĐẶT TÊN chỉ số: người trồng lan ghi
 *                      "số nụ", người chơi bonsai ghi "đường kính thân".
 *                      Làm thành cột thì phải đoán trước mọi chỉ số của
 *                      mọi loài — việc không bao giờ xong. Tách bảng còn
 *                      cho phép vẽ biểu đồ một chỉ số theo thời gian
 *                      bằng một câu truy vấn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journals', function (Blueprint $table) {
            $table->id();

            /*
             * cascadeOnDelete: người dùng xoá tài khoản thì nhật ký đi
             * theo. Đây là dữ liệu riêng tư của họ — giữ lại một quyển sổ
             * mồ côi không còn chủ là giữ lại đúng thứ đáng lẽ phải xoá.
             */
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->string('kind', 20);
            $table->text('description')->nullable();

            /*
             * GẮN VỚI MỘT SẢN PHẨM ĐÃ MUA — không bắt buộc.
             *
             * Có thì trang sổ hiện thẳng hướng dẫn chăm sóc và đường dẫn
             * phân loại của đúng cây đó. Không có cũng chẳng sao: người
             * ta ghi nhật ký cho cây xin của hàng xóm cũng là chuyện
             * thường.
             *
             * nullOnDelete: cửa hàng ngừng bán một sản phẩm KHÔNG được
             * kéo theo nhật ký của khách.
             */
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            $table->string('cover_image')->nullable();
            $table->date('started_at')->nullable();

            /*
             * CHỈ SỐ VẼ BIỂU ĐỒ.
             *
             * Một sổ có thể ghi nhiều chỉ số; đây là cái được vẽ ở đầu
             * trang. Lưu ở sổ chứ không đoán lại mỗi lần mở: người dùng
             * chọn một lần rồi thôi.
             */
            $table->string('chart_metric')->nullable();

            /*
             * ---------- MỤC TIÊU ----------
             * Bốn cột dưới đây CHỈ có nghĩa với sổ loại `goal`, và chúng
             * nullable vì lý do đó.
             *
             * Vì sao không tách thành bảng riêng: một mục tiêu là MỘT
             * dòng cho MỘT sổ — quan hệ 1-1. Bảng riêng cho quan hệ 1-1
             * chỉ thêm một lượt join vào mọi truy vấn để đổi lấy vài cột
             * NULL ở loại sổ khác.
             */
            $table->string('target_metric')->nullable();
            $table->decimal('target_value', 14, 2)->nullable();
            $table->string('target_unit', 20)->nullable();
            $table->date('target_date')->nullable();

            /*
             * LƯU TRỮ thay cho XOÁ.
             *
             * Cây chết rồi thì quyển sổ vẫn là kỷ niệm, và vẫn là bài học
             * cho lần trồng sau. Cho ẩn khỏi danh sách chính, đừng bắt
             * người ta phải xoá mới cho gọn màn hình.
             */
            $table->boolean('is_archived')->default(false);

            $table->timestamps();

            // Truy vấn thường gặp: sổ của tôi, chưa lưu trữ, mới nhất trước.
            $table->index(['user_id', 'is_archived', 'updated_at']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();

            /*
             * NGÀY GHI NHẬN, tách khỏi `created_at`.
             *
             * Người ta hay ghi bù: chủ nhật ngồi ghi lại cả tuần. Dùng
             * `created_at` để dựng dòng thời gian thì bốn trang của bốn
             * ngày khác nhau dồn hết vào chủ nhật, và biểu đồ sinh trưởng
             * thành một cột dựng đứng.
             */
            $table->date('entry_date');

            $table->string('title')->nullable();
            $table->text('body')->nullable();

            /** Tình trạng cây — xem App\Enums\PlantCondition. Không bắt buộc. */
            $table->string('condition', 20)->nullable();

            /*
             * MỘT ảnh mỗi trang, không phải nhiều.
             *
             * Nhật ký sinh trưởng dùng ảnh theo kiểu tua nhanh thời gian:
             * cùng một góc chụp, mỗi lần một tấm. Cho nhiều ảnh mỗi trang
             * thì cần thêm một bảng nữa, mà công dụng chính vẫn chỉ dùng
             * tới tấm đầu tiên.
             */
            $table->string('photo')->nullable();

            $table->timestamps();

            // Dòng thời gian của một sổ: lọc theo sổ, xếp theo ngày ghi.
            $table->index(['journal_id', 'entry_date']);
        });

        Schema::create('journal_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();

            /** Tên do NGƯỜI DÙNG đặt: "Chiều cao", "Số nụ", "Giá tại chợ Bưởi". */
            $table->string('name');

            /*
             * decimal chứ không float: chỉ số hay là tiền (sổ theo dõi
             * giá), và tiền thì không được dùng số thực. Dùng chung một
             * kiểu cho mọi chỉ số để không phải phân biệt lúc đọc ra.
             */
            $table->decimal('value', 14, 2);

            /** Đơn vị: "cm", "lá", "%", ký hiệu tiền tệ. Không bắt buộc. */
            $table->string('unit', 20)->nullable();

            $table->timestamps();

            /*
             * Truy vấn vẽ biểu đồ: lấy MỘT chỉ số theo tên, trong mọi
             * trang của một sổ. Chỉ mục ghép phủ đúng câu đó.
             */
            $table->index(['journal_entry_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_metrics');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('journals');
    }
};
