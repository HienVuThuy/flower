<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Xoá TOẠ ĐỘ GPS khỏi video MP4 / MOV điện thoại quay.
 * ============================================================
 * Cùng lý do với ImageMetadataStripper: video quay bằng iPhone / Android ghi
 * vị trí quay (chuẩn ISO 6709, ví dụ "+10.7626+106.6602+012.000/") vào hộp
 * `moov` — đăng công khai là đăng luôn địa chỉ nhà người quay.
 *
 * KHÔNG CẦN ffmpeg (máy chủ không có): đọc cấu trúc hộp MP4, chỉ đọc vào hộp
 * siêu dữ liệu (`moov`, `udta`, `meta` ở tầng trên cùng), bỏ qua dữ liệu hình
 * `mdat`, rồi GHI ĐÈ chuỗi toạ độ bằng khoảng trắng CÙNG ĐỘ DÀI. Không đổi kích
 * thước hộp nào nên mọi con trỏ trong tệp vẫn đúng và video vẫn phát.
 *
 * Mẫu toạ độ chặt (vĩ độ 2 chữ số, kinh độ 3 chữ số, có phần thập phân, kết
 * thúc bằng "/") để không đụng nhầm dữ liệu nhị phân khác trong `moov`.
 *
 * WebM không có chuẩn ghi GPS phổ biến nên không cần xử lý.
 */
final class VideoMetadataStripper
{
    /** Hộp siêu dữ liệu lớn hơn mức này là bất thường — không đọc cả vào bộ nhớ. */
    private const TOI_DA_HOP = 32 * 1024 * 1024;

    private const TOA_DO = '#[+-]\d{2}\.\d{2,}[+-]\d{3}\.\d{2,}(?:[+-]\d+(?:\.\d+)?)?(?:CRS[^/]{0,40})?/#';

    /**
     * @param  string  $duongDan  đường dẫn trong đĩa `public`
     * @return int số chuỗi toạ độ đã xoá
     */
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
                    break; // Tệp hỏng hoặc không phải MP4: dừng, không ghi bừa.
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
