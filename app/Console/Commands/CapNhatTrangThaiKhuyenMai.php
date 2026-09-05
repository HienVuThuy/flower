<?php

namespace App\Console\Commands;

use App\Enums\PromotionStatus;
use App\Models\Promotion;
use App\Services\Audit\ActivityLogger;
use Illuminate\Console\Command;

/**
 * Kéo nhãn trạng thái khuyến mại theo ngày bắt đầu / kết thúc.
 * ============================================================
 * VÌ SAO CẦN — VÀ VÌ SAO NÓ KHÔNG PHẢI CHUYỆN VỀ TIỀN.
 *
 * `Promotion::scopeActiveNow()` đã lọc theo `starts_at`/`ends_at`, nên
 * GIÁ BÁN luôn đúng: một khuyến mại quá hạn không còn giảm giá cho ai,
 * kể cả khi cột `status` vẫn ghi `active`.
 *
 * Sai là ở chỗ khác: cái NHÃN. Đo được trên dữ liệu thật lúc viết lệnh
 * này — 1 chương trình đã qua ngày kết thúc mà trang quản trị vẫn hiện
 * "Đang diễn ra". Admin nhìn danh sách và tưởng nó đang chạy; muốn biết
 * sự thật phải mở từng cái ra đối chiếu ngày.
 *
 * Một bảng điều khiển nói sai còn tệ hơn một bảng điều khiển trống: cái
 * trống thì người ta đi tìm chỗ khác, cái sai thì người ta tin.
 *
 * ============================================================
 * CHỈ ĐỘNG VÀO HAI CHIỀU CÓ THỂ SUY RA TỪ NGÀY THÁNG:
 *
 *     Đã lên lịch ──(tới ngày bắt đầu)──► Đang diễn ra ──(qua ngày kết thúc)──► Đã kết thúc
 *
 * KHÔNG động vào `Nháp` và `Tạm dừng`. Cả hai là Ý ĐỊNH CỦA CON NGƯỜI:
 * "tôi chưa muốn chạy cái này" và "tôi vừa tắt nó đi". Máy bật lại một
 * chương trình mà admin cố ý tạm dừng — chỉ vì hôm nay nằm trong khoảng
 * ngày — là máy huỷ quyết định của người.
 */
class CapNhatTrangThaiKhuyenMai extends Command
{
    protected $signature = 'khuyen-mai:cap-nhat-trang-thai';

    protected $description = 'Chuyển khuyến mại sang Đang diễn ra / Đã kết thúc theo ngày đã đặt';

    public function handle(ActivityLogger $nhatKy): int
    {
        $now = now();

        $batDau = Promotion::query()
            ->where('status', PromotionStatus::Scheduled)
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', $now)
            /*
             * Đã lên lịch NHƯNG cũng đã qua luôn ngày kết thúc (lệnh
             * không chạy suốt kỳ khuyến mại): bỏ qua ở đây, vòng dưới sẽ
             * đưa thẳng sang "Đã kết thúc". Bật lên rồi tắt ngay trong
             * cùng một lượt là ghi hai dòng nhật ký cho một chuyện.
             */
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->get();

        foreach ($batDau as $km) {
            $this->doi($km, PromotionStatus::Active, $nhatKy);
        }

        $ketThuc = Promotion::query()
            ->whereIn('status', [PromotionStatus::Active, PromotionStatus::Scheduled])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $now)
            ->get();

        foreach ($ketThuc as $km) {
            $this->doi($km, PromotionStatus::Ended, $nhatKy);
        }

        $this->info(sprintf(
            'Đã bật %d chương trình, kết thúc %d chương trình.',
            $batDau->count(),
            $ketThuc->count(),
        ));

        return self::SUCCESS;
    }

    private function doi(Promotion $km, PromotionStatus $moi, ActivityLogger $nhatKy): void
    {
        $cu = $km->status;

        $km->status = $moi;
        $km->save();

        /*
         * GHI NHẬT KÝ CHO CẢ VIỆC MÁY LÀM.
         *
         * Nhật ký quản trị trước đây chỉ ghi việc do người bấm. Khi máy
         * bắt đầu tự đổi trạng thái, admin mở lên thấy chương trình đã
         * kết thúc mà không có dòng nào giải thích — và câu hỏi đầu tiên
         * sẽ là "ai tắt của tôi?".
         */
        $nhatKy->log(
            'khuyen-mai.tu-dong-doi-trang-thai',
            sprintf(
                'Tự động chuyển "%s" từ %s sang %s theo lịch đã đặt.',
                $km->name,
                $cu->label(),
                $moi->label(),
            ),
            $km,
            ['truoc' => $cu->value, 'sau' => $moi->value],
        );
    }
}
