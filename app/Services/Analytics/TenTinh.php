<?php

namespace App\Services\Analytics;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Gộp các cách viết khác nhau của cùng một tỉnh/thành.
 *
 * Dữ liệu thật có "Hà Nội" và "Thành phố Hà Nội", "Bắc Ninh" (địa chỉ cũ gõ
 * tay) bên cạnh tên có tiền tố (địa chỉ mới chọn từ danh sách GHN). Chỉ bỏ
 * TIỀN TỐ HÀNH CHÍNH và khác biệt hoa/thường — không gộp hai tỉnh khác tên,
 * không đoán theo sáp nhập hành chính.
 */
final class TenTinh
{
    private const TIEN_TO = ['thành phố ', 'tp. ', 'tp.', 'tp ', 'tỉnh '];

    public static function khoa(?string $ten): string
    {
        $k = Str::lower(trim((string) $ten));

        foreach (self::TIEN_TO as $tt) {
            if (str_starts_with($k, $tt)) {
                $k = trim(substr($k, strlen($tt)));
                break;
            }
        }

        return $k === '' ? '(không rõ)' : $k;
    }

    /**
     * Nhãn hiển thị cho một nhóm: cách viết gặp NHIỀU NHẤT trong nhóm.
     *
     * @param  Collection<int, string|null>  $cacTen
     */
    public static function nhan(Collection $cacTen): string
    {
        $pho = $cacTen->map(fn ($t) => trim((string) $t))->filter()->countBy()->sortDesc();

        return (string) ($pho->keys()->first() ?? '(không rõ tỉnh)');
    }
}
