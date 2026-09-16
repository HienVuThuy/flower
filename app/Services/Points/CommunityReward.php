<?php

namespace App\Services\Points;

use App\Enums\PointReason;
use App\Models\CommunityPost;
use App\Models\PointTransaction;
use App\Models\User;
use App\Services\Time\Gio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Thưởng điểm cho Góc cây — theo CHẤT LƯỢNG, TẦN SUẤT và TƯƠNG TÁC.
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
 * TƯƠNG TÁC: lượt thích NGƯỜI KHÁC dành cho bài. Tự thích bài mình không
 * tính; người thích phải có email đã xác thực; mỗi người chỉ tính một lần
 * cho một bài (bỏ thích rồi thích lại không cộng lại); và có trần điểm mỗi
 * tuần — không có trần thì mười tài khoản phụ là một máy in điểm. Bình
 * luận KHÔNG được thưởng: thưởng bình luận là mời bình luận rác.
 *
 * KHÔNG THU HỒI khi bài bị gỡ hay bị bỏ thích: điểm có thể đã đổi thành
 * voucher, và trừ ngược làm sổ của khách âm vì việc không phải của họ.
 */
class CommunityReward
{
    public const CO_BAN = 20;

    public const CO_ANH = 10;

    public const NOI_BAT = 30;

    public const TOI_DA_MOI_TUAN = 3;

    public const LUOT_THICH = 2;

    /** Trần ĐIỂM (không phải số lượt) từ lượt thích mỗi tuần cho một tác giả. */
    public const THICH_TOI_DA_MOI_TUAN = 20;

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

        $daThuong = PointTransaction::where('user_id', $user->id)
            ->where('reason', PointReason::DangBai->value)
            ->where('created_at', '>=', self::dauTuan())
            ->count();

        if ($daThuong >= self::TOI_DA_MOI_TUAN) {
            return 0;
        }

        $diem = self::CO_BAN
            // Có ít nhất một ảnh hoặc video — nhiều tệp không cộng thêm (không thưởng đăng dồn ảnh).
            + ($post->media()->exists() ? self::CO_ANH : 0)
            + ($noiBat ? self::NOI_BAT : 0);

        $ghiChu = ($noiBat ? 'Bài nổi bật' : 'Bài được duyệt') . ': ' . Str::limit($post->body, 60);

        return $this->so->cong($user, $diem, PointReason::DangBai, $khoa, $ghiChu) ? $diem : null;
    }

    /** @return int số điểm vừa cộng cho tác giả */
    public function luotThich(CommunityPost $post, User $nguoiThich): int
    {
        $tacGia = $post->user;

        if ($tacGia === null
            || (int) $tacGia->id === (int) $nguoiThich->id
            || ! $nguoiThich->hasVerifiedEmail()) {
            return 0;
        }

        $daNhan = (int) PointTransaction::where('user_id', $tacGia->id)
            ->where('reason', PointReason::DuocThich->value)
            ->where('created_at', '>=', self::dauTuan())
            ->sum('amount');

        if ($daNhan + self::LUOT_THICH > self::THICH_TOI_DA_MOI_TUAN) {
            return 0;
        }

        return $this->so->cong(
            $tacGia,
            self::LUOT_THICH,
            PointReason::DuocThich,
            'thich:' . $post->id . ':' . $nguoiThich->id,
            'Lượt thích bài: ' . Str::limit($post->body, 40),
        ) ? self::LUOT_THICH : 0;
    }

    /**
     * Điểm DUYỆT BÀI đã thưởng cho từng bài, để hiện cạnh bài.
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

    /**
     * TUẦN THEO LỊCH VIỆT NAM, bắt đầu thứ Hai, trả về theo giờ lưu. Tuần
     * theo UTC sang tuần mới lúc 7 giờ sáng thứ Hai — việc lúc 6 giờ sáng
     * vẫn bị tính vào trần của tuần trước.
     */
    private static function dauTuan(): Carbon
    {
        return now(Gio::mui())->startOfWeek(Carbon::MONDAY)->setTimezone(Gio::muiLuu());
    }
}
