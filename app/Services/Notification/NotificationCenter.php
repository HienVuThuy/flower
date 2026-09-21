<?php

namespace App\Services\Notification;

use App\Enums\NotificationType;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Thông báo trong trang — NƠI DUY NHẤT tạo và đọc. */
class NotificationCenter
{
    public function binhLuan(CommunityComment $bl): void
    {
        $bai = $bl->post()->first();

        if (! $bai) {
            return;
        }

        $daBao = [];

        $daBao[] = $this->tao((int) $bai->user_id, NotificationType::BinhLuanBai, $bl->user_id, $bai, $bl);

        if ($bl->parent_id === null) {
            return;
        }

        $goc = CommunityComment::find($bl->parent_id);

        if ($goc) {
            $daBao[] = $this->tao((int) $goc->user_id, NotificationType::TraLoi, $bl->user_id, $bai, $bl, $daBao);
        }

        if ($bl->reply_to_user_id) {
            $this->tao((int) $bl->reply_to_user_id, NotificationType::TraLoi, $bl->user_id, $bai, $bl, $daBao);
        }
    }

    public function baiDuocDuyet(CommunityPost $bai): void
    {
        $this->tao((int) $bai->user_id, NotificationType::BaiDuocDuyet, null, $bai);
    }

    public function baiTuChoi(CommunityPost $bai, string $lyDo): void
    {
        $this->tao((int) $bai->user_id, NotificationType::BaiTuChoi, null, $bai, null, [], $lyDo);
    }

    public function baiBiAn(CommunityPost $bai, string $lyDo): void
    {
        $this->tao((int) $bai->user_id, NotificationType::BaiBiAn, null, $bai, null, [], $lyDo);
    }

    public function chuaDoc(?User $user): int
    {
        return $user === null
            ? 0
            : UserNotification::where('user_id', $user->id)->chuaDoc()->count();
    }

    public function danhSach(User $user, int $moiTrang = 20): LengthAwarePaginator
    {
        return UserNotification::query()
            ->where('user_id', $user->id)
            ->with(['actor:id,name', 'post:id,body,approved_at,hidden_at', 'comment:id,body', 'boardingBooking:id,code'])
            ->latest('id')
            ->paginate($moiTrang);
    }

    public function danhDauDaDoc(UserNotification $tb): void
    {
        if ($tb->read_at === null) {
            $tb->forceFill(['read_at' => now()])->save();
        }
    }

    public function danhDauTatCa(User $user): int
    {
        return UserNotification::where('user_id', $user->id)->chuaDoc()->update(['read_at' => now()]);
    }

    private function tao(
        int $nguoiNhan,
        NotificationType $loai,
        ?int $nguoiGayRa,
        ?CommunityPost $bai = null,
        ?CommunityComment $bl = null,
        array $daBao = [],
        ?string $ghiChu = null,
    ): ?int {
        if ($nguoiNhan === (int) $nguoiGayRa || in_array($nguoiNhan, $daBao, true)) {
            return null;
        }

        $tb = new UserNotification();
        $tb->forceFill([
            'user_id' => $nguoiNhan,
            'type' => $loai,
            'actor_id' => $nguoiGayRa,
            'community_post_id' => $bai?->id,
            'community_comment_id' => $bl?->id,
            'note' => $ghiChu,
        ])->save();

        return $nguoiNhan;
    }
}
