<?php

namespace Tests\Support;

/** Dựng một tệp JPEG có khối EXIF GPS THẬT. */
trait TaoAnhCoGps
{
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

    protected function conGps(string $duongDan): bool
    {
        $exif = @exif_read_data($duongDan);

        return isset($exif['GPSLatitude']) || isset($exif['GPS']['GPSLatitude']);
    }
}
