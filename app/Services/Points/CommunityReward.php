<?php

namespace App\Services\Points;

use App\Enums\PointReason;
use App\Models\CommunityPost;
use App\Models\PointTransaction;
use App\Services\Time\Gio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Thưởng điểm cho bài Góc cây — theo CHẤT LƯỢNG và TẦN SUẤT.
 * ============================================================
 * CHẤT LƯỢNG do người duyệt đánh giá, không do máy đếm chữ: độ dài bài
 * nói rất ít về việc nó có đáng xem không. Người duyệt đã đọc bài rồi;
 * một nút "bài nổi bật" là cách rẻ nhất để ghi lại phán đoán đó. Bài có
 * ảnh được thêm một ít — ảnh cây thật là thứ Góc cây cần nhất.
 *
 * TẦN SUẤT có trần mỗi tuần: không có trần thì mười bài "cây đẹp quá" một
 * ngày là mười lần cộng điểm. Bài vượt trần vẫn được duyệt và hiện ra —
 * chỉ là không cộng điểm, và người duyệt được nói rõ vì sao.
 *
 * TƯƠNG TÁC: CHƯA LÀM. Góc cây chưa có lượt thích hay bình luận, nên
 * không có con số tương tác thật nào để thưởng theo. Đếm lượt xem trang
 * thay vào là thưởng theo một con số ai cũng tự bơm được.
 *
 * KHÔNG THU HỒI khi bài bị gỡ sau này: điểm có thể đã đổi thành voucher,
 * và trừ ngược làm sổ của khách âm vì một quyết định không phải của họ.
 */
class CommunityReward
{
    public const CO_BAN = 20;

    public const CO_ANH = 10;

    public const NOI_BAT = 30;

    public const TOI_DA_MOI_TUAN = 3;

    public function __construct(
        private readonly PointLedger $so,
    ) {
    }

    /**
     * Thưởng cho một bài vừa được duyệt.
     *
     * @return int|null số điểm vừa cộng; 0 nếu khách đã đủ trần tuần này;
     *                  null nếu bài đã được thưởng từ trước (duyệt lại)
     */
    public function thuong(CommunityPost $post, bool $noiBat): ?int
    {
        $user = $post->user;

        if ($user === null) {
            return null;
        }

        $khoa = self::khoa($post->id);

        // Đã thưởng (bài từng duyệt, bị từ chối, rồi duyệt lại) — không tính vào trần, không cộng thêm.
        if (PointTransaction::where('user_id', $user->id)->where('source_key', $khoa)->exists()) {
            return null;
        }

        /*
         * TUẦN THEO LỊCH VIỆT NAM, bắt đầu thứ Hai. Tuần theo giờ lưu (UTC)
         * sang tuần mới lúc 7 giờ sáng thứ Hai — bài duyệt lúc 6 giờ sáng
         * vẫn bị tính vào trần của tuần trước.
         */
        $dauTuan = now(Gio::mui())->startOfWeek(Carbon::MONDAY)->setTimezone(Gio::muiLuu());

        $daThuong = PointTransaction::where('user_id', $user->id)
            ->where('reason', PointReason::DangBai->value)
            ->where('created_at', '>=', $dauTuan)
            ->count();

        if ($daThuong >= self::TOI_DA_MOI_TUAN) {
            return 0;
        }

        $diem = self::CO_BAN
            + ($post->photo ? self::CO_ANH : 0)
            + ($noiBat ? self::NOI_BAT : 0);

        $ghiChu = ($noiBat ? 'Bài nổi bật' : 'Bài được duyệt') . ': ' . Str::limit($post->body, 60);

        return $this->so->cong($user, $diem, PointReason::DangBai, $khoa, $ghiChu) ? $diem : null;
    }

    /**
     * Điểm đã thưởng cho từng bài, để hiện cạnh bài.
     *
     * @param  Collection<int, CommunityPost>  $posts
     * @return array<int, int> id bài => điểm
     */
    public function daThuong(Collection $posts): array
    {
        if ($posts->isEmpty()) {
            return [];
        }

        $theoKhoa = $posts->keyBy(fn (CommunityPost $p) => self::khoa($p->id));

        return PointTransaction::query()
            ->whereIn('source_key', $theoKhoa->keys())
            ->whereIn('user_id', $posts->pluck('user_id')->unique())
            ->get(['source_key', 'amount'])
            ->mapWithKeys(fn ($d) => [$theoKhoa[$d->source_key]->id => (int) $d->amount])
            ->all();
    }

    public static function khoa(int $postId): string
    {
        return 'bai:' . $postId;
    }
}
