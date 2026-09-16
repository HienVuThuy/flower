<?php

namespace App\Services\Community;

use App\Enums\CommunityReaction;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\User;
use App\Services\Points\CommunityReward;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** Thích, lưu bài và bình luận Góc cây. */
class CommunityInteraction
{
    public const DO_DAI_BINH_LUAN = 1000;

    public function __construct(
        private readonly CommunityReward $thuong,
    ) {
    }

    public function doiThich(User $user, CommunityPost $post, ?CommunityReaction $camXuc = null): array
    {
        $camXuc ??= CommunityReaction::macDinh();
        $dieuKien = ['community_post_id' => $post->id, 'user_id' => $user->id];
        $dangCo = DB::table('community_post_likes')->where($dieuKien)->first();

        if ($dangCo) {
            if ($dangCo->reaction === $camXuc->value) {
                DB::table('community_post_likes')->where($dieuKien)->delete();

                return ['co' => false, 'loai' => null];
            }

            DB::table('community_post_likes')->where($dieuKien)->update(['reaction' => $camXuc->value]);

            return ['co' => true, 'loai' => $camXuc];
        }

        try {
            DB::table('community_post_likes')->insert($dieuKien + ['reaction' => $camXuc->value, 'created_at' => now()]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return ['co' => true, 'loai' => $camXuc];
            }

            throw $e;
        }

        $this->thuong->luotThich($post, $user);

        return ['co' => true, 'loai' => $camXuc];
    }

    public function doiLuu(User $user, CommunityPost $post): bool
    {
        $dieuKien = ['community_post_id' => $post->id, 'user_id' => $user->id];

        if (DB::table('community_post_saves')->where($dieuKien)->delete() > 0) {
            return false;
        }

        DB::table('community_post_saves')->insertOrIgnore($dieuKien + ['created_at' => now()]);

        return true;
    }

    public function doiCamXucBinhLuan(User $user, CommunityComment $binhLuan, ?CommunityReaction $camXuc = null): array
    {
        $camXuc ??= CommunityReaction::macDinh();
        $dieuKien = ['community_comment_id' => $binhLuan->id, 'user_id' => $user->id];
        $dangCo = DB::table('community_comment_reactions')->where($dieuKien)->first();

        if ($dangCo) {
            if ($dangCo->reaction === $camXuc->value) {
                DB::table('community_comment_reactions')->where($dieuKien)->delete();

                return ['co' => false, 'loai' => null];
            }

            DB::table('community_comment_reactions')->where($dieuKien)->update(['reaction' => $camXuc->value]);

            return ['co' => true, 'loai' => $camXuc];
        }

        DB::table('community_comment_reactions')->insertOrIgnore($dieuKien + ['reaction' => $camXuc->value, 'created_at' => now()]);

        return ['co' => true, 'loai' => $camXuc];
    }

    public function coTheBinhLuan(?User $user): bool
    {
        return $user !== null && $user->hasVerifiedEmail();
    }

    public function binhLuan(User $user, CommunityPost $post, string $noiDung, ?int $traLoi = null): CommunityComment
    {
        if (! $this->coTheBinhLuan($user)) {
            throw new CommunityException('Xác thực email của tài khoản để bình luận.');
        }

        if ($post->khoaBinhLuan()) {
            throw new CommunityException('Chủ bài đã khoá bình luận cho bài này.');
        }

        $noiDung = trim($noiDung);

        if ($noiDung === '') {
            throw new CommunityException('Bình luận không được để trống.');
        }

        [$goc, $nguoiDuocTraLoi] = $this->choTraLoi($post, $traLoi);

        $trung = CommunityComment::query()
            ->where('user_id', $user->id)
            ->where('community_post_id', $post->id)
            ->where('parent_id', $goc?->id)
            ->where('body', $noiDung)
            ->where('created_at', '>=', now()->subSeconds(15))
            ->first();

        if ($trung) {
            return $trung;
        }

        $binhLuan = new CommunityComment(['body' => $noiDung]);
        $binhLuan->forceFill([
            'user_id' => $user->id,
            'community_post_id' => $post->id,
            'parent_id' => $goc?->id,
            'reply_to_user_id' => $nguoiDuocTraLoi,
        ])->save();

        try {
            app(\App\Services\Notification\NotificationCenter::class)->binhLuan($binhLuan);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Không tạo được thông báo bình luận.', [
                'binh_luan' => $binhLuan->id,
                'loi' => $e->getMessage(),
            ]);
        }

        return $binhLuan;
    }

    public function suaBinhLuan(User $user, CommunityComment $binhLuan, string $noiDung): CommunityComment
    {
        if ((int) $binhLuan->user_id !== (int) $user->id) {
            throw new CommunityException('Chỉ sửa được bình luận của chính bạn.');
        }

        if ($binhLuan->hidden_at !== null) {
            throw new CommunityException('Bình luận đã bị cửa hàng ẩn nên không sửa được.');
        }

        $noiDung = trim($noiDung);

        if ($noiDung === '') {
            throw new CommunityException('Bình luận không được để trống.');
        }

        if ($noiDung !== $binhLuan->body) {
            $binhLuan->body = $noiDung;
            $binhLuan->edited_at = now();
            $binhLuan->save();
        }

        return $binhLuan;
    }

    private function choTraLoi(CommunityPost $post, ?int $traLoi): array
    {
        if ($traLoi === null) {
            return [null, null];
        }

        $dich = CommunityComment::visible()->where('community_post_id', $post->id)->find($traLoi);

        if (! $dich) {
            throw new CommunityException('Bình luận bạn trả lời không còn nữa.');
        }

        if ($dich->parent_id === null) {
            return [$dich, null];
        }

        $goc = CommunityComment::visible()->where('community_post_id', $post->id)->find($dich->parent_id);

        if (! $goc) {
            throw new CommunityException('Bình luận bạn trả lời không còn nữa.');
        }

        return [$goc, (int) $dich->user_id];
    }
}
