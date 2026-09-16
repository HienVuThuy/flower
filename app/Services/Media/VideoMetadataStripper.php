<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Xoá TOẠ ĐỘ GPS khỏi video MP4 / MOV điện thoại quay. */
final class VideoMetadataStripper
{
    private const TOI_DA_HOP = 32 * 1024 * 1024;

    private const TOA_DO = '#[+-]\d{2}\.\d{2,}[+-]\d{3}\.\d{2,}(?:[+-]\d+(?:\.\d+)?)?(?:CRS[^/]{0,40})?/#';

    public function tuoc(string $duongDan): int
    {
        return $this->tuocTep(Storage::disk('public')->path($duongDan));
    }

    public function tuocTep(string $tep): int
    {
        $fh = @fopen($tep, 'r+b');

        if ($fh === false) {
            throw new RuntimeException('Không mở được tệp video để xoá vị trí.');
        }

        $kichThuoc = filesize($tep);
        $viTri = 0;
        $dem = 0;

        try {
            while ($viTri + 8 <= $kichThuoc) {
                fseek($fh, $viTri);
                $dau = fread($fh, 8);

                if (strlen($dau) < 8) {
                    break;
                }

                $doDai = unpack('N', substr($dau, 0, 4))[1];
                $loai = substr($dau, 4, 4);
                $dauHop = 8;

                if ($doDai === 1) {
                    $doDai = unpack('J', fread($fh, 8))[1];
                    $dauHop = 16;
                } elseif ($doDai === 0) {
                    $doDai = $kichThuoc - $viTri;
                }

                if ($doDai < $dauHop || $viTri + $doDai > $kichThuoc) {
                    break;
                }

                $noiDung = $doDai - $dauHop;

                if (in_array($loai, ['moov', 'udta', 'meta'], true) && $noiDung <= self::TOI_DA_HOP) {
                    fseek($fh, $viTri + $dauHop);
                    $du = fread($fh, $noiDung);
                    $sach = preg_replace_callback(self::TOA_DO, fn ($m) => str_repeat(' ', strlen($m[0])), $du, -1, $so);

                    if ($so > 0) {
                        fseek($fh, $viTri + $dauHop);
                        fwrite($fh, $sach);
                        $dem += $so;
                    }
                }

                $viTri += $doDai;
            }
        } finally {
            fclose($fh);
        }

        return $dem;
    }
}
