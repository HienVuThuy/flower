<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cẩm nang — blog kiến thức về hoa và cây cảnh.
 * ============================================================
 * VÌ SAO BLOG LÀ NỀN TẢNG CHÍNH, KHÔNG PHẢI MẠNG XÃ HỘI.
 *
 * Người mua cây gần như luôn tìm hiểu trước khi mua: cây này ưa nắng hay
 * ưa bóng, bao lâu tưới một lần, có độc với mèo không, để bàn làm việc
 * được không. Họ gõ câu hỏi đó vào Google.
 *
 * Một bài "7 loại cây để bàn ít cần ánh sáng" kéo khách suốt nhiều tháng
 * và dẫn thẳng tới sản phẩm. Một bài kiểu mạng xã hội sống được vài ngày.
 *
 * ============================================================
 * BẢNG NỐI BÀI VỚI SẢN PHẨM LÀ THỨ BIẾN BLOG THÀNH DOANH THU.
 *
 * Không có `blog_post_product` thì bài viết chỉ là chữ, và khách đọc
 * xong phải tự đi tìm cây trong danh mục. Có nó thì mỗi loại cây nhắc
 * trong bài có một nút "Xem cây này" ngay dưới đoạn nói về nó.
 *
 * Dùng bảng nối thay vì dán link vào nội dung: link dán tay sẽ chết khi
 * sản phẩm đổi slug hoặc ngừng bán, và không có gì báo. Khoá ngoại thì
 * biết ngay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->string('description', 300)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('blog_category_id')
                ->nullable()
                /*
                 * Xoá chuyên mục KHÔNG xoá bài. Bài viết là công sức
                 * nhiều giờ; chuyên mục chỉ là cách xếp ngăn. Cascade ở
                 * đây nghĩa là một cú bấm nhầm ở trang chuyên mục xoá
                 * luôn hai chục bài.
                 */
                ->constrained()
                ->nullOnDelete();

            /*
             * Tác giả: nullable và `nullOnDelete`.
             *
             * Nhân viên nghỉ việc thì tài khoản bị xoá, nhưng bài họ viết
             * vẫn là tài sản của cửa hàng và vẫn đang kéo khách từ Google.
             * Cascade ở đây là tự xoá tài sản của mình.
             */
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title', 200);
            $table->string('slug', 220)->unique();

            /*
             * `excerpt` là đoạn tóm tắt CHỦ ĐỘNG viết, không phải cắt tự
             * động từ nội dung.
             *
             * Cắt 160 ký tự đầu thì đoạn mô tả trên Google thường rơi vào
             * giữa câu dẫn nhập ("Nhiều người hỏi tôi rằng…") — đúng phần
             * không nói gì về nội dung bài.
             */
            $table->string('excerpt', 300)->nullable();

            $table->longText('body');
            $table->string('cover_image')->nullable();

            /*
             * `published_at` thay cho cột boolean `is_published`.
             *
             * Một cột trả lời được ba câu: đã đăng chưa (null hay không),
             * đăng khi nào (dùng cho thứ tự và cho "bài mới"), và ĐẶT LỊCH
             * đăng (ngày ở tương lai thì chưa hiện).
             *
             * Cùng lý do đã dùng cho `journal_milestones.done_at` (QĐ-149)
             * và `coupon_user.hidden_at`.
             */
            $table->timestamp('published_at')->nullable();

            /*
             * Thẻ SEO riêng, KHÔNG suy ra từ tiêu đề.
             *
             * Tiêu đề trên trang viết cho người đọc đã ở đây rồi ("Cây
             * nào hợp bàn làm việc?"); tiêu đề trên Google viết cho người
             * chưa biết cửa hàng này tồn tại. Hai câu khác nhau, và ép
             * dùng chung một câu thì mất một trong hai.
             *
             * Để trống thì lấy tiêu đề bài — mặc định hợp lý, không phải
             * bắt buộc điền.
             */
            $table->string('meta_title', 200)->nullable();
            $table->string('meta_description', 300)->nullable();

            $table->unsignedInteger('view_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Truy vấn chính của cả trang: bài đã đăng, mới nhất trước.
            $table->index(['published_at', 'blog_category_id']);
        });

        /*
         * NỐI BÀI VỚI SẢN PHẨM.
         *
         * `note` là câu giải thích riêng cho lần nhắc này — "chịu bóng
         * tốt nhất trong danh sách". Cùng một cây xuất hiện ở ba bài khác
         * nhau thì mỗi bài có lý do riêng để nhắc tới nó; lấy mô tả chung
         * của sản phẩm thì cả ba chỗ đọc như nhau.
         */
        Schema::create('blog_post_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('note', 200)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->unique(['blog_post_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_product');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_categories');
    }
};
