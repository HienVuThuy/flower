<?php

namespace App\Services\Community;

use App\Enums\OrderStatus;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\Order;
use App\Models\User;
use App\Services\Points\CommunityReward;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Thích và bình luận bài Góc cây.
 * ============================================================
 * CHỈ BÀI ĐÃ DUYỆT — nơi gọi phải lấy bài qua `approved()`. Thích một bài
 * chưa duyệt là xác nhận bài đó tồn tại.
 *
 * BÌNH LUẬN HIỆN NGAY, KHÔNG DUYỆT TRƯỚC — khác bài đăng, và vì thế đòi
 * điều kiện chặt hơn: người đã NHẬN ít nhất một đơn hàng. Duyệt tay từng
 * bình luận thì cửa hàng nhỏ không theo kịp; mở cho mọi tài khoản thì một
 * email ảo là đủ để rải quảng cáo dưới ảnh cây của khách. Người đã mua
 * hàng thật có danh tính và có lý do để giữ tài khoản. Cửa hàng vẫn ẩn
 * được bất kỳ bình luận nào.
 */
class CommunityInteraction
{
    public function __construct(
        private readonly CommunityReward $thuong,
    ) {
    }

    /**
     * Thích / bỏ thích.
     *
     * @return bool trạng thái SAU thao tác: true là đang thích
     */
    public function doiThich(User $user, CommunityPost $post): bool
    {
        $dieuKien = ['community_post_id' => $post->id, 'user_id' => $user->id];

        if (DB::table('community_post_likes')->where($dieuKien)->delete() > 0) {
            return false;
        }

        try {
            DB::table('community_post_likes')->insert($dieuKien + ['created_at' => now()]);
        } catch (QueryException $e) {
            // Hai tab bấm cùng lúc: dòng đã có — kết quả vẫn là "đang thích".
            if ($e->getCode() === '23000') {
                return true;
            }

            throw $e;
        }

        $this->thuong->luotThich($post, $user);

        return true;
    }

    public function coTheBinhLuan(?User $user): bool
    {
        return $user !== null && Order::query()
            ->where('user_id', $user->id)
            ->where('status', OrderStatus::Completed->value)
            ->exists();
    }

    /** @throws CommunityException */
    public function binhLuan(User $user, CommunityPost $post, string $noiDung): CommunityComment
    {
        if (! $this->coTheBinhLuan($user)) {
            throw new CommunityException('Bình luận dành cho khách đã nhận ít nhất một đơn hàng ở cửa hàng.');
        }

        $binhLuan = new CommunityComment(['body' => trim($noiDung)]);
        $binhLuan->user_id = $user->id;
        $binhLuan->community_post_id = $post->id;
        $binhLuan->save();

        return $binhLuan;
    }
}
