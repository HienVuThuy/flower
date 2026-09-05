<?php

namespace App\Services\Shop;

use App\Models\Setting;

/**
 * Cam kết dịch vụ của cửa hàng.
 * ============================================================
 * VÌ SAO KHÔNG VIẾT CỨNG TRONG BLADE:
 * Những câu như "giao trong 2 giờ", "hoa tươi 3+ ngày", "miễn phí giao
 * 63 tỉnh" là LỜI HỨA KINH DOANH. Chỉ chủ cửa hàng mới biết mình làm
 * được gì; lập trình viên viết sẵn vào giao diện là bịa ra cam kết thay
 * cho họ, và khi cửa hàng không giữ được lời thì phải sửa code mới gỡ
 * xuống được.
 *
 * Vì vậy: admin tự nhập trong Cài đặt, không nhập thì KHÔNG hiện gì.
 * Mặc định là danh sách RỖNG — cố ý không cài sẵn câu mẫu nào.
 *
 * Lưu trong bảng settings dạng JSON thay vì tạo bảng riêng: đây là cấu
 * hình của cửa hàng, chỉ có một bản, không cần quan hệ với gì cả
 * (Guide §27 chống tạo bảng thừa).
 */
class ServiceCommitments
{
    public const KEY = 'service_commitments';

    /** Số dòng tối đa admin có thể khai. */
    public const MAX = 6;

    /**
     * Biểu tượng cho phép chọn — phải là icon đã có trong SVG sprite,
     * nếu không sẽ hiện ô trống.
     *
     * @return array<string, string>
     */
    public static function icons(): array
    {
        return [
            'check-circle' => 'Dấu tích',
            'shield-lock' => 'Khiên bảo đảm',
            'telephone' => 'Điện thoại',
            'flower1' => 'Bông hoa',
            'flower2' => 'Cành lá',
            'geo-alt' => 'Định vị',
            'arrow-repeat' => 'Vòng lặp',
            'tags' => 'Thẻ giá',
            'envelope-paper' => 'Thiệp',
        ];
    }

    /**
     * Danh sách cam kết đang khai, đã bỏ dòng trống.
     *
     * @return array<int, array{icon: string, title: string, note: string}>
     */
    public static function all(): array
    {
        $raw = Setting::get(self::KEY);

        if (! $raw) {
            return [];
        }

        $rows = json_decode($raw, true);

        if (! is_array($rows)) {
            return [];
        }

        $icons = self::icons();
        $out = [];

        foreach ($rows as $row) {
            $title = trim($row['title'] ?? '');

            // Không có tiêu đề thì không phải một cam kết.
            if ($title === '') {
                continue;
            }

            $out[] = [
                // Biểu tượng lạ (do sửa tay trong CSDL) rơi về dấu tích.
                'icon' => isset($icons[$row['icon'] ?? '']) ? $row['icon'] : 'check-circle',
                'title' => $title,
                'note' => trim($row['note'] ?? ''),
            ];
        }

        return array_slice($out, 0, self::MAX);
    }

    /**
     * Lưu lại danh sách. Dòng thiếu tiêu đề bị loại ngay tại đây.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function save(array $rows): void
    {
        $icons = self::icons();
        $clean = [];

        foreach (array_slice($rows, 0, self::MAX) as $row) {
            $title = trim((string) ($row['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            $clean[] = [
                'icon' => isset($icons[$row['icon'] ?? '']) ? $row['icon'] : 'check-circle',
                'title' => $title,
                'note' => trim((string) ($row['note'] ?? '')),
            ];
        }

        Setting::set(self::KEY, $clean ? json_encode($clean, JSON_UNESCAPED_UNICODE) : null);
    }
}
