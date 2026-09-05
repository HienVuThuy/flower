<?php

namespace App\Services\Coupon;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ví voucher: mã giảm giá khách đã lưu về tài khoản.
 * ============================================================
 * NƠI DUY NHẤT trả lời ba câu hỏi:
 *   - khách này đã lưu những mã nào?
 *   - còn mã nào đang mời mà họ chưa lưu?
 *   - mã này khách còn dùng được mấy lần nữa?
 *
 * TÁCH KHỎI CouponService, không gộp: CouponService trả lời "mã này có
 * hợp lệ với GIỎ HÀNG này không" — thuần về tiền và điều kiện đơn. Lớp
 * này trả lời "mã này với NGƯỜI này thì sao" — thuần về quan hệ sở hữu.
 * Gộp lại thì mọi phép kiểm tra giá phải kéo theo khái niệm người dùng,
 * kể cả khi khách mua không đăng nhập.
 */
class CouponWallet
{
    /**
     * Lưu một mã về ví.
     *
     * @return bool true nếu vừa lưu, false nếu đã có sẵn từ trước
     *
     * @throws CouponException khi mã không cho lưu
     */
    public function claim(User $user, Coupon $coupon): bool
    {
        if (! $coupon->is_public) {
            /*
             * Mã riêng (in trên tờ rơi, gửi cho một khách cụ thể) vẫn
             * NHẬP TAY được ở bước thanh toán, chỉ là không lưu về ví
             * bằng nút bấm. Nếu cho lưu thì ai dò trúng id là có mã trong
             * ví vĩnh viễn.
             */
            throw new CouponException('Mã này không nằm trong chương trình đang mở.');
        }

        if (! $coupon->isRunning()) {
            throw new CouponException('Mã giảm giá không còn hiệu lực.');
        }

        if ($coupon->isExhausted()) {
            throw new CouponException('Mã giảm giá đã hết lượt.');
        }

        try {
            DB::table('coupon_user')->insert([
                'user_id' => $user->id,
                'coupon_id' => $coupon->id,
                'claimed_at' => now(),
                'used_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        } catch (QueryException $e) {
            /*
             * Trùng khoá = đã lưu rồi. KHÔNG phải lỗi cần báo đỏ: khách
             * bấm hai lần, hoặc mở hai tab. Ràng buộc UNIQUE làm đúng
             * việc của nó; ở đây chỉ cần nói "đã có trong ví".
             *
             * Bắt theo mã SQLSTATE 23000 chứ không bắt mọi QueryException:
             * mất kết nối cơ sở dữ liệu cũng là QueryException, mà nuốt
             * lỗi đó rồi báo "đã lưu rồi" là nói dối.
             */
            if ($e->getCode() === '23000') {
                return false;
            }

            throw $e;
        }
    }

    /** Bỏ một mã khỏi ví. */
    public function discard(User $user, Coupon $coupon): bool
    {
        /*
         * KHÔNG cho bỏ mã đã dùng: hàng `coupon_user` là bằng chứng khách
         * đã dùng mã này mấy lần, và per_user_limit dựa vào đó. Xoá đi là
         * họ dùng lại được từ đầu.
         */
        return DB::table('coupon_user')
            ->where('user_id', $user->id)
            ->where('coupon_id', $coupon->id)
            ->where('used_count', 0)
            ->delete() > 0;
    }

    /**
     * Các mã đang trong ví, kèm số lượt đã dùng.
     *
     * @return Collection<int, array{coupon: Coupon, usedCount: int, exhaustedForUser: bool}>
     */
    public function forUser(User $user): Collection
    {
        $rows = DB::table('coupon_user')
            ->where('user_id', $user->id)
            ->orderByDesc('claimed_at')
            ->get(['coupon_id', 'used_count']);

        if ($rows->isEmpty()) {
            return collect();
        }

        // Một truy vấn cho toàn bộ mã, không phải mỗi hàng một lần.
        $coupons = Coupon::whereIn('id', $rows->pluck('coupon_id'))->get()->keyBy('id');

        return $rows
            ->map(function ($row) use ($coupons) {
                $coupon = $coupons->get($row->coupon_id);

                return $coupon === null ? null : [
                    'coupon' => $coupon,
                    'usedCount' => (int) $row->used_count,
                    'exhaustedForUser' => $this->limitReached($coupon, (int) $row->used_count),
                ];
            })
            // Mã bị xoá hẳn khỏi hệ thống: khoá ngoại đã cascade nên
            // trường hợp này gần như không xảy ra, nhưng lọc cho chắc.
            ->filter()
            ->values();
    }

    /**
     * Mã công khai đang chạy mà khách CHƯA lưu.
     *
     * @return Collection<int, Coupon>
     */
    public function claimableFor(?User $user): Collection
    {
        $query = Coupon::query()
            ->where('is_public', true)
            /*
             * MÃ CỦA SỰ KIỆN KHÔNG HIỆN Ở TRANG VOUCHER CHUNG.
             *
             * Cả điểm của việc gắn mã vào một chương trình là nó chỉ phát
             * TRONG trang sự kiện đó — đấy là lý do khách chịu bấm vào
             * banner. Để nó hiện cả ở đây thì trang sự kiện mất luôn thứ
             * duy nhất chỉ nó mới có.
             *
             * Mã đã LƯU vào ví thì vẫn hiện bình thường ở khối "Ví của
             * tôi" (hàm forUser), vì lúc đó nó đã là tài sản của khách.
             */
            ->whereNull('promotion_id')
            ->usableNow()
            ->orderByDesc('value');

        if ($user) {
            $claimed = DB::table('coupon_user')
                ->where('user_id', $user->id)
                ->pluck('coupon_id');

            if ($claimed->isNotEmpty()) {
                $query->whereNotIn('id', $claimed);
            }
        }

        return $query->get()
            // Hết lượt toàn hệ thống thì không mời nữa. Lọc bằng PHP chứ
            // không bằng SQL: điều kiện là "usage_limit IS NULL OR
            // used_count < usage_limit", viết trong truy vấn thì dài dòng
            // mà số mã luôn nhỏ.
            ->reject(fn (Coupon $c) => $c->isExhausted())
            ->values();
    }

    /** Mã này có trong ví của khách không. */
    public function has(?User $user, Coupon $coupon): bool
    {
        return $user !== null && DB::table('coupon_user')
            ->where('user_id', $user->id)
            ->where('coupon_id', $coupon->id)
            ->exists();
    }

    /**
     * Khách này đã dùng hết suất của mình cho mã này chưa.
     *
     * Khách VÃNG LAI luôn trả về false: không có tài khoản thì không có
     * cách nào đếm. Đó là giới hạn thật của việc cho đặt hàng không cần
     * đăng nhập, không phải chỗ để giả vờ đã kiểm soát được.
     */
    public function userLimitReached(?User $user, Coupon $coupon): bool
    {
        if ($user === null || $coupon->per_user_limit === null) {
            return false;
        }

        $used = (int) DB::table('coupon_user')
            ->where('user_id', $user->id)
            ->where('coupon_id', $coupon->id)
            ->value('used_count');

        return $this->limitReached($coupon, $used);
    }

    /**
     * Ghi nhận khách vừa dùng mã.
     *
     * Gọi cùng lúc với CouponService::redeem() — xem CheckoutController.
     * Dùng upsert vì khách có thể nhập tay một mã chưa từng lưu vào ví;
     * lúc đó vẫn phải đếm, nếu không per_user_limit thành vô nghĩa với
     * đúng những người không dùng nút Lưu.
     */
    public function markUsed(?User $user, Coupon $coupon): void
    {
        if ($user === null) {
            return;
        }

        DB::table('coupon_user')->upsert(
            [[
                'user_id' => $user->id,
                'coupon_id' => $coupon->id,
                'claimed_at' => now(),
                'used_count' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['user_id', 'coupon_id'],
            // Hàng đã có thì CỘNG THÊM, không ghi đè bằng 1 — ghi đè sẽ
            // reset bộ đếm mỗi lần dùng và per_user_limit không bao giờ
            // chặn được ai.
            ['used_count' => DB::raw('used_count + 1'), 'updated_at' => now()],
        );
    }

    /** Trả lại một lượt khi đơn bị huỷ. */
    public function releaseUse(?User $user, Coupon $coupon): void
    {
        if ($user === null) {
            return;
        }

        DB::table('coupon_user')
            ->where('user_id', $user->id)
            ->where('coupon_id', $coupon->id)
            // GREATEST giữ bộ đếm không âm. Không có nó thì huỷ một đơn
            // cũ (đặt từ trước khi có bảng này) sẽ trừ xuống -1, và khách
            // được thêm một lượt miễn phí.
            ->update([
                'used_count' => DB::raw('GREATEST(used_count - 1, 0)'),
                'updated_at' => now(),
            ]);
    }

    private function limitReached(Coupon $coupon, int $used): bool
    {
        return $coupon->per_user_limit !== null && $used >= $coupon->per_user_limit;
    }
}
