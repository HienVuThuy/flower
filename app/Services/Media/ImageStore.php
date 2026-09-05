<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * MỘT CỬA DUY NHẤT để lưu ảnh do người dùng tải lên.
 * ============================================================
 * VÌ SAO CẦN: trước đây mỗi nơi tự gọi `$file->store('products', 'public')`.
 * Tám chỗ khác nhau trong năm tệp, và không chỗ nào sinh bản WebP — nên
 * mọi ảnh admin tải lên đều nặng gấp ba bốn lần ảnh có sẵn, cho tới khi
 * có người nhớ chạy `php artisan anh:toi-uu` bằng tay.
 *
 * Sửa bằng cách nhắc mọi người "nhớ gọi thêm hàm tối ưu" thì chỗ thứ
 * chín sẽ quên. Sửa bằng cách để chỉ có MỘT cách lưu ảnh thì không có gì
 * để quên.
 *
 * ============================================================
 * TỐI ƯU NGAY, KHÔNG ĐẨY VÀO HÀNG ĐỢI.
 *
 * Hàng đợi có vẻ đúng hơn về lý thuyết — nhưng dự án này đặt
 * `QUEUE_CONNECTION=database` mà KHÔNG có tiến trình worker nào chạy.
 * Đẩy vào hàng đợi ở đây nghĩa là công việc nằm trong bảng `jobs` mãi
 * mãi, và ảnh không bao giờ được tối ưu — hỏng y hệt trước, chỉ khó phát
 * hiện hơn.
 *
 * Chi phí thật: một ảnh 2000px sinh hai bản WebP mất khoảng 100–300ms.
 * Đây là thao tác của admin, không phải của khách, và nó xảy ra sau khi
 * họ đã bấm "Lưu" — thêm một phần ba giây là chấp nhận được.
 *
 * ============================================================
 * TỐI ƯU HỎNG KHÔNG ĐƯỢC LÀM HỎNG VIỆC LƯU ẢNH.
 *
 * Thiếu extension GD, ảnh lạ, hết chỗ trống trên đĩa — tất cả đều có
 * thể. Nhưng ảnh gốc thì ĐÃ lưu xong rồi, và `<x-site.image>` không tìm
 * thấy bản tối ưu thì tự dùng ảnh gốc. Ném lỗi ra ngoài ở đây là làm
 * hỏng cả việc tạo sản phẩm chỉ vì một bước làm-cho-nhẹ-hơn.
 */
class ImageStore
{
    public function __construct(
        private readonly ImageOptimizer $optimizer,
        private readonly ImageMetadataStripper $stripper,
    ) {
    }

    /**
     * Lưu một ảnh tải lên, TƯỚC METADATA, rồi sinh sẵn bản WebP.
     * ============================================================
     * TƯỚC METADATA CHO MỌI ẢNH, KHÔNG CÓ CỜ BẬT/TẮT.
     *
     * Ảnh chụp bằng điện thoại mang theo toạ độ GPS chính xác tới vài mét
     * — tức là địa chỉ nhà người chụp. Xem chú thích đầu
     * `ImageMetadataStripper`.
     *
     * Có thể lập luận rằng chỉ ảnh ĐĂNG CÔNG KHAI mới cần tước. Nhưng:
     *
     *   - một cờ là một thứ để quên, và chỗ quên sẽ là chỗ mới thêm sau
     *     này — đúng cùng lý do lớp này tồn tại (xem chú thích đầu tệp);
     *   - không có trường hợp nào cửa hàng CẦN giữ toạ độ GPS của khách,
     *     kể cả với ảnh riêng tư trong nhật ký;
     *   - ảnh gốc nằm trong đĩa `public` nên truy cập thẳng được bằng
     *     `/storage/…`, "riêng tư" ở đây chỉ là không có link dẫn tới.
     *
     * Tước hỏng KHÔNG làm hỏng việc lưu ảnh — cùng nguyên tắc với bước
     * tối ưu bên dưới. Nhưng khác một điểm quan trọng: nếu tước hỏng thì
     * GHI LOG MỨC error, không phải warning. Một ảnh không tối ưu được
     * chỉ nặng hơn; một ảnh không tước được là dữ liệu vị trí của khách
     * còn nằm trên máy chủ.
     *
     * @param  string  $thuMuc  thư mục trong đĩa `public`, ví dụ `products`
     * @return string đường dẫn tương đối đã lưu
     */
    public function luu(UploadedFile $file, string $thuMuc): string
    {
        $path = $file->store($thuMuc, 'public');

        try {
            $this->stripper->tuoc($path);
        } catch (\Throwable $e) {
            Log::error('KHÔNG TƯỚC ĐƯỢC METADATA của ảnh vừa tải lên', [
                'path' => $path,
                'loi' => $e->getMessage(),
            ]);
        }

        $this->toiUu($path);

        return $path;
    }

    /**
     * Xoá một ảnh cùng mọi bản đã sinh từ nó.
     *
     * Đi cùng cặp với luu(): xoá ảnh gốc mà để lại bản WebP là để lại
     * những tệp không ai dùng, và một mục manifest trỏ vào hư không.
     */
    public function xoa(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('public')->delete($path);

        try {
            $this->optimizer->xoa($path);
        } catch (\Throwable $e) {
            Log::warning('Không dọn được bản tối ưu của ảnh đã xoá', [
                'path' => $path,
                'loi' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sinh bản tối ưu cho một ảnh ĐÃ nằm trên đĩa.
     *
     * Tách riêng để những nơi đã tự gọi `store()` từ trước (và chưa
     * chuyển sang luu()) vẫn tối ưu được bằng một dòng.
     */
    public function toiUu(string $path): void
    {
        try {
            $this->optimizer->xuLyMot($path);
        } catch (\Throwable $e) {
            // Xem chú thích đầu tệp: ảnh gốc đã lưu xong, trang vẫn chạy.
            Log::warning('Không tối ưu được ảnh vừa tải lên', [
                'path' => $path,
                'loi' => $e->getMessage(),
            ]);
        }
    }
}
