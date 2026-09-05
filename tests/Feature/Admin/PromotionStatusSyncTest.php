<?php

namespace Tests\Feature\Admin;

use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Models\ActivityLog;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Nhãn trạng thái khuyến mại phải tự đi theo ngày đã đặt.
 * ============================================================
 * Đây KHÔNG phải bài kiểm thử về giá: `scopeActiveNow()` đã lọc theo
 * ngày nên giá bán vẫn luôn đúng dù nhãn có sai. Bài này giữ cho TRANG
 * QUẢN TRỊ nói đúng — thứ admin dựa vào để biết cửa hàng mình đang chạy
 * chương trình nào.
 *
 * Phần dễ sai nhất không phải hai chiều tự động, mà là hai trạng thái
 * KHÔNG ĐƯỢC ĐỘNG VÀO: `Nháp` và `Tạm dừng` là ý định của con người.
 */
class PromotionStatusSyncTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tạo thẳng bằng create() vì Promotion chưa có factory.
     *
     * Không dựng factory ở đây: bài này chỉ cần bốn trường, còn một
     * factory dùng chung phải nghĩ cho mọi loại khuyến mại (theo %, theo
     * số tiền, theo khung giờ, theo thứ trong tuần) — việc đó thuộc về
     * lúc có bài kiểm thử thứ hai cần tới nó.
     */
    private function khuyenMai(PromotionStatus $status, mixed $batDau, mixed $ketThuc): Promotion
    {
        $dem = Promotion::count() + 1;

        return Promotion::create([
            'name' => 'Chương trình thử ' . $dem,
            'slug' => 'chuong-trinh-thu-' . $dem,
            'type' => PromotionType::Percent,
            'discount_value' => '10.00',
            'status' => $status,
            'starts_at' => $batDau,
            'ends_at' => $ketThuc,
        ]);
    }

    #[Test]
    public function toi_ngay_bat_dau_thi_chuyen_sang_dang_dien_ra(): void
    {
        $km = $this->khuyenMai(PromotionStatus::Scheduled, now()->subHour(), now()->addDays(3));

        $this->artisan('khuyen-mai:cap-nhat-trang-thai')->assertSuccessful();

        $this->assertSame(PromotionStatus::Active, $km->refresh()->status);
    }

    #[Test]
    public function qua_ngay_ket_thuc_thi_chuyen_sang_da_ket_thuc(): void
    {
        $km = $this->khuyenMai(PromotionStatus::Active, now()->subDays(5), now()->subHour());

        $this->artisan('khuyen-mai:cap-nhat-trang-thai')->assertSuccessful();

        $this->assertSame(PromotionStatus::Ended, $km->refresh()->status);
    }

    #[Test]
    public function khong_duoc_bat_lai_chuong_trinh_admin_da_tam_dung(): void
    {
        /*
         * ĐIỀU QUAN TRỌNG NHẤT TỆP NÀY.
         *
         * "Tạm dừng" nghĩa là admin vừa cố ý tắt nó đi — có thể vì hết
         * hàng, vì tính nhầm giá, vì sếp bảo dừng. Hôm nay vẫn nằm trong
         * khoảng ngày đã đặt, nên một lệnh chỉ nhìn ngày sẽ bật lại.
         *
         * Máy bật lại thứ người vừa tắt là máy huỷ quyết định của người,
         * và ở đây nó huỷ một quyết định về giá bán.
         */
        $km = $this->khuyenMai(PromotionStatus::Paused, now()->subDays(2), now()->addDays(3));

        $this->artisan('khuyen-mai:cap-nhat-trang-thai')->assertSuccessful();

        $this->assertSame(PromotionStatus::Paused, $km->refresh()->status);
    }

    #[Test]
    public function khong_dong_vao_ban_nhap(): void
    {
        // Nháp = "tôi chưa muốn chạy cái này", kể cả khi đã điền ngày.
        $km = $this->khuyenMai(PromotionStatus::Draft, now()->subDays(2), now()->addDays(3));

        $this->artisan('khuyen-mai:cap-nhat-trang-thai')->assertSuccessful();

        $this->assertSame(PromotionStatus::Draft, $km->refresh()->status);
    }

    #[Test]
    public function chuong_trinh_bi_bo_lo_ca_ky_thi_di_thang_sang_da_ket_thuc(): void
    {
        /*
         * Lệnh không chạy suốt kỳ khuyến mại (máy tắt, quên bật
         * schedule:work). Lúc chạy lại thì chương trình đã qua cả ngày
         * bắt đầu lẫn ngày kết thúc.
         *
         * Bật lên rồi tắt ngay trong cùng một lượt là ghi hai dòng nhật
         * ký cho một chuyện, và trong một khoảnh khắc nó thành "đang
         * diễn ra" — đủ để một lượt truy vấn khác đọc trúng.
         */
        $km = $this->khuyenMai(PromotionStatus::Scheduled, now()->subDays(9), now()->subDays(2));

        $this->artisan('khuyen-mai:cap-nhat-trang-thai')->assertSuccessful();

        $this->assertSame(PromotionStatus::Ended, $km->refresh()->status);
        $this->assertSame(1, ActivityLog::where('action', 'khuyen-mai.tu-dong-doi-trang-thai')->count());
    }

    #[Test]
    public function chua_toi_ngay_bat_dau_thi_de_yen(): void
    {
        $km = $this->khuyenMai(PromotionStatus::Scheduled, now()->addDays(2), now()->addDays(9));

        $this->artisan('khuyen-mai:cap-nhat-trang-thai')->assertSuccessful();

        $this->assertSame(PromotionStatus::Scheduled, $km->refresh()->status);
    }

    #[Test]
    public function viec_may_lam_cung_phai_co_trong_nhat_ky(): void
    {
        // Không có dòng nhật ký thì admin mở lên thấy chương trình đã
        // tắt và câu hỏi đầu tiên là "ai tắt của tôi?".
        $km = $this->khuyenMai(PromotionStatus::Active, now()->subDays(5), now()->subHour());

        $this->artisan('khuyen-mai:cap-nhat-trang-thai')->assertSuccessful();

        $log = ActivityLog::where('action', 'khuyen-mai.tu-dong-doi-trang-thai')->firstOrFail();

        $this->assertStringContainsString($km->name, $log->description);
        $this->assertStringContainsString('Đã kết thúc', $log->description);
    }
}
