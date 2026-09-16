<?php

namespace App\Services\Notification;

use App\Enums\NotificationType;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Thông báo trong trang — NƠI DUY NHẤT tạo và đọc.
 * ============================================================
 * BA LUẬT:
 *
 *   1. KHÔNG TỰ BÁO CHO CHÍNH MÌNH. Bình luận dưới bài của mình, trả lời chính
 *      mình — không ai cần một thông báo về việc mình vừa làm.
 *   2. MỘT VIỆC, MỖI NGƯỜI MỘT THÔNG BÁO. Trả lời một bình luận dưới bài của
 *      người khác có thể chạm tới ba người (chủ bài, chủ bình luận gốc, người
 *      được trả lời); ai trùng thì chỉ nhận một.
 *   3. KHÔNG LÀM HỎNG VIỆC CHÍNH. Tạo thông báo là việc phụ đi kèm bình luận
 *      hay duyệt bài; hỏng thì ghi log, không ném lỗi ra màn hình của khách.
 */
class NotificationCenter
{
    /** Bình luận mới hoặc câu trả lời: báo cho những người có liên quan. */
    public function binhLuan(CommunityComment $bl): void
    {
        $bai = $bl->post()->first();

        if (! $bai) {
            return;
        }

        $daBao = [];

        // Chủ bài: có người bình luận bài của bạn.
        $daBao[] = $this->tao((int) $bai->user_id, NotificationType::BinhLuanBai, $bl->user_id, $bai, $bl);

        if ($bl->parent_id === null) {
            return;
        }

        // Chủ bình luận gốc: có người trả lời bình luận của bạn.
        $goc = CommunityComment::find($bl->parent_id);

        if ($goc) {
            $daBao[] = $this->tao((int) $goc->user_id, NotificationType::TraLoi, $bl->user_id, $bai, $bl, $daBao);
        }

        // Người được trả lời trực tiếp (trả lời một câu trả lời).
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

    /** @return LengthAwarePaginator<int, UserNotification> */
    public function danhSach(User $user, int $moiTrang = 20): LengthAwarePaginator
    {
        return UserNotification::query()
            ->where('user_id', $user->id)
            ->with(['actor:id,name', 'post:id,body,approved_at,hidden_at', 'comment:id,body'])
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

    /**
     * Ghi một thông báo, bỏ qua khi người nhận chính là người gây ra hoặc đã
     * được báo trong cùng việc này.
     *
     * @param  list<int|null>  $daBao  id những người đã nhận thông báo của việc này
     * @return int|null id người vừa được báo (để nơi gọi gộp vào $daBao)
     */
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
