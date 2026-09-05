<?php

namespace App\Services\Theme;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Bộ ảnh hero do ADMIN tự khai, cho từng theme.
 * ============================================================
 * config/theme.php vẫn giữ một bộ ảnh mặc định cho mỗi theme — đó là
 * thứ website hiển thị khi cửa hàng chưa tự khai gì. Lớp này là lớp
 * ĐÈ LÊN: admin tải ảnh của mình lên thì dùng ảnh của họ.
 *
 * VÌ SAO KHÔNG SỬA config/theme.php:
 * Ảnh do admin chọn là DỮ LIỆU của cửa hàng, thay đổi lúc nào cũng
 * được. config là mã nguồn — sửa nó phải deploy lại, và trên máy chủ
 * thật thì thư mục mã nguồn thường không cho ghi.
 *
 * VÌ SAO LƯU VÀO bảng settings CHỨ KHÔNG TẠO BẢNG RIÊNG:
 * Đây là cấu hình của cửa hàng, chỉ có một bản, không có quan hệ với
 * bảng nào khác (Guide §27 chống tạo bảng thừa).
 *
 * Tệp ảnh nằm ở storage/app/public/hero/ — cùng chỗ với ảnh sản phẩm
 * admin tải lên, không phải resources/ (thư mục của Vite).
 */
class HeroImages
{
    public const KEY = 'hero_images';

    /** Thư mục trong disk `public`. */
    public const DIR = 'hero';

    /**
     * Số ảnh tối đa mỗi theme.
     *
     * Có giới hạn vì TẤT CẢ ảnh của theme đang bật đều được nhúng vào
     * trang chủ — ảnh đầu tải ngay, các ảnh sau lazy. Không chặn thì
     * một hôm nào đó trang chủ kéo về vài chục megabyte. 10 ảnh đã là
     * một vòng quay hơn một phút ở nhịp 6 giây.
     */
    public const MAX = 10;

    /** Định dạng chấp nhận khi tải lên. */
    public const MIMES = ['jpg', 'jpeg', 'png', 'webp'];

    /** Dung lượng tối đa mỗi ảnh (KB). */
    public const MAX_KB = 3072;

    /**
     * Toàn bộ cấu hình đã lưu: [themeKey => [['path' =>, 'alt' =>], ...]].
     *
     * @return array<string, array<int, array{path: string, alt: string}>>
     */
    public static function all(): array
    {
        $raw = Setting::get(self::KEY);

        if (! $raw) {
            return [];
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Ảnh admin đã khai cho một theme, đã bỏ những mục trỏ tới tệp
     * KHÔNG CÒN TỒN TẠI.
     *
     * Kiểm tra tệp là cần thiết: ai đó xoá tay trong thư mục storage
     * thì bản ghi vẫn còn, và trang chủ sẽ hiện một ô ảnh vỡ.
     *
     * @return array<int, array{path: string, alt: string, url: string}>
     */
    public static function forTheme(string $themeKey): array
    {
        $rows = self::all()[$themeKey] ?? [];
        $disk = Storage::disk('public');
        $out = [];

        foreach ($rows as $row) {
            $path = is_array($row) ? ($row['path'] ?? '') : '';

            if ($path === '' || ! $disk->exists($path)) {
                continue;
            }

            $out[] = [
                'path' => $path,
                'alt' => is_array($row) ? (string) ($row['alt'] ?? '') : '',
                'url' => $disk->url($path),
            ];
        }

        return array_slice($out, 0, self::MAX);
    }

    /**
     * Lưu lại danh sách cho MỘT theme.
     *
     * @param  array<int, array{path: string, alt?: string}>  $keep   ảnh cũ giữ lại
     * @param  array<int, UploadedFile>                       $files  ảnh mới tải lên
     * @return int số ảnh cuối cùng của theme này
     */
    public static function saveTheme(string $themeKey, array $keep, array $files): int
    {
        $all = self::all();
        $before = $all[$themeKey] ?? [];

        $rows = [];

        foreach ($keep as $item) {
            $path = trim((string) ($item['path'] ?? ''));

            /*
             * Chỉ nhận lại đường dẫn ĐÃ CÓ trong bản ghi trước đó.
             *
             * Trường path nằm trong form dưới dạng input ẩn, mà input ẩn
             * thì sửa được. Không đối chiếu thì người ta gửi lên
             * "../../.env" hay đường dẫn tới ảnh của theme khác cũng lọt.
             */
            if ($path === '' || ! self::isKnownPath($before, $path)) {
                continue;
            }

            $rows[] = [
                'path' => $path,
                'alt' => trim((string) ($item['alt'] ?? '')),
            ];
        }

        foreach ($files as $file) {
            if (count($rows) >= self::MAX) {
                break;
            }

            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $rows[] = [
                // store() tự sinh tên ngẫu nhiên -> không đoán được đường
                // dẫn, và không ghi đè ảnh trùng tên của người khác.
                'path' => app(\App\Services\Media\ImageStore::class)->luu($file, self::DIR),
                'alt' => '',
            ];
        }

        $rows = array_slice($rows, 0, self::MAX);

        // Ảnh bị bỏ ra khỏi danh sách thì xoá luôn tệp, tránh rác tồn đọng.
        self::deleteOrphans($before, $rows);

        $all[$themeKey] = $rows;

        // Theme không còn ảnh nào thì bỏ hẳn khoá, để dữ liệu không phình
        // ra toàn mảng rỗng.
        $all = array_filter($all, fn ($v) => ! empty($v));

        Setting::set(self::KEY, $all ? json_encode($all, JSON_UNESCAPED_UNICODE) : null);

        return count($rows);
    }

    /** @param array<int, mixed> $rows */
    private static function isKnownPath(array $rows, string $path): bool
    {
        foreach ($rows as $row) {
            if (is_array($row) && ($row['path'] ?? null) === $path) {
                return true;
            }
        }

        return false;
    }

    /**
     * Xoá tệp của những ảnh đã bị loại khỏi danh sách.
     *
     * @param  array<int, mixed>  $before
     * @param  array<int, array{path: string}>  $after
     */
    private static function deleteOrphans(array $before, array $after): void
    {
        $keptPaths = array_column($after, 'path');
        $disk = Storage::disk('public');

        foreach ($before as $row) {
            $path = is_array($row) ? ($row['path'] ?? '') : '';

            if ($path === '' || in_array($path, $keptPaths, true)) {
                continue;
            }

            /*
             * Chỉ xoá tệp nằm trong thư mục của chính tính năng này.
             * Nếu bản ghi vì lý do nào đó trỏ ra ngoài, thà để lại một
             * tệp rác còn hơn xoá nhầm ảnh sản phẩm.
             */
            if (str_starts_with($path, self::DIR . '/') && $disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }
}
