<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Sinh bản WebP nhiều cỡ cho MỘT ảnh, và cập nhật manifest.
 * ============================================================
 * VÌ SAO TÁCH RA KHỎI LỆNH `anh:toi-uu`.
 *
 * LỖI ĐANG SỬA: việc tối ưu ảnh trước đây CHỈ nằm trong một lệnh dòng
 * lệnh chạy tay. Admin thêm sản phẩm mới và tải ảnh lên thì ảnh đó được
 * lưu nguyên bản JPEG, không có bản WebP, không có trong manifest.
 *
 * Trang vẫn chạy — `<x-site.image>` không tìm thấy bản tối ưu thì dùng
 * thẳng ảnh gốc. Nên không ai thấy gì hỏng. Chỉ có điều ảnh đó nặng gấp
 * ba bốn lần những ảnh khác, mãi mãi, cho tới khi có người nhớ ra và gõ
 * `php artisan anh:toi-uu`.
 *
 * Một việc bắt buộc phải nhớ làm bằng tay sau mỗi lần tải ảnh thì sớm
 * muộn cũng bị quên. Nay mọi đường tải ảnh lên đều đi qua ImageStore,
 * và ImageStore gọi thẳng lớp này.
 *
 * ============================================================
 * MỘT BẢN CÀI ĐẶT, HAI NƠI GỌI:
 *
 *   - ImageStore  — mỗi lần admin tải một ảnh lên (tự động, ngay lập tức)
 *   - anh:toi-uu  — quét lại toàn bộ (bù cho ảnh cũ, hoặc khi đổi cỡ)
 *
 * Chép thuật toán resize làm hai bản là hai bản sẽ lệch nhau: đổi chất
 * lượng nén ở một chỗ, và ảnh cũ với ảnh mới trông khác nhau trên cùng
 * một trang.
 */
class ImageOptimizer
{
    /** Chất lượng nén WebP mặc định. */
    public const CHAT_LUONG = 82;

    /**
     * Sinh bản WebP cho một ảnh đã nằm trên đĩa `public`.
     *
     * @param  string  $path  đường dẫn tương đối trong đĩa public, ví dụ `products/abc.jpg`
     * @param  bool  $lamLai  bỏ qua bản đã có, sinh lại từ đầu
     * @return bool true nếu ảnh này đã được ghi vào manifest
     */
    public function xuLyMot(string $path, bool $lamLai = false, int $chatLuong = self::CHAT_LUONG): bool
    {
        $disk = Storage::disk('public');

        if (! preg_match('/\.(jpe?g|png)$/i', $path) || ! $disk->exists($path)) {
            return false;
        }

        $duongDanThat = $disk->path($path);
        $kichThuoc = @getimagesize($duongDanThat);

        if (! $kichThuoc) {
            return false;
        }

        [$rong, $cao] = $kichThuoc;
        $ban = [];

        foreach (ResponsiveImage::WIDTHS as $w) {
            /*
             * KHÔNG PHÓNG TO ảnh nhỏ hơn mức đích.
             *
             * Phóng to không thêm chi tiết nào, chỉ thêm dung lượng —
             * bản "800px" của một ảnh gốc 600px vừa mờ vừa nặng hơn
             * chính nó.
             */
            if ($rong < $w) {
                continue;
            }

            $dich = ResponsiveImage::FOLDER . "/{$w}/" . preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);

            // Bản cũ còn mới hơn ảnh gốc thì dùng lại, khỏi sinh.
            if (! $lamLai && $disk->exists($dich)
                && $disk->lastModified($dich) >= $disk->lastModified($path)) {
                $ban[$w] = $dich;

                continue;
            }

            if ($this->sinhBan($duongDanThat, $disk->path($dich), $w, $rong, $cao, $chatLuong)) {
                $ban[$w] = $dich;
            }
        }

        $this->ghiManifest($path, [
            'width' => $rong,
            'height' => $cao,
            'webp' => $ban,
        ]);

