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

/** Thưởng điểm cho Góc cây — theo CHẤT LƯỢNG, TẦN SUẤT và TƯƠNG TÁC. */
class CommunityReward
{
    public const CO_BAN = 20;

    public const CO_ANH = 10;

    public const NOI_BAT = 30;

    public const TOI_DA_MOI_TUAN = 3;

    public const LUOT_THICH = 2;

    public const THICH_TOI_DA_MOI_TUAN = 20;

    public function __construct(
        private readonly PointLedger $so,
    ) {
    }

    public function thuong(CommunityPost $post, bool $noiBat): ?int
    {
        $user = $post->user;

        if ($user === null) {
            return null;
        }

        $khoa = self::khoa($post->id);

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
            + ($post->media()->exists() ? self::CO_ANH : 0)
            + ($noiBat ? self::NOI_BAT : 0);

        $ghiChu = ($noiBat ? 'Bài nổi bật' : 'Bài được duyệt') . ': ' . Str::limit($post->body, 60);

        return $this->so->cong($user, $diem, PointReason::DangBai, $khoa, $ghiChu) ? $diem : null;
    }

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

    private static function dauTuan(): Carbon
    {
        return now(Gio::mui())->startOfWeek(Carbon::MONDAY)->setTimezone(Gio::muiLuu());
    }
}
