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

    /**
     * Bỏ một mã khỏi ví — MỌI mã trong ví đều bỏ được.
     * ============================================================
     * HAI ĐƯỜNG, TUỲ MÃ ĐÃ DÙNG HAY CHƯA.
     *
     * Trước đây hàm này chỉ xoá được mã `used_count = 0`, và giao diện
     * thì chỉ hiện nút cho mã ĐANG CÒN HẠN. Kết quả là một mã đã lưu mà
     * hết hạn nằm lại trong ví vĩnh viễn — không nút nào chạm tới nó
     * được, và ví đầy dần bằng những mã không dùng được nữa.
     *
     *   - CHƯA DÙNG LẦN NÀO -> xoá hàng thật. Chưa dùng thì không có
     *     bằng chứng nào để giữ, và xoá hẳn cho phép khách lưu lại sau
     *     này nếu đổi ý.
     *
     *   - ĐÃ DÙNG -> chỉ đánh dấu `hidden_at`. Hàng `coupon_user` là
     *     bằng chứng khách đã dùng mã mấy lần và `per_user_limit` đếm
     *     dựa vào nó; xoá đi là họ dùng lại được từ đầu. Ẩn thì màn hình
     *     gọn mà phép đếm vẫn đúng.
     *
     * Khách chỉ muốn dọn ví cho gọn, không đòi xoá lịch sử của chính
     * mình. Đáp ứng đúng nhu cầu đó thì không phải nới lỏng gì cả.
     *
     * @return 'deleted'|'hidden'|null null khi mã không có trong ví
     */
    public function discard(User $user, Coupon $coupon): ?string
    {
        $row = DB::table('coupon_user')
            ->where('user_id', $user->id)
            ->where('coupon_id', $coupon->id)
            ->first(['used_count']);

        if ($row === null) {
            return null;
        }

        if ((int) $row->used_count === 0) {
            DB::table('coupon_user')
                ->where('user_id', $user->id)
                ->where('coupon_id', $coupon->id)
                ->delete();

            return 'deleted';
        }

        DB::table('coupon_user')
            ->where('user_id', $user->id)
            ->where('coupon_id', $coupon->id)
            ->update(['hidden_at' => now(), 'updated_at' => now()]);

        return 'hidden';
    }

    /**
     * Đưa lại một mã đã ẩn về ví.
     *
     * PHẢI CÓ ĐƯỜNG QUAY LẠI. Một nút chỉ đi một chiều là cái bẫy: bấm
     * nhầm rồi thì mã biến mất và khách không biết nó đi đâu, cũng không
     * có cách nào tìm lại.
     */
    public function unhide(User $user, Coupon $coupon): bool
    {
        return DB::table('coupon_user')
            ->where('user_id', $user->id)
            ->where('coupon_id', $coupon->id)
            ->whereNotNull('hidden_at')
            ->update(['hidden_at' => null, 'updated_at' => now()]) > 0;
    }

    /** Số mã đang bị ẩn — để giao diện có chỗ mời xem lại. */
    /**
     * Mã này còn dùng được không.
     *
     * ============================================================
     * BA CÁCH CHẾT, GIAO DIỆN XỬ LÝ NHƯ NHAU:
     *
     *   - khách đã dùng hết suất của mình
     *   - mã hết lượt trên toàn hệ thống
     *   - mã hết hạn, hoặc đã bị ngừng
     *
     * LUẬT NÀY TỪNG NẰM TRONG BLADE (biến `$dead` của voucher-card). Để
     * ở đó thì mỗi nơi cần lọc lại phải chép lại, và bản chép sẽ quên
     * một trong ba vế — mã hết hạn vẫn nằm trong ví, hoặc mã còn dùng
     * được bị dọn đi oan.
     *
     * @param  array{coupon: Coupon, usedCount: int, exhaustedForUser: bool}  $row
     */
    public function conDungDuoc(array $row): bool
    {
        $coupon = $row['coupon'];

        return ! $row['exhaustedForUser']
            && $coupon->isRunning()
            && ! $coupon->isExhausted();
    }

    public function hiddenCount(?User $user): int
    {
        return $user === null ? 0 : DB::table('coupon_user')
            ->where('user_id', $user->id)
            ->whereNotNull('hidden_at')
            ->count();
    }

    /**
     * Các mã đang trong ví, kèm số lượt đã dùng.
     *
     * @return Collection<int, array{coupon: Coupon, usedCount: int, exhaustedForUser: bool}>
     */
    public function forUser(User $user, bool $daAn = false): Collection
    {
        $rows = DB::table('coupon_user')
            ->where('user_id', $user->id)
            // Mặc định chỉ lấy mã ĐANG hiện. Mã đã ẩn vẫn còn nguyên
            // hàng — xem chú thích ở discard() — nhưng không chen vào
            // danh sách chính nữa.
            ->when($daAn, fn ($q) => $q->whereNotNull('hidden_at'))
            ->when(! $daAn, fn ($q) => $q->whereNull('hidden_at'))
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
            /*
             * MÃ SẮP HẾT HẠN LÊN ĐẦU, mã không có hạn xuống cuối.
             *
             * Trước đây xếp theo ngày lưu: mã sắp hết hạn trong ngày mai
             * nằm lẫn dưới mã lưu hôm qua còn hạn cả tháng — khách để mất
             * mã mà không biết. Cùng hạn thì giữ thứ tự ngày lưu (sortBy ổn định).
             */
            ->sortBy(fn (array $row) => $row['coupon']->ends_at?->getTimestamp() ?? PHP_INT_MAX)
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

    /**
     * Mã này có trong ví của khách không.
     *
     * TÍNH CẢ MÃ ĐÃ ẨN. Hàng vẫn tồn tại nên nút "Lưu mã" bấm vào sẽ
     * đụng ràng buộc UNIQUE; hiện nút đó ra là mời khách bấm một nút
     * không làm gì. Giao diện phải mời "đưa lại về ví" thay vì "lưu mã".
     */
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

        /*
         * TĂNG BỘ ĐẾM KÈM ĐIỀU KIỆN, rồi kiểm SỐ DÒNG BỊ ẢNH HƯỞNG — cùng
         * cách với giới hạn chung ở CouponService::redeem().
         *
         * Lỗi đã sửa (race condition): bản cũ kiểm userLimitReached() ở một
         * bước, rồi upsert "+1" ở bước khác. Giới hạn 1 lần, hai đơn gửi
         * cùng lúc: cả hai đọc used_count = 0, cả hai qua, bộ đếm lên 2 —
         * một người dùng mã hai lần. Gộp kiểm và ghi vào MỘT câu UPDATE có
         * điều kiện thì cơ sở dữ liệu tự phân xử.
         *
         * Bước 1 chỉ bảo đảm có dòng để cập nhật (khách nhập tay một mã
         * chưa từng lưu vào ví). insertOrIgnore không đè dòng đã có, nên
         * không reset bộ đếm của ai.
         */
        DB::table('coupon_user')->insertOrIgnore([
            'user_id' => $user->id,
            'coupon_id' => $coupon->id,
            'claimed_at' => now(),
            'used_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Đọc giới hạn TỪ CƠ SỞ DỮ LIỆU, không tin đối tượng trong bộ nhớ có
        // thể đã cũ từ lúc khách mở trang thanh toán.
        $gioiHan = \App\Models\Coupon::whereKey($coupon->id)->value('per_user_limit');

        $ghiNhan = DB::table('coupon_user')
            ->where('user_id', $user->id)
            ->where('coupon_id', $coupon->id)
            ->when($gioiHan !== null, fn ($q) => $q->where('used_count', '<', $gioiHan))
            ->update(['used_count' => DB::raw('used_count + 1'), 'updated_at' => now()]);

        if ($ghiNhan === 0) {
            // Ném để transaction tạo đơn cuộn lại — kể cả lượt dùng chung đã tăng.
            throw new CouponException(sprintf(
                'Tài khoản của bạn đã dùng hết số lần cho mã %s.',
                $coupon->code,
            ));
        }
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