        return true;
    }

    /**
     * Xoá ảnh gốc khỏi manifest và xoá các bản WebP của nó.
     *
     * Gọi khi admin xoá ảnh. Không gọi thì manifest cứ phình ra với
     * những đường dẫn trỏ vào hư không, và thư mục `rp/` giữ lại bản
     * WebP của những ảnh không còn ai dùng.
     */
    public function xoa(string $path): void
    {
        $disk = Storage::disk('public');

        foreach (ResponsiveImage::WIDTHS as $w) {
            $dich = ResponsiveImage::FOLDER . "/{$w}/" . preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);

            if ($disk->exists($dich)) {
                $disk->delete($dich);
            }
        }

        $this->ghiManifest($path, null);
    }

    /**
     * Ghi một mục vào manifest (hoặc xoá mục đó khi $thongTin là null).
     *
     * ĐỌC — SỬA — GHI LẠI CẢ TỆP, và có khoá.
     *
     * Manifest là MỘT tệp JSON cho toàn bộ ảnh. Hai admin tải ảnh lên
     * cùng lúc thì cả hai cùng đọc bản cũ, mỗi người thêm mục của mình,
     * và người ghi sau xoá mất mục của người ghi trước — ảnh đó lặng lẽ
     * mất bản tối ưu mà không ai biết.
     *
     * Khoá 10 giây là quá đủ cho một thao tác đọc-ghi tệp; chờ tối đa 5
     * giây rồi bỏ cuộc, vì thà không ghi được manifest (ảnh vẫn hiện,
     * chỉ là dùng bản gốc) còn hơn treo trang quản trị.
     *
     * @param  array{width:int, height:int, webp:array<int,string>}|null  $thongTin
     */
    private function ghiManifest(string $path, ?array $thongTin): void
    {
        $disk = Storage::disk('public');

        $khoa = \Illuminate\Support\Facades\Cache::lock('anh.manifest.ghi', 10);

        try {
            $khoa->block(5);

            $manifest = $disk->exists(ResponsiveImage::MANIFEST)
                ? (json_decode((string) $disk->get(ResponsiveImage::MANIFEST), true) ?: [])
                : [];

            if ($thongTin === null) {
                unset($manifest[$path]);
            } else {
                $manifest[$path] = $thongTin;
            }

            $disk->put(
                ResponsiveImage::MANIFEST,
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            );

            // Trang đang đệm manifest vĩnh viễn — đây là lúc DUY NHẤT nó đổi.
            ResponsiveImage::quenManifest();
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            Log::warning('Không lấy được khoá để ghi manifest ảnh', ['path' => $path]);
        } finally {
            optional($khoa)->release();
        }
    }

    /**
     * Resize và ghi ra một tệp WebP.
     *
     * Dùng GD chứ không Imagick: GD có sẵn trong mọi bản PHP thông
     * thường, còn Imagick thì phải cài thêm — và một tính năng chỉ chạy
     * trên máy đã cài đúng thứ là một tính năng sẽ hỏng ở máy khác.
     */
    private function sinhBan(
        string $nguon,
        string $dich,
        int $rongDich,
        int $rongGoc,
        int $caoGoc,
        int $chatLuong,
    ): bool {
        $anh = match (strtolower(pathinfo($nguon, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($nguon),
            'png' => @imagecreatefrompng($nguon),
            default => false,
        };

        if (! $anh) {
            return false;
        }

        $caoDich = (int) round($caoGoc * ($rongDich / $rongGoc));
        $moi = imagecreatetruecolor($rongDich, $caoDich);

        /*
         * Giữ phần trong suốt của PNG.
         *
         * Không làm thì nền trong suốt thành ĐEN — và với ảnh sản phẩm
         * tách nền thì đó là một khối đen giữa trang.
         */
        imagealphablending($moi, false);
        imagesavealpha($moi, true);

        imagecopyresampled($moi, $anh, 0, 0, 0, 0, $rongDich, $caoDich, $rongGoc, $caoGoc);

        if (! is_dir(dirname($dich))) {
            mkdir(dirname($dich), 0755, true);
        }

        $ok = imagewebp($moi, $dich, $chatLuong);

        imagedestroy($anh);
        imagedestroy($moi);

        return $ok;
    }
}
