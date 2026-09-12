<?php

namespace App\Services\Media;

/**
 * Đọc link video của người dùng và trả về địa chỉ NHÚNG an toàn.
 * ============================================================
 * KHÔNG BAO GIỜ NHÚNG THẲNG CHUỖI NGƯỜI DÙNG DÁN VÀO.
 *
 * Một ô "link video" nhận nguyên chuỗi rồi đổ vào `<iframe src="...">` là một
 * lỗ chèn mã: `javascript:`, `data:text/html,<script>…`, hay một trang bất kỳ
 * giả làm trình phát để hỏi mật khẩu khách ngay trong trang cửa hàng.
 *
 * Ở đây chỉ nhận ĐÚNG hai nhà cung cấp, và chỉ lấy MÃ VIDEO khỏi link — địa
 * chỉ nhúng do chính lớp này dựng lại. Người ta dán gì đi nữa thì thứ đi vào
 * trang cũng chỉ là `https://www.youtube-nocookie.com/embed/<mã>`.
 *
 * YOUTUBE-NOCOOKIE, KHÔNG PHẢI YOUTUBE.COM: bản nocookie không đặt cookie theo
 * dõi cho tới khi khách thật sự bấm phát. Khách xem một chậu cây không có lý do
 * gì để bị Google gắn thẻ theo dõi.
 */
final class VideoLink
{
    /** Mã video: chữ, số, gạch ngang, gạch dưới — không gì khác. */
    private const MA_YOUTUBE = '/^[A-Za-z0-9_-]{6,20}$/';

    private const MA_VIMEO = '/^[0-9]{6,15}$/';

    /**
     * Đổi link người dùng dán thành địa chỉ nhúng, hoặc null nếu không nhận.
     */
    public static function nhung(?string $link): ?string
    {
        $link = trim((string) $link);

        if ($link === '') {
            return null;
        }

        $phan = parse_url($link);

        // Thiếu host, hoặc giao thức không phải http(s): bỏ.
        if (! is_array($phan) || empty($phan['host'])) {
            return null;
        }

        if (isset($phan['scheme']) && ! in_array(strtolower($phan['scheme']), ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower(preg_replace('/^www\./', '', $phan['host']));
        $duong = trim($phan['path'] ?? '', '/');

        parse_str($phan['query'] ?? '', $thamSo);

        $ma = match (true) {
            $host === 'youtu.be' => $duong,
            in_array($host, ['youtube.com', 'm.youtube.com', 'youtube-nocookie.com'], true) => match (true) {
                isset($thamSo['v']) => (string) $thamSo['v'],
                str_starts_with($duong, 'embed/') => substr($duong, 6),
                str_starts_with($duong, 'shorts/') => substr($duong, 7),
                default => '',
            },
            default => null,
        };

        if ($ma !== null) {
            return preg_match(self::MA_YOUTUBE, $ma)
                ? 'https://www.youtube-nocookie.com/embed/' . $ma
                : null;
        }

        if (in_array($host, ['vimeo.com', 'player.vimeo.com'], true)) {
            // vimeo.com/123456789 hoặc player.vimeo.com/video/123456789
            $so = preg_replace('#^video/#', '', $duong);

            return preg_match(self::MA_VIMEO, $so)
                ? 'https://player.vimeo.com/video/' . $so
                : null;
        }

        return null;
    }

    /** Link có dùng được không — dùng cho tầng kiểm tra biểu mẫu. */
    public static function hopLe(?string $link): bool
    {
        return self::nhung($link) !== null;
    }
}
