<?php

namespace Tests\Feature\Community;

use App\Services\Media\VideoMetadataStripper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Video điện thoại mang toạ độ quay trong hộp `moov` — phải bị xoá, và tệp vẫn đúng cấu trúc (không đổi kích… */
class VideoMetadataStripperTest extends TestCase
{
    private function hop(string $loai, string $noiDung): string
    {
        return pack('N', 8 + strlen($noiDung)) . $loai . $noiDung;
    }

    private function mp4(string $toaDo): string
    {
        return $this->hop('ftyp', 'isom0000isomiso2')
            . $this->hop('moov', $this->hop('mvhd', str_repeat("\0", 20)) . $this->hop('udta', $this->hop("\xA9xyz", "\x00\x12\x15\xC7" . $toaDo)))
            . $this->hop('mdat', 'khung-hinh+10.7626+106.6602/khung-hinh');
    }

    #[Test]
    public function xoa_toa_do_trong_moov_giu_nguyen_kich_thuoc_va_mdat(): void
    {
        $tep = tempnam(sys_get_temp_dir(), 'vid');
        file_put_contents($tep, $this->mp4('+10.7626+106.6602+012.345/'));
        $truoc = filesize($tep);

        $this->assertSame(1, app(VideoMetadataStripper::class)->tuocTep($tep));

        $sau = file_get_contents($tep);
        $this->assertSame($truoc, strlen($sau), 'Kích thước tệp phải giữ nguyên');
        $this->assertStringNotContainsString('+106.6602+012.345/', $sau, 'Toạ độ trong moov vẫn còn');
        $this->assertStringContainsString('khung-hinh+10.7626+106.6602/khung-hinh', $sau, 'Không được sửa dữ liệu hình (mdat)');
        $this->assertStringContainsString('moov', $sau);

        unlink($tep);
    }

    #[Test]
    public function tep_khong_phai_mp4_thi_khong_ghi_bua(): void
    {
        $tep = tempnam(sys_get_temp_dir(), 'vid');
        $noiDung = 'khong phai video +10.7626+106.6602/';
        file_put_contents($tep, $noiDung);

        $this->assertSame(0, app(VideoMetadataStripper::class)->tuocTep($tep));
        $this->assertSame($noiDung, file_get_contents($tep));

        unlink($tep);
    }
}
