<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Góc cây của bạn" — mạng xã hội NHẸ, cố ý nhẹ.
 * ============================================================
 * KHÔNG DỰNG MỘT FACEBOOK THỨ HAI.
 *
 * Không follower, không bảng tin theo thuật toán, không nhắn tin, không
 * story. Lý do không phải vì lười: một khu vực mạng xã hội mới sinh ra
 * gặp bài toán con gà–quả trứng — không có người thì trống trơn, mà
 * trống trơn thì không ai vào.
 *
 * Thứ dùng được ngay từ ngày đầu là một dòng ảnh khách đăng: cây họ vừa
 * mua, góc ban công, hoa đang nở. Nó không cần ai theo dõi ai.
 *
 * ============================================================
 * ⚠️ ẢNH Ở ĐÂY LÀ ẢNH CÔNG KHAI — METADATA PHẢI BỊ TƯỚC.
 *
 * Khách chụp cây trên ban công nhà mình bằng điện thoại. Tệp đó mang
 * theo toạ độ GPS chính xác tới vài mét. Đăng lên mà không tước là đăng
 * luôn địa chỉ nhà họ cho bất kỳ ai tải ảnh về.
 *
 * `ImageStore::luu()` tước metadata cho MỌI ảnh, không có cờ bật/tắt —
 * xem `ImageMetadataStripper`. Mọi đường ghi ảnh ở đây phải đi qua đó.
 *
 * ============================================================
 * DUYỆT TRƯỚC KHI HIỆN, KHÔNG DUYỆT SAU.
 *
 * Đây là nội dung người lạ đăng lên một trang bán hàng. Hiện ngay rồi gỡ
 * sau nghĩa là khoảng thời gian giữa hai việc đó, trang của cửa hàng
 * đang hiển thị bất kỳ thứ gì ai đó vừa gửi lên.
 *
 * Với một cửa hàng nhỏ, số bài mỗi ngày đếm trên đầu ngón tay, nên duyệt
 * tay không phải gánh nặng. Khi nào nhiều tới mức không duyệt xuể thì đó
 * là lúc bàn tới tự động — không phải bây giờ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_posts', function (Blueprint $table) {
            $table->id();

            /*
             * Xoá tài khoản thì bài đi theo (`cascadeOnDelete`).
             *
             * Khác với bài blog của nhân viên — bài blog là tài sản của
             * cửa hàng, còn bài ở đây là nội dung cá nhân của khách kèm
             * ảnh nhà họ. Người yêu cầu xoá tài khoản đang yêu cầu xoá cả
             * thứ đó.
             */
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('body', 1000);
            $table->string('photo')->nullable();

            /*
             * Gắn với một sản phẩm — không bắt buộc.
             *
             * Khách khoe cây mua ở đây thì gắn được; khoe cây tự trồng
             * thì thôi. Bắt buộc gắn là loại bỏ đúng nhóm nội dung tự
             * nhiên nhất.
             *
             * `nullOnDelete`: sản phẩm ngừng bán thì bài vẫn còn, chỉ mất
             * cái link.
             */
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            /*
             * `approved_at` / `rejected_at` thay cho một cột trạng thái
             * chuỗi.
             *
             * Ba trạng thái suy ra được: chưa duyệt (cả hai null), đã
             * duyệt, đã từ chối. Và mỗi mốc kèm luôn THỜI ĐIỂM — thứ cần
             * khi khách hỏi "bài tôi gửi hôm kia sao rồi".
             */
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            /*
             * Lý do từ chối — hiện lại cho chính người đăng.
             *
             * Từ chối im lặng thì khách đăng lại y hệt, rồi lại bị từ
             * chối, và họ kết luận là trang bị hỏng.
             */
            $table->string('reject_reason', 200)->nullable();

            $table->timestamps();

            // Truy vấn chính: bài đã duyệt, mới nhất trước.
            $table->index(['approved_at', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_posts');
    }
};
