<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Storage;

/**
 * Xoá metadata khỏi ảnh người dùng tải lên.
 * ============================================================
 * VÌ SAO PHẢI CÓ: ẢNH CHỤP BẰNG ĐIỆN THOẠI MANG THEO TOẠ ĐỘ GPS.
 *
 * Khách chụp cây trên ban công nhà mình rồi đăng lên mục "Góc cây của
 * bạn". Tệp JPEG đó thường chứa khối EXIF với:
 *
 *   - GPSLatitude / GPSLongitude — chính xác tới vài mét, tức là ĐỊA CHỈ
 *     NHÀ họ;
 *   - DateTimeOriginal — giờ họ ở nhà;
 *   - Make / Model — máy họ dùng;
 *   - đôi khi cả tên chủ máy trong Artist / Copyright.
 *
 * Không xoá thì bất kỳ ai tải ảnh về cũng đọc được. Đây không phải rủi
 * ro lý thuyết — nó là cách người ta bị tìm ra địa chỉ từ một bức ảnh
 * đăng công khai.
 *
 * ============================================================
 * BẢN WEBP ĐÃ SẠCH SẴN — VẤN ĐỀ NẰM Ở ẢNH GỐC.
 *
 * `ImageOptimizer` dựng bản WebP bằng GD, mà GD chỉ chép PIXEL sang ảnh
 * mới — mọi khối EXIF bị bỏ lại. Nên bản hiển thị vốn đã an toàn.
 *
 * Nhưng ẢNH GỐC được lưu nguyên xi và nằm trong đĩa `public`, truy cập
 * thẳng được bằng `/storage/…`. `<x-site.image>` cũng lùi về ảnh gốc khi
 * chưa có bản WebP. Nên chỉ dựa vào WebP là để hở đúng tệp nguy hiểm
 * nhất.
 *
 * Lớp này GHI ĐÈ chính ảnh gốc bằng một bản đã tước sạch metadata.
 *
 * ============================================================
 * ⚠️ PHẢI XOAY ẢNH TRƯỚC KHI XOÁ EXIF.
 *
 * Đây là cái bẫy kinh điển của việc tước metadata, và nó hỏng theo cách
 * người dùng thấy ngay.
 *
 * Điện thoại hầu như không xoay pixel khi chụp dọc. Nó lưu ảnh NGANG rồi
 * ghi vào EXIF một cờ `Orientation` bảo trình xem "xoay 90° khi hiển
 * thị". Xoá EXIF mà không xoay pixel trước thì cờ đó biến mất, và mọi
 * ảnh chụp dọc nằm nghiêng vĩnh viễn.
 *
 * Nên thứ tự bắt buộc là: ĐỌC cờ -> XOAY pixel -> GHI lại không EXIF.
 */
class ImageMetadataStripper
{
    /**
     * Chất lượng khi ghi lại JPEG.
     *
     * 90: đủ cao để một lần ghi lại không nhìn ra khác biệt, đủ thấp để
     * tệp không phình. Ảnh chỉ đi qua đây MỘT lần (lúc tải lên) nên
     * không có chuyện suy giảm dồn qua nhiều đời.
     */
    private const CHAT_LUONG_JPEG = 90;

