<?php

namespace App\Services\Community;

use App\Enums\CommunityReaction;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\User;
use App\Services\Points\CommunityReward;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Thích, lưu bài và bình luận Góc cây.
 * ============================================================
 * CHỈ BÀI ĐANG HIỆN — nơi gọi lấy bài qua `approved()` (đã duyệt, không bị ẩn).
 * Thích một bài chưa duyệt là xác nhận bài đó tồn tại.
 *
 * BÌNH LUẬN: MỌI TÀI KHOẢN ĐÃ XÁC THỰC EMAIL. Trước đây chỉ khách đã nhận
 * hàng mới bình luận được — chặn đúng người cần hỏi nhất: người chưa mua, thấy
 * cây đẹp và muốn hỏi cách chăm. Chống rác bằng cách khác: email phải xác thực,
 * giới hạn tốc độ ở route, khách báo cáo được, cửa hàng ẩn được một chạm.
 */
class CommunityInteraction
{
    public const DO_DAI_BINH_LUAN = 1000;

    public function __construct(
        private readonly CommunityReward $thuong,
    ) {
    }

    /**
     * Bày tỏ cảm xúc / đổi cảm xúc / bỏ cảm xúc.
     *
     * MỘT NGƯỜI MỘT CẢM XÚC cho một bài: bấm lại đúng cảm xúc đang có thì bỏ,
     * bấm cảm xúc khác thì ĐỔI dòng đã có (không thêm dòng mới) — nên số đếm và
     * điểm thưởng không nhân lên khi khách đổi ý.
     *
     * @return array{co: bool, loai: ?CommunityReaction} trạng thái SAU thao tác
     */
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
            // Hai tab bấm cùng lúc: dòng đã có — kết quả vẫn là "đang có cảm xúc".
            if ($e->getCode() === '23000') {
                return ['co' => true, 'loai' => $camXuc];
            }

            throw $e;
        }

        // Điểm thưởng tính theo NGƯỜI, không theo loại cảm xúc: đổi cảm xúc không cộng thêm.
        $this->thuong->luotThich($post, $user);

        return ['co' => true, 'loai' => $camXuc];
    }

    /**
     * Lưu / bỏ lưu bài để xem lại.
     *
     * @return bool true là đang lưu
     */
    public function doiLuu(User $user, CommunityPost $post): bool
    {
        $dieuKien = ['community_post_id' => $post->id, 'user_id' => $user->id];

        if (DB::table('community_post_saves')->where($dieuKien)->delete() > 0) {
            return false;
        }

        DB::table('community_post_saves')->insertOrIgnore($dieuKien + ['created_at' => now()]);

        return true;
    }

    public function coTheBinhLuan(?User $user): bool
    {
        return $user !== null && $user->hasVerifiedEmail();
    }

    /**
     * Viết bình luận, hoặc trả lời một bình luận.
     *
     * @param  int|null  $traLoi  id bình luận được trả lời (gốc hoặc câu trả lời)
     *
     * @throws CommunityException
     */
    public function binhLuan(User $user, CommunityPost $post, string $noiDung, ?int $traLoi = null): CommunityComment
    {
        if (! $this->coTheBinhLuan($user)) {
            throw new CommunityException('Xác thực email của tài khoản để bình luận.');
        }

        $noiDung = trim($noiDung);

        if ($noiDung === '') {
            throw new CommunityException('Bình luận không được để trống.');
        }

        [$goc, $nguoiDuocTraLoi] = $this->choTraLoi($post, $traLoi);

        /*
         * CHỐNG GỬI HAI LẦN: bấm "Gửi" hai lần vì mạng chậm thì không sinh hai
         * bình luận giống hệt nhau trong vài giây.
         */
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

        /*
         * BÁO CHO NGƯỜI LIÊN QUAN — việc phụ, không được làm hỏng việc chính.
         *
         * Bình luận đã ghi xong rồi; một lỗi khi tạo thông báo không được biến
         * thành trang lỗi trước mặt người vừa bấm Gửi.
         */
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

    /**
     * Sửa bình luận của chính mình.
     *
     * @throws CommunityException
     */
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

    /**
     * Trả lời bình luận nào: luôn gắn vào bình luận GỐC của cùng bài.
     *
     * @return array{0: ?CommunityComment, 1: ?int} [bình luận gốc, người được trả lời]
     *
     * @throws CommunityException
     */
    private function choTraLoi(CommunityPost $post, ?int $traLoi): array
    {
        if ($traLoi === null) {
            return [null, null];
        }

        // Tra trong CHÍNH bài này: id bịa của bài khác không gắn được vào đây.
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
