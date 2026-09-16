<?php

namespace App\Console\Commands;

use App\Enums\PromotionStatus;
use App\Models\Promotion;
use App\Services\Audit\ActivityLogger;
use Illuminate\Console\Command;

/** Kéo nhãn trạng thái khuyến mại theo ngày bắt đầu / kết thúc. */
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
