<?php

namespace Tests\Feature\Media;

use App\Services\Media\ImageMetadataStripper;
use App\Services\Media\ImageStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tước metadata khỏi ảnh tải lên.
 * ============================================================
 * ĐÂY LÀ BÀI VỀ QUYỀN RIÊNG TƯ, KHÔNG PHẢI VỀ XỬ LÝ ẢNH.
 *
 * Ảnh chụp bằng điện thoại mang theo toạ độ GPS chính xác tới vài mét —
 * tức là địa chỉ nhà người chụp. Khách đăng ảnh cây trên ban công lên
 * mục "Góc cây của bạn" là đăng luôn chỗ mình ở, nếu không ai tước.
 *
 * Bài này dựng một tệp JPEG có khối EXIF GPS THẬT (không giả lập, không
 * mock) rồi khẳng định sau khi lưu qua `ImageStore` thì đọc lại không
 * còn toạ độ nào.
 */
class ImageMetadataStripperTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Dựng một JPEG có EXIF GPS thật.
     *
     * PHP không có hàm GHI exif, nên khối APP1 được ghép bằng tay ở mức
     * byte. Đổi lại là bài kiểm thử này đo trên một tệp giống hệt thứ
     * điện thoại sinh ra, chứ không đo trên một giả định.
     */
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

    /**
     * Chèn một khối APP1/EXIF tối thiểu có GPS vào ngay sau SOI của JPEG.
     *
     * Cấu trúc: SOI (FFD8) + APP1 (FFE1, dài, "Exif\0\0", TIFF header,
     * IFD0 trỏ tới GPS IFD).
     */
    private function chenExifGps(string $duongDan): void
    {
        // --- TIFF header: little-endian, magic 42, IFD0 ở offset 8 ---
        $tiff = "II\x2A\x00" . pack('V', 8);

        // IFD0: 1 mục — GPSInfoIFDPointer (0x8825), kiểu LONG, trỏ tới offset 26
        $ifd0 = pack('v', 1)
            . pack('v', 0x8825) . pack('v', 4) . pack('V', 1) . pack('V', 26)
            . pack('V', 0);

        /*
         * GPS IFD: 2 mục — vĩ độ và kinh độ, kiểu RATIONAL (5), mỗi cái
         * 3 phân số (độ/phút/giây). Dữ liệu đặt sau bảng mục.
         *
         * Toạ độ dùng ở đây là 21°01'40"N 105°45'50"E — Trường Đại học
         * Tài nguyên và Môi trường Hà Nội, tức là một địa điểm THẬT, để
         * bài kiểm thử phản ánh đúng thứ nó phòng.
         */
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
        /*
         * BẢO HIỂM CHO CHÍNH BỘ BÀI NÀY.
         *
         * Nếu tệp mẫu không có GPS ngay từ đầu thì mọi bài "đã xoá GPS"
         * bên dưới đều xanh một cách vô nghĩa — chúng sẽ xanh kể cả khi
         * xoá sạch lớp tước metadata.
         *
         * Đây đúng là cái bẫy đã ghi ở QĐ-124: một bài chỉ đi qua nhánh
         * không có gì để kiểm thì không bảo vệ ai.
         */
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

        /*
         * ĐO BẰNG `exif_read_data` THÔ, không chỉ bằng hàm kiểm của chính
         * dự án.
         *
         * Bản đầu chỉ gọi `metadataConLai()`. Chèn đột biến cho hàm đó
         * luôn trả về mảng rỗng thì bài VẪN XANH — nó đang tin vào chính
         * thứ nó phải kiểm. Đọc thẳng bằng hàm của PHP thì không có
         * đường nào lách.
         */
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
        // Tước metadata mà làm hỏng ảnh thì không phải là tước, là xoá.
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
        /*
         * `tuoc()` được gọi cho MỌI tệp đi qua ImageStore. Định dạng lạ
         * phải trả về false lặng lẽ — ném lỗi ở đây là làm hỏng cả việc
         * tải ảnh lên vì một bước phụ.
         */
        Storage::fake('public');
        Storage::disk('public')->put('community/khong-phai-anh.txt', 'xin chao');

        $this->assertFalse(app(ImageMetadataStripper::class)->tuoc('community/khong-phai-anh.txt'));
        $this->assertFalse(app(ImageMetadataStripper::class)->tuoc('community/khong-ton-tai.jpg'));
    }

    #[Test]
    public function png_trong_suot_khong_bi_bien_thanh_nen_den(): void
    {
        /*
         * Lỗi kinh điển khi ghi lại ảnh bằng GD: không giữ kênh alpha thì
         * nền trong suốt thành ĐEN. Với ảnh sản phẩm tách nền, đó là một
         * khối đen giữa trang.
         */
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
