<?php

namespace Tests\Support;

/**
 * Dựng một tệp JPEG có khối EXIF GPS THẬT.
 * ============================================================
 * VÌ SAO KHÔNG DÙNG `UploadedFile::fake()->image()`.
 *
 * Ảnh giả của Laravel không có EXIF. Một bài kiểm thử khẳng định "ảnh đã
 * bị tước GPS" mà dùng ảnh giả thì XANH kể cả khi xoá sạch lớp tước —
 * vì không có gì để tước ngay từ đầu.
 *
 * Đã đo: chèn đột biến cho `CommunityController` gọi thẳng
 * `$file->store()` (bỏ qua ImageStore, tức bỏ qua bước tước metadata)
 * thì bài vẫn xanh. Đúng cái bẫy đã ghi ở QĐ-124.
 *
 * PHP không có hàm GHI exif, nên khối APP1 dưới đây được ghép bằng tay ở
 * mức byte. Đổi lại là bài kiểm thử đo trên một tệp giống hệt thứ điện
 * thoại sinh ra.
 */
trait TaoAnhCoGps
{
    /** @return string đường dẫn tệp tạm */
    protected function anhCoGps(int $rong = 120, int $cao = 80): string
    {
        $anh = imagecreatetruecolor($rong, $cao);
        imagefill($anh, 0, 0, imagecolorallocate($anh, 40, 120, 60));

        $tam = tempnam(sys_get_temp_dir(), 'gps') . '.jpg';
        imagejpeg($anh, $tam, 92);
        imagedestroy($anh);

        $this->chenExifGps($tam);

        return $tam;
    }

    /**
     * Chèn khối APP1/EXIF tối thiểu có GPS ngay sau SOI của JPEG.
     *
     * Toạ độ dùng ở đây là 21°01'40"N 105°45'50"E — Trường Đại học Tài
     * nguyên và Môi trường Hà Nội, một địa điểm THẬT, để bài kiểm thử
     * phản ánh đúng thứ nó phòng: một địa chỉ đọc được từ bức ảnh.
     */
    private function chenExifGps(string $duongDan): void
    {
        $tiff = "II\x2A\x00" . pack('V', 8);

        $ifd0 = pack('v', 1)
            . pack('v', 0x8825) . pack('v', 4) . pack('V', 1) . pack('V', 26)
            . pack('V', 0);

        $viTriDuLieu = 26 + 2 + 2 * 12 + 4;

        $gpsIfd = pack('v', 2)
            . pack('v', 0x0002) . pack('v', 5) . pack('V', 3) . pack('V', $viTriDuLieu)
            . pack('v', 0x0004) . pack('v', 5) . pack('V', 3) . pack('V', $viTriDuLieu + 24)
            . pack('V', 0);

        $viDo = pack('VV', 21, 1) . pack('VV', 1, 1) . pack('VV', 40, 1);
        $kinhDo = pack('VV', 105, 1) . pack('VV', 45, 1) . pack('VV', 50, 1);

        $than = $tiff . str_pad($ifd0, 26 - 8, "\x00") . $gpsIfd . $viDo . $kinhDo;
        $app1 = "Exif\x00\x00" . $than;
        $khoi = "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1;

        $jpeg = file_get_contents($duongDan);
        file_put_contents($duongDan, substr($jpeg, 0, 2) . $khoi . substr($jpeg, 2));
    }

    /** Tệp này còn toạ độ GPS đọc được không. */
    protected function conGps(string $duongDan): bool
    {
        $exif = @exif_read_data($duongDan);

        return isset($exif['GPSLatitude']) || isset($exif['GPS']['GPSLatitude']);
    }
}