    /**
     * Tước metadata khỏi một ảnh đã nằm trên đĩa `public`.
     *
     * @return bool true nếu đã ghi lại được; false nếu bỏ qua (định dạng
     *              không xử lý được, hoặc GD không đọc nổi tệp)
     */
    public function tuoc(string $path): bool
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return false;
        }

        $duongDan = $disk->path($path);
        $duoi = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $anh = match ($duoi) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($duongDan),
            'png' => @imagecreatefrompng($duongDan),
            'webp' => @imagecreatefromwebp($duongDan),
            default => false,
        };

        if (! $anh) {
            return false;
        }

        // XOAY TRƯỚC — xem chú thích đầu tệp về vì sao thứ tự này bắt buộc.
        $anh = $this->xoayTheoExif($anh, $duongDan, $duoi);

        /*
         * PNG và WebP có thể có kênh trong suốt. Không giữ lại thì nền
         * trong suốt thành ĐEN — cùng lỗi đã ghi trong ImageOptimizer.
         */
        if ($duoi !== 'jpg' && $duoi !== 'jpeg') {
            imagealphablending($anh, false);
            imagesavealpha($anh, true);
        }

        /*
         * GHI RA TỆP TẠM RỒI MỚI ĐỔI CHỖ.
         *
         * Ghi thẳng đè lên ảnh gốc thì nếu quá trình ghi hỏng giữa chừng
         * (hết đĩa, tiến trình bị kill) sẽ còn lại một tệp cụt — và ảnh
         * gốc thì đã mất. Ghi tạm rồi `rename` là thao tác nguyên tử trên
         * cùng một phân vùng.
         */
        $tam = $duongDan . '.strip.tmp';

        $ok = match ($duoi) {
            'jpg', 'jpeg' => imagejpeg($anh, $tam, self::CHAT_LUONG_JPEG),
            'png' => imagepng($anh, $tam),
            'webp' => imagewebp($anh, $tam, self::CHAT_LUONG_JPEG),
            default => false,
        };

        imagedestroy($anh);

        if (! $ok || ! is_file($tam)) {
            @unlink($tam);

            return false;
        }

        return @rename($tam, $duongDan);
    }

    /**
     * Xoay pixel theo cờ EXIF Orientation.
     *
     * CHỈ JPEG mới có EXIF Orientation. PNG và WebP không dùng cơ chế
     * này, nên gọi `exif_read_data` cho chúng chỉ tốn một lượt đọc đĩa
     * để nhận về false.
     *
     * Không có extension `exif` thì bỏ qua phép xoay chứ KHÔNG bỏ qua
     * việc tước metadata: ảnh nằm nghiêng còn đỡ hơn ảnh lộ toạ độ nhà.
     *
     * @param  \GdImage  $anh
     * @return \GdImage
     */
    private function xoayTheoExif(\GdImage $anh, string $duongDan, string $duoi): \GdImage
    {
        if (! in_array($duoi, ['jpg', 'jpeg'], true) || ! function_exists('exif_read_data')) {
            return $anh;
        }

        $exif = @exif_read_data($duongDan);
        $huong = (int) ($exif['Orientation'] ?? 0);

        /*
         * Tám giá trị của Orientation, nhưng chỉ ba giá trị dưới đây gặp
         * ngoài đời (2, 4, 5, 7 là các bản LẬT GƯƠNG — gần như không máy
         * nào sinh ra).
         *
         * `imagerotate` quay NGƯỢC chiều kim đồng hồ, còn EXIF mô tả góc
         * cần xoay THEO chiều kim đồng hồ — nên góc bị đảo dấu.
         */
        $goc = match ($huong) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($goc === 0) {
            return $anh;
        }

        $xoay = @imagerotate($anh, $goc, 0);

        if (! $xoay) {
            return $anh;
        }

        imagedestroy($anh);

        return $xoay;
    }

    /**
     * Ảnh này còn metadata nào đáng lo không.
     *
     * Dùng cho lệnh quét và cho bài kiểm thử. Chỉ báo những khối THẬT SỰ
     * nhận diện được người chụp — bỏ qua các khoá kỹ thuật vô hại như
     * kích thước hay hệ màu, vì báo cả chúng thì mọi ảnh đều "có vấn đề"
     * và cảnh báo mất hết ý nghĩa.
     *
     * @return list<string> tên các khối tìm thấy; rỗng nghĩa là sạch
     */
    public function metadataConLai(string $path): array
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path) || ! function_exists('exif_read_data')) {
            return [];
        }

        $exif = @exif_read_data($disk->path($path));

        if (! is_array($exif)) {
            return [];
        }

        $nguyHiem = [];

        foreach (['GPSLatitude', 'GPSLongitude', 'GPSAltitude'] as $k) {
            if (isset($exif[$k]) || isset($exif['GPS'][$k])) {
                $nguyHiem[] = $k;
            }
        }

        foreach (['Make', 'Model', 'DateTimeOriginal', 'Artist', 'Copyright', 'Software'] as $k) {
            if (! empty($exif[$k])) {
                $nguyHiem[] = $k;
            }
        }

        return $nguyHiem;
    }
}
