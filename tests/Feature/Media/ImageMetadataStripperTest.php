<?php

namespace Tests\Feature\Media;

use App\Services\Media\ImageMetadataStripper;
use App\Services\Media\ImageStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Tước metadata khỏi ảnh tải lên. */
class ImageMetadataStripperTest extends TestCase
{
    use RefreshDatabase;

    private function anhCoGps(): string
    {
        $anh = imagecreatetruecolor(120, 80);
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

    #[Test]
    public function anh_mau_dung_de_kiem_thu_THAT_SU_co_gps(): void
    {
        $tam = $this->anhCoGps();

        $exif = @exif_read_data($tam);

        $this->assertIsArray($exif, 'Tệp mẫu không đọc được EXIF — bài kiểm thử đang vô hiệu.');
        $this->assertTrue(
            isset($exif['GPSLatitude']) || isset($exif['GPS']['GPSLatitude']),
            'Tệp mẫu KHÔNG có GPS — mọi bài bên dưới sẽ xanh mà không kiểm gì.',
        );

        @unlink($tam);
    }

    #[Test]
    public function luu_qua_ImageStore_thi_gps_bien_mat(): void
    {
        Storage::fake('public');

        $tam = $this->anhCoGps();

        $path = app(ImageStore::class)->luu(
            new UploadedFile($tam, 'ban-cong.jpg', 'image/jpeg', null, true),
            'community',
        );

        $exif = @exif_read_data(Storage::disk('public')->path($path));

        $this->assertFalse(
            isset($exif['GPSLatitude']) || isset($exif['GPS']['GPSLatitude']),
            'TOẠ ĐỘ GPS VẪN CÒN trong ảnh đã lưu — đây là địa chỉ nhà của khách.',
        );

        $conLai = app(ImageMetadataStripper::class)->metadataConLai($path);

        $this->assertSame(
            [],
            $conLai,
            'Ảnh còn metadata sau khi lưu: ' . implode(', ', $conLai),
        );
    }

    #[Test]
    public function tuoc_xong_anh_van_mo_duoc_va_dung_kich_thuoc(): void
    {
        Storage::fake('public');

        $tam = $this->anhCoGps();

        $path = app(ImageStore::class)->luu(
            new UploadedFile($tam, 'ban-cong.jpg', 'image/jpeg', null, true),
            'community',
        );

        $co = @getimagesize(Storage::disk('public')->path($path));

        $this->assertIsArray($co, 'Ảnh sau khi tước không mở được nữa.');
        $this->assertSame(120, $co[0]);
        $this->assertSame(80, $co[1]);
    }

    #[Test]
    public function anh_khong_co_metadata_van_luu_binh_thuong(): void
    {
        Storage::fake('public');

        $path = app(ImageStore::class)->luu(
            UploadedFile::fake()->image('sach.jpg', 200, 150),
            'community',
        );

        Storage::disk('public')->assertExists($path);
        $this->assertSame([], app(ImageMetadataStripper::class)->metadataConLai($path));
    }

    #[Test]
    public function tep_khong_phai_anh_thi_bo_qua_chu_khong_vo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('community/khong-phai-anh.txt', 'xin chao');

        $this->assertFalse(app(ImageMetadataStripper::class)->tuoc('community/khong-phai-anh.txt'));
        $this->assertFalse(app(ImageMetadataStripper::class)->tuoc('community/khong-ton-tai.jpg'));
    }

    #[Test]
    public function png_trong_suot_khong_bi_bien_thanh_nen_den(): void
    {
        Storage::fake('public');

        $anh = imagecreatetruecolor(60, 60);
        imagealphablending($anh, false);
        imagesavealpha($anh, true);
        imagefill($anh, 0, 0, imagecolorallocatealpha($anh, 0, 0, 0, 127));

        $tam = tempnam(sys_get_temp_dir(), 'png') . '.png';
        imagepng($anh, $tam);
        imagedestroy($anh);

        Storage::disk('public')->put('community/trong-suot.png', file_get_contents($tam));
        app(ImageMetadataStripper::class)->tuoc('community/trong-suot.png');

        $sau = imagecreatefrompng(Storage::disk('public')->path('community/trong-suot.png'));
        $mau = imagecolorsforindex($sau, imagecolorat($sau, 30, 30));
        imagedestroy($sau);
        @unlink($tam);

        $this->assertSame(127, $mau['alpha'], 'Nền trong suốt đã bị tô đen.');
    }
}
