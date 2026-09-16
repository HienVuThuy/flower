<?php

namespace App\Services\Community;

use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Những việc CHỦ BÀI tự làm được với bài của mình.
 * ============================================================
 * KHÔNG LẤN SANG QUYỀN DUYỆT CỦA CỬA HÀNG:
 *
 *   - chủ bài tự ẩn bài mình (mở lại được lúc nào cũng được) — khác hẳn việc
 *     cửa hàng ẩn vì vi phạm, thứ khách không được tự mở;
 *   - chủ bài ẩn bình luận TRÊN BÀI CỦA MÌNH, nhưng không mở lại được bình luận
 *     do cửa hàng ẩn — nếu không thì ẩn một bình luận vi phạm xong chủ bài bật
 *     lại là xong chuyện;
 *   - ghim chỉ đổi thứ tự TRANG CÁ NHÂN, không đẩy bài lên bảng tin chung.
 */
class PostOwner
{
    /** Bài của chính người này — 404 chứ không 403, cùng cách với mọi đường khác. */
    public function baiCuaToi(User $user, int $id): CommunityPost
    {
        return CommunityPost::where('user_id', $user->id)->findOrFail($id);
    }

    /**
     * Tạm ẩn / hiện lại bài của mình.
     *
     * @return bool true là đang ẩn
     */
    public function doiAn(CommunityPost $bai): bool
    {
        $dangAn = $bai->author_hidden_at === null;

        $bai->forceFill([
            'author_hidden_at' => $dangAn ? now() : null,
            // Bài bị ẩn thì cái ghim không còn nghĩa gì.
            'pinned_at' => $dangAn ? null : $bai->pinned_at,
        ])->save();

        return $dangAn;
    }

    /**
     * Ghim / bỏ ghim một bài lên đầu trang cá nhân.
     *
     * MỖI NGƯỜI MỘT BÀI GHIM: ghim bài mới thì bài cũ tự bỏ ghim — "ghim" mà có
     * mười cái thì không còn là ghim nữa.
     *
     * @throws CommunityException
     */
    public function doiGhim(CommunityPost $bai): bool
    {
        if ($bai->pinned_at !== null) {
            $bai->forceFill(['pinned_at' => null])->save();

            return false;
        }

        if (! $bai->isApproved() || $bai->isHidden() || $bai->tuAn()) {
            throw new CommunityException('Chỉ ghim được bài đang hiển thị.');
        }

        DB::transaction(function () use ($bai) {
            CommunityPost::where('user_id', $bai->user_id)->whereNotNull('pinned_at')->update(['pinned_at' => null]);
            $bai->forceFill(['pinned_at' => now()])->save();
        });

        return true;
    }

    /** Khoá / mở bình luận cho bài của mình. @return bool true là đang khoá */
    public function doiKhoaBinhLuan(CommunityPost $bai): bool
    {
        $khoa = $bai->comments_locked_at === null;

        $bai->forceFill(['comments_locked_at' => $khoa ? now() : null])->save();

        return $khoa;
    }

    /**
     * Chủ bài ẩn / hiện lại một bình luận TRÊN BÀI CỦA MÌNH.
     *
     * @return bool true là đang ẩn
     *
     * @throws CommunityException
     */
    public function doiAnBinhLuan(User $user, CommunityComment $binhLuan): bool
    {
        $bai = $binhLuan->post()->first();

        if (! $bai || (int) $bai->user_id !== (int) $user->id) {
            throw new CommunityException('Chỉ ẩn được bình luận trên bài của bạn.');
        }

        if ($binhLuan->hidden_at !== null) {
            if ($binhLuan->hidden_by === null) {
                throw new CommunityException('Bình luận này do cửa hàng ẩn, bạn không mở lại được.');
            }

            $binhLuan->forceFill(['hidden_at' => null, 'hidden_by' => null])->save();

            return false;
        }

        $binhLuan->forceFill(['hidden_at' => now(), 'hidden_by' => $user->id])->save();

        return true;
    }
}
