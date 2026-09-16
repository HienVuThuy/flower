<?php

namespace App\Services\Community;

use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Những việc CHỦ BÀI tự làm được với bài của mình. */
class PostOwner
{
    public function baiCuaToi(User $user, int $id): CommunityPost
    {
        return CommunityPost::where('user_id', $user->id)->findOrFail($id);
    }

    public function doiAn(CommunityPost $bai): bool
    {
        $dangAn = $bai->author_hidden_at === null;

        $bai->forceFill([
            'author_hidden_at' => $dangAn ? now() : null,
            'pinned_at' => $dangAn ? null : $bai->pinned_at,
        ])->save();

        return $dangAn;
    }

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

    public function doiKhoaBinhLuan(CommunityPost $bai): bool
    {
        $khoa = $bai->comments_locked_at === null;

        $bai->forceFill(['comments_locked_at' => $khoa ? now() : null])->save();

        return $khoa;
    }

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
