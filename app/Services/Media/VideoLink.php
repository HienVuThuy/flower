<?php

namespace App\Services\Media;

/** Đọc link video của người dùng và trả về địa chỉ NHÚNG an toàn. */
final class VideoLink
{
    private const MA_YOUTUBE = '/^[A-Za-z0-9_-]{6,20}$/';

    private const MA_VIMEO = '/^[0-9]{6,15}$/';

    public static function nhung(?string $link): ?string
    {
        $link = trim((string) $link);

        if ($link === '') {
            return null;
        }

        $phan = parse_url($link);

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
            $so = preg_replace('#^video/#', '', $duong);

            return preg_match(self::MA_VIMEO, $so)
                ? 'https://player.vimeo.com/video/' . $so
                : null;
        }

        return null;
    }

    public static function hopLe(?string $link): bool
    {
        return self::nhung($link) !== null;
    }
}
